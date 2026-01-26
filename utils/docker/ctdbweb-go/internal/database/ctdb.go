package database

import (
	"database/sql"
	"fmt"
	"net/http"
	"strconv"
	"time"

	"github.com/cuetools/ctdbweb/internal/models"
	"github.com/cuetools/ctdbweb/pkg/pgarray"
)

// GetLatestSubmissions retrieves the latest CD submissions from CTDB
func GetLatestSubmissions(db *sql.DB, start, limit int) ([]models.Submission, error) {
	query := `
		SELECT
			s.id,
			s.artist,
			s.title,
			s.tocid,
			s.firstaudio,
			s.audiotracks,
			s.trackcount,
			s.trackoffsets,
			s.subcount,
			s.crc32,
			s.track_crcs
		FROM submissions2 s
		ORDER BY s.id DESC
		LIMIT $1 OFFSET $2
	`

	rows, err := db.Query(query, limit, start)
	if err != nil {
		return nil, fmt.Errorf("query failed: %w", err)
	}
	defer rows.Close()

	var submissions []models.Submission
	for rows.Next() {
		var s models.Submission
		var artist, title, tocid sql.NullString
		var trackCRCsStr sql.NullString

		err := rows.Scan(
			&s.ID,
			&artist,
			&title,
			&tocid,
			&s.FirstAudio,
			&s.AudioTracks,
			&s.TrackCount,
			&s.TrackOffsets,
			&s.SubCount,
			&s.CRC32,
			&trackCRCsStr,
		)
		if err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}

		// Handle NULL strings
		if artist.Valid {
			s.Artist = artist.String
		}
		if title.Valid {
			s.Title = title.String
		}
		if tocid.Valid {
			s.TOCID = tocid.String
		}

		// Parse track CRCs if present
		if trackCRCsStr.Valid {
			trackCRCs, err := pgarray.Parse(trackCRCsStr.String)
			if err == nil {
				s.TrackCRCs = make([]int32, len(trackCRCs))
				for i, crc := range trackCRCs {
					if crcStr, ok := crc.(string); ok {
						var crcVal int32
						fmt.Sscanf(crcStr, "%d", &crcVal)
						s.TrackCRCs[i] = crcVal
					}
				}
			}
		}

		submissions = append(submissions, s)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("rows iteration failed: %w", err)
	}

	return submissions, nil
}

// GetTopSubmissions retrieves the most popular CD submissions from CTDB
func GetTopSubmissions(db *sql.DB, start, limit int) ([]models.Submission, error) {
	query := `
		SELECT
			s.id,
			s.artist,
			s.title,
			s.tocid,
			s.firstaudio,
			s.audiotracks,
			s.trackcount,
			s.trackoffsets,
			s.subcount,
			s.crc32,
			s.track_crcs
		FROM submissions2 s
		WHERE s.subcount > 1
		ORDER BY s.subcount DESC, s.id DESC
		LIMIT $1 OFFSET $2
	`

	rows, err := db.Query(query, limit, start)
	if err != nil {
		return nil, fmt.Errorf("query failed: %w", err)
	}
	defer rows.Close()

	var submissions []models.Submission
	for rows.Next() {
		var s models.Submission
		var artist, title, tocid sql.NullString
		var trackCRCsStr sql.NullString

		err := rows.Scan(
			&s.ID,
			&artist,
			&title,
			&tocid,
			&s.FirstAudio,
			&s.AudioTracks,
			&s.TrackCount,
			&s.TrackOffsets,
			&s.SubCount,
			&s.CRC32,
			&trackCRCsStr,
		)
		if err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}

		// Handle NULL strings
		if artist.Valid {
			s.Artist = artist.String
		}
		if title.Valid {
			s.Title = title.String
		}
		if tocid.Valid {
			s.TOCID = tocid.String
		}

		// Parse track CRCs if present
		if trackCRCsStr.Valid {
			trackCRCs, err := pgarray.Parse(trackCRCsStr.String)
			if err == nil {
				s.TrackCRCs = make([]int32, len(trackCRCs))
				for i, crc := range trackCRCs {
					if crcStr, ok := crc.(string); ok {
						var crcVal int32
						fmt.Sscanf(crcStr, "%d", &crcVal)
						s.TrackCRCs[i] = crcVal
					}
				}
			}
		}

		submissions = append(submissions, s)
	}

	if err = rows.Err(); err != nil {
		return nil, fmt.Errorf("rows iteration failed: %w", err)
	}

	return submissions, nil
}

// GetStats retrieves statistics from CTDB
func GetStats(db *sql.DB, statType string, r interface{}) (interface{}, error) {
	switch statType {
	case "totals":
		return getStatsTotals(db)
	case "drives":
		return getStatsDrives(db)
	case "agents":
		return getStatsAgents(db)
	case "pregaps":
		return getStatsPregaps(db)
	case "submissions":
		return getStatsSubmissions(db, r)
	default:
		return nil, fmt.Errorf("unknown stat type: %s", statType)
	}
}

