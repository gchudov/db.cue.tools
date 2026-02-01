package metadata

import (
	"database/sql"
	"encoding/base64"
	"fmt"
	"strconv"
	"strings"

	"github.com/cuetools/ctdbweb/internal/models"
	"github.com/cuetools/ctdbweb/internal/toc"
)

// CTDBClient handles queries to the CTDB submissions database
type CTDBClient struct {
	db *sql.DB
}

// NewCTDBClient creates a new CTDB metadata client
func NewCTDBClient(db *sql.DB) *CTDBClient {
	return &CTDBClient{db: db}
}

// LookupByTOC performs exact or fuzzy TOC matching against submissions2 table
func (c *CTDBClient) LookupByTOC(tocString string, fuzzy bool) ([]models.CTDBEntry, error) {
	// Parse TOC string to get offsets
	tocObj, err := toc.ParseTOCString(tocString)
	if err != nil {
		return nil, fmt.Errorf("failed to parse TOC: %w", err)
	}

	// Calculate TOCID (SHA-1 based fingerprint)
	tocID := tocObj.ToTOCID()

	// Build SQL query based on fuzzy flag
	var query string
	var args []interface{}

	baseQuery := `
		SELECT id, artist, title, tocid, crc32, subcount, track_crcs,
		       firstaudio, audiotracks, trackcount, trackoffsets,
		       hasparity, s3, syndrome
		FROM submissions2
	`

	if fuzzy {
		// Fuzzy match: only match TOCID
		query = baseQuery + "WHERE tocid = $1 ORDER BY id"
		args = []interface{}{tocID}
	} else {
		// Exact match: match both TOCID and trackoffsets
		offsetsStr := tocObj.OffsetsString()
		query = baseQuery + "WHERE tocid = $1 AND trackoffsets = $2 ORDER BY id"
		args = []interface{}{tocID, offsetsStr}
	}

	// Execute query
	rows, err := c.db.Query(query, args...)
	if err != nil {
		return nil, fmt.Errorf("database query failed: %w", err)
	}
	defer rows.Close()

	// Parse results
	var entries []models.CTDBEntry

	for rows.Next() {
		var entry models.CTDBEntry
		var artist, title sql.NullString
		var syndrome []byte
		var crc32 int32
		var trackCRCs []int32
		var firstaudio, audiotracks, trackcount int
		var trackoffsets string
		var s3 bool

		err := rows.Scan(
			&entry.ID,
			&artist,
			&title,
			&entry.TOCID,
			&crc32,
			&entry.Confidence,
			(*IntArray)(&trackCRCs),
			&firstaudio,
			&audiotracks,
			&trackcount,
			&trackoffsets,
			&entry.HasParity,
			&s3,
			&syndrome,
		)
		if err != nil {
			return nil, fmt.Errorf("failed to scan row: %w", err)
		}

		// Set optional fields
		if artist.Valid {
			entry.Artist = artist.String
		}
		if title.Valid {
			entry.Title = title.String
		}
		if syndrome != nil && len(syndrome) > 0 {
			// Base64 encode syndrome (matches PHP behavior - lookup2.php:134)
			entry.Syndrome = base64.StdEncoding.EncodeToString(syndrome)
		}

		// Format CRC32 as 8-digit lowercase hex
		entry.CRC32 = fmt.Sprintf("%08x", uint32(crc32))

		// Format track CRCs as hex strings
		if len(trackCRCs) > 0 {
			entry.TrackCRCs = make([]string, len(trackCRCs))
			for i, crc := range trackCRCs {
				entry.TrackCRCs[i] = fmt.Sprintf("%08x", uint32(crc))
			}
		}

		// Convert TOC from database format to client format
		// Database stores: "0 20107 308930 332850" (space-separated)
		// Client expects: "0:20107:-308930:332850" (colon-separated with - for data tracks)
		// Uses toc.String() method (port of PHP toc_toc2s - lookup2.php:136)
		offsetParts := strings.Fields(trackoffsets)
		offsets := make([]int, len(offsetParts))
		for i, part := range offsetParts {
			offsets[i], _ = strconv.Atoi(part)
		}
		tocRecord := &toc.TOC{
			FirstAudio:  firstaudio,
			AudioTracks: audiotracks,
			TrackCount:  trackcount,
			Offsets:     offsets,
		}
		entry.TOC = tocRecord.String()

		// Build parity URL if available
		if entry.HasParity && syndrome != nil && len(syndrome) > 0 {
			if s3 {
				entry.ParityURL = fmt.Sprintf("http://p.cuetools.net/%d", entry.ID)
			} else {
				entry.ParityURL = fmt.Sprintf("/parity/%d", entry.ID)
			}
		}

		entries = append(entries, entry)
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("error iterating rows: %w", err)
	}

	return entries, nil
}

// IntArray is a helper type for scanning PostgreSQL integer arrays
type IntArray []int32

func (a *IntArray) Scan(src interface{}) error {
	if src == nil {
		*a = nil
		return nil
	}

	// PostgreSQL returns arrays as byte slices in text format
	bytes, ok := src.([]byte)
	if !ok {
		return fmt.Errorf("expected []byte, got %T", src)
	}

	// Parse PostgreSQL array format: {1,2,3}
	str := string(bytes)
	if len(str) < 2 || str[0] != '{' || str[len(str)-1] != '}' {
		return fmt.Errorf("invalid array format: %s", str)
	}

	// Handle empty array
	if str == "{}" {
		*a = []int32{}
		return nil
	}

	// Parse comma-separated values
	content := str[1 : len(str)-1]
	var result []int32
	var current int32
	var num string

	for i := 0; i < len(content); i++ {
		if content[i] == ',' {
			if num != "" {
				fmt.Sscanf(num, "%d", &current)
				result = append(result, current)
				num = ""
			}
		} else {
			num += string(content[i])
		}
	}

	// Add last number
	if num != "" {
		fmt.Sscanf(num, "%d", &current)
		result = append(result, current)
	}

	*a = result
	return nil
}
