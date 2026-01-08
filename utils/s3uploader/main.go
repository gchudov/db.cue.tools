package main

import (
	"context"
	"database/sql"
	"fmt"
	"log"
	"os"
	"sync"
	"time"

	"github.com/aws/aws-sdk-go-v2/aws"
	"github.com/aws/aws-sdk-go-v2/config"
	"github.com/aws/aws-sdk-go-v2/service/s3"
	"github.com/aws/aws-sdk-go-v2/service/s3/types"
	_ "github.com/lib/pq"
)

const (
	bucket     = "p.cuetools.net"
	parityDir  = "/var/www/html/parity/"
	batchSize  = 10
	dbConnStr  = "dbname=ctdb user=ctdb_user host=/var/run/postgresql port=6432 sslmode=disable"
)

type submission struct {
	ID    int64
	TocID string
	CRC32 int64
}

func main() {
	// Connect to PostgreSQL
	db, err := sql.Open("postgres", dbConnStr)
	if err != nil {
		log.Fatalf("Could not connect to database: %v", err)
	}
	defer db.Close()

	if err := db.Ping(); err != nil {
		log.Fatalf("Could not ping database: %v", err)
	}

	// Load AWS config
	ctx := context.Background()
	cfg, err := config.LoadDefaultConfig(ctx, config.WithRegion("us-east-1"))
	if err != nil {
		log.Fatalf("Could not load AWS config: %v", err)
	}

	s3Client := s3.NewFromConfig(cfg)

	// Main processing loop
	for {
		processed, err := processBatch(ctx, db, s3Client)
		if err != nil {
			log.Fatalf("Error processing batch: %v", err)
		}
		if !processed {
			fmt.Printf("%s nothing to do\n", time.Now().UTC().Format("Jan 2 15:04:05"))
			break
		}
	}
}

func processBatch(ctx context.Context, db *sql.DB, s3Client *s3.Client) (bool, error) {
	// Start transaction
	tx, err := db.BeginTx(ctx, nil)
	if err != nil {
		return false, fmt.Errorf("begin transaction: %w", err)
	}
	defer tx.Rollback()

	// Query submissions that need uploading
	rows, err := tx.QueryContext(ctx, `
		SELECT id, tocid, crc32 
		FROM submissions2 
		WHERE hasparity = true AND s3 = false 
		ORDER BY id 
		LIMIT $1
	`, batchSize)
	if err != nil {
		return false, fmt.Errorf("query submissions: %w", err)
	}

	var submissions []submission
	for rows.Next() {
		var s submission
		if err := rows.Scan(&s.ID, &s.TocID, &s.CRC32); err != nil {
			rows.Close()
			return false, fmt.Errorf("scan row: %w", err)
		}
		submissions = append(submissions, s)
	}
	rows.Close()
	if err := rows.Err(); err != nil {
		return false, fmt.Errorf("rows error: %w", err)
	}

	if len(submissions) == 0 {
		return false, nil
	}

	// Process each submission
	var totalSize int64
	var toUpload []submission
	var fileSizes []int64

	for _, sub := range submissions {
		localPath := fmt.Sprintf("%s%d", parityDir, sub.ID)
		
		info, err := os.Stat(localPath)
		if os.IsNotExist(err) {
			fmt.Printf("File missing: id=%d tocid=%s\n", sub.ID, sub.TocID)
			_, err := tx.ExecContext(ctx, 
				"UPDATE submissions2 SET hasparity = false WHERE id=$1 AND NOT s3", 
				sub.ID)
			if err != nil {
				return false, fmt.Errorf("update missing file: %w", err)
			}
			continue
		} else if err != nil {
			return false, fmt.Errorf("stat file %s: %w", localPath, err)
		}

		totalSize += info.Size()
		toUpload = append(toUpload, sub)
		fileSizes = append(fileSizes, info.Size())

		// Mark as uploaded in DB (will be committed after S3 upload succeeds)
		_, err = tx.ExecContext(ctx, "UPDATE submissions2 SET s3 = TRUE WHERE id=$1", sub.ID)
		if err != nil {
			return false, fmt.Errorf("update s3 flag: %w", err)
		}
	}

	if len(toUpload) == 0 {
		if err := tx.Commit(); err != nil {
			return false, fmt.Errorf("commit (no uploads): %w", err)
		}
		return true, nil
	}

	// Upload files concurrently
	start := time.Now()
	var wg sync.WaitGroup
	errChan := make(chan error, len(toUpload))
	urlChan := make(chan string, len(toUpload))

	for _, sub := range toUpload {
		wg.Add(1)
		go func(sub submission) {
			defer wg.Done()
			
			localPath := fmt.Sprintf("%s%d", parityDir, sub.ID)
			file, err := os.Open(localPath)
			if err != nil {
				errChan <- fmt.Errorf("open file %s: %w", localPath, err)
				return
			}
			defer file.Close()

			key := fmt.Sprintf("%d", sub.ID)
			_, err = s3Client.PutObject(ctx, &s3.PutObjectInput{
				Bucket: aws.String(bucket),
				Key:    aws.String(key),
				Body:   file,
				ACL:    types.ObjectCannedACLPublicRead,
			})
			if err != nil {
				errChan <- fmt.Errorf("upload %s: %w", key, err)
				return
			}

			urlChan <- fmt.Sprintf("https://%s.s3.amazonaws.com/%s", bucket, key)
		}(sub)
	}

	// Wait for all uploads
	wg.Wait()
	close(errChan)
	close(urlChan)

	// Check for errors
	var uploadErrors []error
	for err := range errChan {
		uploadErrors = append(uploadErrors, err)
	}

	if len(uploadErrors) > 0 {
		for _, err := range uploadErrors {
			fmt.Printf("Upload error: %v\n", err)
		}
		return false, fmt.Errorf("upload failed: %v", uploadErrors[0])
	}

	// Print uploaded URLs
	for url := range urlChan {
		fmt.Printf("%s uploaded.\n", url)
	}

	duration := time.Since(start).Seconds()
	if duration < 0.01 {
		duration = 0.01
	}

	// Commit transaction
	if err := tx.Commit(); err != nil {
		return false, fmt.Errorf("commit: %w", err)
	}

	fmt.Printf("%s COMMIT %d files, %d bytes in %.0f secs (%dKB/s)\n",
		time.Now().UTC().Format("Jan 2 15:04:05"),
		len(toUpload),
		totalSize,
		duration,
		int(float64(totalSize)/duration/1024))

	// Delete local files after successful commit
	for _, sub := range toUpload {
		localPath := fmt.Sprintf("%s%d", parityDir, sub.ID)
		if err := os.Remove(localPath); err != nil {
			log.Printf("Warning: could not delete %s: %v", localPath, err)
		}
	}

	return true, nil
}
