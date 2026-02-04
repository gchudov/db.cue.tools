package handlers

import (
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"strings"

	"github.com/cuetools/ctdbweb/internal/database"
	"github.com/cuetools/ctdbweb/internal/models"
	"github.com/cuetools/ctdbweb/internal/toc"
)

// RecentHandler handles requests for recent CD submissions (admin-only)
type RecentHandler struct {
	db *database.DB
}

// NewRecentHandler creates a new recent submissions handler
func NewRecentHandler(db *database.DB) *RecentHandler {
	return &RecentHandler{db: db}
}

// ServeHTTP handles the /api/recent endpoint
func (h *RecentHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	// Parse query parameters
	query := r.URL.Query()

	// Parse limit (default 100, max 1000)
	limit := 100
	if limitStr := query.Get("limit"); limitStr != "" {
		if l, err := strconv.Atoi(limitStr); err == nil {
			limit = l
		}
	}

	// Parse start/offset (default 0, legacy compatibility)
	offset := 0
	if startStr := query.Get("start"); startStr != "" {
		if s, err := strconv.Atoi(startStr); err == nil {
			offset = s
		}
	}

	// Parse cursor parameters (for cursor-based pagination)
	var cursor int64 = 0
	if cursorStr := query.Get("cursor"); cursorStr != "" {
		if c, err := strconv.ParseInt(cursorStr, 10, 64); err == nil {
			cursor = c
		}
	}

	var before int64 = 0
	if beforeStr := query.Get("before"); beforeStr != "" {
		if b, err := strconv.ParseInt(beforeStr, 10, 64); err == nil {
			before = b
		}
	}

	// Build filters
	filters := &database.RecentSubmissionFilters{}

	// Handle TOC parameter - convert to TOCID
	if tocStr := query.Get("toc"); tocStr != "" {
		tocObj, err := toc.ParseTOC(tocStr)
		if err == nil {
			filters.TOCID = tocObj.ToTOCID()
		}
	}

	// Handle other filter parameters
	if tocid := query.Get("tocid"); tocid != "" {
		filters.TOCID = tocid
	}
	if artist := query.Get("artist"); artist != "" {
		filters.Artist = artist
	}
	if agent := query.Get("agent"); agent != "" {
		filters.Agent = agent
	}
	if drivename := query.Get("drivename"); drivename != "" {
		filters.DriveName = drivename
	}
	if uid := query.Get("uid"); uid != "" {
		filters.UserID = uid
	}
	if ip := query.Get("ip"); ip != "" {
		filters.IP = ip
	}

	// Query database with cursor support
	submissions, err := database.GetRecentSubmissions(h.db.CTDB, limit, offset, cursor, before, filters)
	if err != nil {
		http.Error(w, fmt.Sprintf("Database error: %v", err), http.StatusInternalServerError)
		return
	}

	// Populate computed fields for all submissions
	for i := range submissions {
		submissions[i].PopulateComputedFields()
	}

	// Check if Google Visualization format is requested
	jsonParam := r.URL.Query().Get("json")
	if jsonParam == "1" {
		// Return in Google Visualization API format (legacy)
		response := formatRecentSubmissionsAsGoogleViz(submissions)
		w.Header().Set("Content-Type", "application/json")
		if err := json.NewEncoder(w).Encode(response); err != nil {
			http.Error(w, fmt.Sprintf("JSON encoding error: %v", err), http.StatusInternalServerError)
			return
		}
		return
	}

	// Check if cursor format is requested
	// Use cursor format if cursor/before params are present OR if format=cursor is specified
	formatParam := query.Get("format")
	useCursorFormat := cursor > 0 || before > 0 || formatParam == "cursor"

	if useCursorFormat {
		// Return cursor-based response with metadata
		var newestID, oldestID int64
		hasMore := len(submissions) >= limit

		if len(submissions) > 0 {
			// First entry has the newest subid (DESC order)
			newestID = submissions[0].SubID
			// Last entry has the oldest subid
			oldestID = submissions[len(submissions)-1].SubID
		}

		response := map[string]interface{}{
			"data": submissions,
			"cursors": map[string]interface{}{
				"newest": newestID,
				"oldest": oldestID,
			},
			"has_more": hasMore,
		}

		w.Header().Set("Content-Type", "application/json")
		encoder := json.NewEncoder(w)
		encoder.SetIndent("", "  ")
		if err := encoder.Encode(response); err != nil {
			http.Error(w, fmt.Sprintf("JSON encoding error: %v", err), http.StatusInternalServerError)
			return
		}
		return
	}

	// Return plain JSON array (legacy compatibility)
	w.Header().Set("Content-Type", "application/json")
	encoder := json.NewEncoder(w)
	encoder.SetIndent("", "  ")
	if err := encoder.Encode(submissions); err != nil {
		http.Error(w, fmt.Sprintf("JSON encoding error: %v", err), http.StatusInternalServerError)
		return
	}
}

