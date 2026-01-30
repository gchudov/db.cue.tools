package database

import (
	"database/sql"
	"fmt"
	"net/http"
	"strconv"
	"strings"
	"time"

	"github.com/cuetools/ctdbweb/internal/models"
	"github.com/cuetools/ctdbweb/pkg/pgarray"
)

// SubmissionFilters holds optional filter parameters for submission queries
type SubmissionFilters struct {
	TOCID  string // Exact match on TOCID
	Artist string // Case-insensitive match on artist name
}

// RecentSubmissionFilters holds optional filter parameters for recent submission queries
type RecentSubmissionFilters struct {
	TOC       string // Convert to tocid, then exact match
	TOCID     string // Exact match on tocid
	Artist    string // Case-insensitive contains (ILIKE '%value%')
	Agent     string // Case-insensitive prefix (ILIKE 'value%')
	DriveName string // Case-insensitive prefix (ILIKE 'value%')
	UserID    string // Exact match on userid
	IP        string // Exact match on IP address
}

// GetSubmissions retrieves CD submissions from CTDB with different sort modes
// sortBy: "latest" (newest first) or "top" (most popular first)
// filters: optional filters for TOCID and artist
func GetSubmissions(db *sql.DB, start, limit int, sortBy string, filters *SubmissionFilters) ([]models.Submission, error) {
	// Build base SELECT clause
	selectClause := `
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
		FROM submissions2 s`

	// Build WHERE clause based on sort type and filters
	var whereClauses []string
	var args []interface{}
	paramCounter := 1

	// Add filter conditions first
	hasFilters := false
	if filters != nil {
		if filters.TOCID != "" {
			whereClauses = append(whereClauses, fmt.Sprintf("s.tocid = $%d", paramCounter))
			args = append(args, filters.TOCID)
			paramCounter++
			hasFilters = true
		}
		if filters.Artist != "" {
			whereClauses = append(whereClauses, fmt.Sprintf("s.artist ILIKE $%d", paramCounter))
			args = append(args, filters.Artist)
			paramCounter++
			hasFilters = true
		}
	}

	// Add sort-specific WHERE clause only if no filters are provided
	// This matches PHP behavior: top.php only adds subcount constraint when no filters
	if sortBy == "top" && !hasFilters {
		whereClauses = append(whereClauses, fmt.Sprintf("s.subcount > 50"))
	}

	// Build WHERE clause
	whereClause := ""
	if len(whereClauses) > 0 {
		whereClause = " WHERE " + strings.Join(whereClauses, " AND ")
	}

	// Build ORDER BY clause
	var orderByClause string
	switch sortBy {
	case "latest":
		orderByClause = " ORDER BY s.id DESC"
	case "top":
		orderByClause = " ORDER BY s.subcount DESC, s.id DESC"
	default:
		return nil, fmt.Errorf("invalid sortBy parameter: %s (must be 'latest' or 'top')", sortBy)
	}

	// Add LIMIT and OFFSET parameters
	limitParam := fmt.Sprintf("$%d", paramCounter)
	paramCounter++
	offsetParam := fmt.Sprintf("$%d", paramCounter)
	args = append(args, limit, start)

	// Build complete query
	query := selectClause + whereClause + orderByClause + fmt.Sprintf(" LIMIT %s OFFSET %s", limitParam, offsetParam)

	rows, err := db.Query(query, args...)
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

// GetLatestSubmissions retrieves the latest CD submissions from CTDB
// Deprecated: Use GetSubmissions(db, start, limit, "latest", filters) instead
func GetLatestSubmissions(db *sql.DB, start, limit int) ([]models.Submission, error) {
	return GetSubmissions(db, start, limit, "latest", nil)
}

// GetTopSubmissions retrieves the most popular CD submissions from CTDB
// Deprecated: Use GetSubmissions(db, start, limit, "top", filters) instead
func GetTopSubmissions(db *sql.DB, start, limit int) ([]models.Submission, error) {
	return GetSubmissions(db, start, limit, "top", nil)
}

// GetRecentSubmissions retrieves recent CD submissions with optional filters
// Returns up to 'limit' most recent submissions ordered by subid DESC
// Joins submissions with submissions2 to get both submission metadata and CD info
func GetRecentSubmissions(db *sql.DB, limit int, filters *RecentSubmissionFilters) ([]models.RecentSubmission, error) {
	// Default and cap limit
	if limit <= 0 {
		limit = 100
	}
	if limit > 1000 {
		limit = 1000
	}

	// Build base SELECT clause
	selectClause := `
		SELECT
			s.time, s.agent, s.drivename, s.userid, s.ip,
			s.quality, s.barcode, s.entryid,
			e.subcount, e.crc32, e.tocid, e.artist, e.title,
			e.firstaudio, e.audiotracks, e.trackcount, e.trackoffsets
		FROM submissions s
		INNER JOIN submissions2 e ON e.id = s.entryid`

	// Build WHERE clause based on filters
	var whereClauses []string
	var args []interface{}
	paramCounter := 1

	if filters != nil {
		// TOC filter (convert to TOCID first)
		if filters.TOC != "" {
			whereClauses = append(whereClauses, fmt.Sprintf("e.tocid = $%d", paramCounter))
			args = append(args, filters.TOC)
			paramCounter++
		}

		// TOCID filter (exact match)
		if filters.TOCID != "" {
			whereClauses = append(whereClauses, fmt.Sprintf("e.tocid = $%d", paramCounter))
			args = append(args, filters.TOCID)
			paramCounter++
		}

		// Artist filter (case-insensitive contains)
		if filters.Artist != "" {
			whereClauses = append(whereClauses, fmt.Sprintf("e.artist ILIKE $%d", paramCounter))
			args = append(args, "%"+filters.Artist+"%")
			paramCounter++
		}

		// Agent filter (case-insensitive prefix)
		if filters.Agent != "" {
			whereClauses = append(whereClauses, fmt.Sprintf("s.agent ILIKE $%d", paramCounter))
			args = append(args, filters.Agent+"%")
			paramCounter++
		}

		// DriveName filter (case-insensitive prefix)
		if filters.DriveName != "" {
			whereClauses = append(whereClauses, fmt.Sprintf("s.drivename ILIKE $%d", paramCounter))
			args = append(args, filters.DriveName+"%")
			paramCounter++
		}

		// UserID filter (exact match)
		if filters.UserID != "" {
			whereClauses = append(whereClauses, fmt.Sprintf("s.userid = $%d", paramCounter))
			args = append(args, filters.UserID)
			paramCounter++
		}

		// IP filter (exact match)
		if filters.IP != "" {
			whereClauses = append(whereClauses, fmt.Sprintf("s.ip = $%d", paramCounter))
			args = append(args, filters.IP)
			paramCounter++
		}
	}

	// Build WHERE clause
	whereClause := ""
	if len(whereClauses) > 0 {
		whereClause = " WHERE " + strings.Join(whereClauses, " AND ")
	}

	// Add ORDER BY and LIMIT
	limitParam := fmt.Sprintf("$%d", paramCounter)
	args = append(args, limit)

	// Build complete query
	query := selectClause + whereClause + " ORDER BY s.subid DESC LIMIT " + limitParam

	rows, err := db.Query(query, args...)
	if err != nil {
		return nil, fmt.Errorf("query failed: %w", err)
	}
	defer rows.Close()

	var submissions []models.RecentSubmission
	for rows.Next() {
		var rs models.RecentSubmission
		var agent, drivename, userid, ip, barcode, artist, title, tocid, trackOffsets sql.NullString
		var quality sql.NullInt64

		err := rows.Scan(
			&rs.Time,
			&agent,
			&drivename,
			&userid,
			&ip,
			&quality,
			&barcode,
			&rs.ID,
			&rs.SubCount,
			&rs.CRC32,
			&tocid,
			&artist,
			&title,
			&rs.FirstAudio,
			&rs.AudioTracks,
			&rs.TrackCount,
			&trackOffsets,
		)
		if err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}

		// Handle NULL strings
		if agent.Valid {
			rs.Agent = agent.String
		}
		if drivename.Valid {
			rs.DriveName = drivename.String
		}
		if userid.Valid {
			rs.UserID = userid.String
		}
		if ip.Valid {
			rs.IP = ip.String
		}
		if barcode.Valid {
			rs.Barcode = barcode.String
		}
		if artist.Valid {
			rs.Artist = artist.String
		}
		if title.Valid {
			rs.Title = title.String
		}
		if tocid.Valid {
			rs.TOCID = tocid.String
		}
		if trackOffsets.Valid {
			rs.TrackOffsets = trackOffsets.String
		}

		// Handle NULL quality
		if quality.Valid {
			qualityInt := int(quality.Int64)
			rs.Quality = &qualityInt
		}

		submissions = append(submissions, rs)
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