func getStatsTotals(db *sql.DB) (interface{}, error) {
	// Step 1: Get cached unique_tocs count and maxid from stats_totals
	var cachedUniqueTocs, maxID int
	err := db.QueryRow(`SELECT val, maxid FROM stats_totals WHERE kind='unique_tocs'`).Scan(&cachedUniqueTocs, &maxID)
	if err != nil {
		return nil, fmt.Errorf("failed to get cached unique_tocs: %w", err)
	}

	// Step 2: Count new distinct TOCs in submissions2 since maxid
	var newUniqueTocs int
	err = db.QueryRow(`SELECT count(DISTINCT tocid) as val FROM submissions2 WHERE id > $1`, maxID).Scan(&newUniqueTocs)
	if err != nil {
		return nil, fmt.Errorf("failed to count new unique TOCs: %w", err)
	}

	// Step 3: Calculate total unique_tocs (cached + new)
	totalUniqueTocs := cachedUniqueTocs + newUniqueTocs

	// Step 4: Get submissions count (cached value + new submissions since maxid)
	var totalSubmissions int
	err = db.QueryRow(`SELECT val+(SELECT count(*) FROM submissions WHERE subid > maxid) AS val FROM stats_totals WHERE kind='submissions'`).Scan(&totalSubmissions)
	if err != nil {
		return nil, fmt.Errorf("failed to get submissions count: %w", err)
	}

	// Step 5: Return result matching PHP format
	return map[string]int{
		"unique_tocs": totalUniqueTocs,
		"submissions": totalSubmissions,
	}, nil
}

func getStatsDrives(db *sql.DB) (interface{}, error) {
	query := `SELECT label, cnt FROM stats_drives ORDER BY cnt DESC LIMIT 100`
	rows, err := db.Query(query)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var stats []map[string]interface{}
	for rows.Next() {
		var label string
		var count int
		if err := rows.Scan(&label, &count); err != nil {
			return nil, err
		}
		stats = append(stats, map[string]interface{}{
			"drive": label,
			"count": count,
		})
	}
	return stats, rows.Err()
}

func getStatsAgents(db *sql.DB) (interface{}, error) {
	query := `SELECT label, cnt FROM stats_agents ORDER BY cnt DESC LIMIT 100`
	rows, err := db.Query(query)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var stats []map[string]interface{}
	for rows.Next() {
		var label string
		var count int
		if err := rows.Scan(&label, &count); err != nil {
			return nil, err
		}
		stats = append(stats, map[string]interface{}{
			"agent": label,
			"count": count,
		})
	}
	return stats, rows.Err()
}

func getStatsPregaps(db *sql.DB) (interface{}, error) {
	query := `SELECT label, cnt FROM stats_pregaps ORDER BY cnt DESC LIMIT 100`
	rows, err := db.Query(query)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var stats []map[string]interface{}
	for rows.Next() {
		var label string
		var count int
		if err := rows.Scan(&label, &count); err != nil {
			return nil, err
		}
		stats = append(stats, map[string]interface{}{
			"pregap": label,
			"count":  count,
		})
	}
	return stats, rows.Err()
}

func getStatsSubmissions(db *sql.DB, r interface{}) (interface{}, error) {
	// Type assert to get HTTP request
	req, ok := r.(*http.Request)
	if !ok {
		return nil, fmt.Errorf("invalid request type")
	}

	// Parse query parameters
	hourly := req.URL.Query().Get("hourly") != ""
	countStr := req.URL.Query().Get("count")
	count := 100
	if countStr != "" {
		if c, err := strconv.Atoi(countStr); err == nil {
			count = c
		}
	}

	// Calculate time range
	secondsCount := 60 * 60 * count
	if !hourly {
		secondsCount *= 24
	}
	
	now := time.Now().UTC()
	since := now.Add(-time.Duration(secondsCount) * time.Second)
	
	// Format timestamps for query
	var sinceStr, tillStr string
	if hourly {
		sinceStr = since.Format("2006-01-02 15:00:00")
		tillStr = now.Format("2006-01-02 15:00:00")
	} else {
		sinceStr = since.Format("2006-01-02")
		tillStr = now.Format("2006-01-02")
	}

	// Query database
	truncType := "day"
	if hourly {
		truncType = "hour"
	}
	
	query := `
		SELECT 
			date_trunc($1, hour) as t,
			sum(eac) as eac,
			sum(cueripper) as cueripper,
			sum(cuetools) as cuetools
		FROM hourly_stats
		WHERE hour > $2 AND hour < $3
		GROUP BY t
		ORDER BY t
	`
	
	rows, err := db.Query(query, truncType, sinceStr, tillStr)
	if err != nil {
		return nil, fmt.Errorf("query failed: %w", err)
	}
	defer rows.Close()

	var submissions []map[string]interface{}
	for rows.Next() {
		var t time.Time
		var eac, cueripper, cuetools int
		if err := rows.Scan(&t, &eac, &cueripper, &cuetools); err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}
		
		// Format date/time
		var dateStr string
		if hourly {
			dateStr = t.Format("01-02 15:00")
		} else {
			dateStr = t.Format("2006-01-02")
		}
		
		submissions = append(submissions, map[string]interface{}{
			"date":      dateStr,
			"eac":       eac,
			"cueripper": cueripper,
			"cuetools":  cuetools,
		})
	}
	
	return submissions, rows.Err()
}