// formatRecentSubmissionsAsGoogleViz converts recent submissions to Google Viz format
// Returns 15 columns matching PHP recent.php output
func formatRecentSubmissionsAsGoogleViz(submissions []models.RecentSubmission) map[string]interface{} {
	// Define 15 columns
	cols := []map[string]interface{}{
		{"label": "Date", "type": "number"},
		{"label": "Agent", "type": "string"},
		{"label": "Drive", "type": "string"},
		{"label": "User", "type": "string"},
		{"label": "IP", "type": "string"},
		{"label": "Artist", "type": "string"},
		{"label": "Album", "type": "string"},
		{"label": "TOC Id", "type": "string"},
		{"label": "Tr#", "type": "string"},
		{"label": "CTDB Id", "type": "number"},
		{"label": "Cf", "type": "string"},
		{"label": "CRC32", "type": "number"},
		{"label": "TOC", "type": "string"},
		{"label": "Q", "type": "number"},
		{"label": "Barcode", "type": "string"},
	}

	// Build rows
	rows := make([]map[string]interface{}, 0, len(submissions))
	for _, s := range submissions {
		// Format track count string (e.g., "12", "1+12", "12+1")
		trackCountStr := formatTrackCount(s.FirstAudio, s.AudioTracks, s.TrackCount)

		// Format TOC string (colon-separated with data track markers)
		tocStr := formatTOCString(s.TrackOffsets, s.FirstAudio, s.AudioTracks, s.TrackCount)

		// Build row with 15 columns
		row := map[string]interface{}{
			"c": []map[string]interface{}{
				{"v": s.Time.Unix()},            // Date (Unix timestamp)
				{"v": s.Agent},                  // Agent
				{"v": s.DriveName},              // Drive
				{"v": s.UserID},                 // User
				{"v": s.IP},                     // IP
				{"v": s.Artist},                 // Artist
				{"v": s.Title},                  // Album
				{"v": s.TOCID},                  // TOC Id
				{"v": trackCountStr},            // Tr# (formatted track count)
				{"v": s.ID},                     // CTDB Id
				{"v": strconv.Itoa(s.SubCount)}, // Cf (subcount)
				{"v": s.CRC32},                  // CRC32
				{"v": tocStr},                   // TOC (colon-separated)
				{"v": nilToNull(s.Quality)},     // Q (quality, nullable)
				{"v": s.Barcode},                // Barcode
			},
		}
		rows = append(rows, row)
	}

	return map[string]interface{}{
		"cols": cols,
		"rows": rows,
	}
}

// formatTrackCount formats track count string matching PHP logic
// Returns: "12" for normal CDs, "1+12" for data+audio, "12+1" for audio+data
// Matches PHP logic from recent.php lines 87-91
func formatTrackCount(firstAudio, audioTracks, trackCount int) string {
	if firstAudio > 1 {
		// Data tracks at the beginning
		dataTracks := firstAudio - 1
		return fmt.Sprintf("%d+%d", dataTracks, audioTracks)
	} else if audioTracks < trackCount {
		// Data tracks at the end (Enhanced CD)
		// PHP shows "audiotracks+1" not the actual count of data tracks
		return fmt.Sprintf("%d+1", audioTracks)
	} else {
		// Normal audio CD
		return strconv.Itoa(audioTracks)
	}
}

// formatTOCString converts track offsets to colon-separated format with data track markers
// Data tracks are prefixed with '-' (e.g., "150:12345:-234567:...")
func formatTOCString(trackOffsetsStr string, firstAudio, audioTracks, trackCount int) string {
	if trackOffsetsStr == "" {
		return ""
	}

	// Parse track offsets from space-separated string
	parts := strings.Fields(trackOffsetsStr)
	if len(parts) == 0 {
		return ""
	}

	// Mark data tracks with '-' prefix
	result := make([]string, len(parts))
	for i, offset := range parts {
		trackNum := i + 1

		// Determine if this is a data track
		// Data tracks are: tracks before firstAudio OR tracks after lastAudio (but not leadout)
		isDataTrack := false
		if trackNum < len(parts) { // Not leadout
			if trackNum < firstAudio || trackNum >= firstAudio+audioTracks {
				isDataTrack = true
			}
		}

		if isDataTrack {
			result[i] = "-" + offset
		} else {
			result[i] = offset
		}
	}

	return strings.Join(result, ":")
}

// nilToNull returns nil for nil pointers, otherwise returns the value
func nilToNull(ptr *int) interface{} {
	if ptr == nil {
		return nil
	}
	return *ptr
}
