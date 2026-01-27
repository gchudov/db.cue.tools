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

// SubmissionsHandler handles requests for CD submissions with different sort modes
type SubmissionsHandler struct {
	db     *database.DB
	sortBy string // "latest" or "top"
}

// NewSubmissionsHandler creates a new submissions handler with specified sort mode
func NewSubmissionsHandler(db *database.DB, sortBy string) *SubmissionsHandler {
	return &SubmissionsHandler{
		db:     db,
		sortBy: sortBy,
	}
}

// ServeHTTP handles the /api/latest and /api/top endpoints
func (h *SubmissionsHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	// Parse query parameters
	startParam := r.URL.Query().Get("start")
	start := 0
	if startParam != "" {
		if s, err := strconv.Atoi(startParam); err == nil {
			start = s
		}
	}

	limitParam := r.URL.Query().Get("limit")
	limit := 50
	if limitParam != "" {
		if l, err := strconv.Atoi(limitParam); err == nil && l > 0 && l <= 1000 {
			limit = l
		}
	}

	// Parse filter parameters
	var filters *database.SubmissionFilters
	tocidFilter := r.URL.Query().Get("tocid")
	artistFilter := r.URL.Query().Get("artist")
	if tocidFilter != "" || artistFilter != "" {
		filters = &database.SubmissionFilters{
			TOCID:  tocidFilter,
			Artist: artistFilter,
		}
	}

	// Query database using unified function
	submissions, err := database.GetSubmissions(h.db.CTDB, start, limit, h.sortBy, filters)
	if err != nil {
		http.Error(w, `{"error":"Failed to fetch submissions: `+err.Error()+`"}`, http.StatusInternalServerError)
		return
	}

	// Check if Google Visualization format is requested
	jsonParam := r.URL.Query().Get("json")
	if jsonParam == "1" {
		// Return in Google Visualization API format
		response := formatSubmissionsAsGoogleViz(submissions)
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusOK)
		encoder := json.NewEncoder(w)
		if err := encoder.Encode(response); err != nil {
			http.Error(w, `{"error":"Failed to encode response"}`, http.StatusInternalServerError)
		}
		return
	}

	// Return plain JSON
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	encoder := json.NewEncoder(w)
	encoder.SetIndent("", "  ")
	if err := encoder.Encode(submissions); err != nil {
		http.Error(w, `{"error":"Failed to encode response"}`, http.StatusInternalServerError)
	}
}

// formatSubmissionsAsGoogleViz converts submissions to Google Visualization API format
func formatSubmissionsAsGoogleViz(submissions interface{}) map[string]interface{} {
	cols := []map[string]interface{}{
		{"label": "Artist", "type": "string"},
		{"label": "Album", "type": "string"},
		{"label": "Disc Id", "type": "string"},
		{"label": "Tracks", "type": "string"},
		{"label": "CTDB Id", "type": "number"},
		{"label": "Cf", "type": "number"},
		{"label": "CRC32", "type": "number"},
		{"label": "TOC", "type": "string"},
		{"label": "Track CRCs", "type": "string"},
	}

	// Convert submissions to rows
	submissionList, ok := submissions.([]models.Submission)
	if !ok {
		return map[string]interface{}{
			"cols": cols,
			"rows": []map[string]interface{}{},
		}
	}

	rows := make([]map[string]interface{}, len(submissionList))
	for i, sub := range submissionList {
		// Format tracks string (e.g., "12" or "1+12" or "12+1")
		tracksStr := sub.TrackCountString()

		// Format track CRCs as space-separated hex values
		trackCRCsStr := ""
		if len(sub.TrackCRCs) > 0 {
			crcStrs := make([]string, len(sub.TrackCRCs))
			for j, crc := range sub.TrackCRCs {
				crcStrs[j] = fmt.Sprintf("%08x", uint32(crc))
			}
			trackCRCsStr = strings.Join(crcStrs, " ")
		}

		// Convert space-separated TOC offsets to colon-separated format with data track markers
		// Port of PHP: phpCTDB::toc_toc2s()
		tocStr := sub.TrackOffsets // Default to raw offsets if parsing fails
		if sub.TrackOffsets != "" {
			// Parse space-separated offsets from database
			offsetParts := strings.Fields(sub.TrackOffsets)
			offsets := make([]int, len(offsetParts))
			parseOk := true
			for idx, part := range offsetParts {
				offset, err := strconv.Atoi(part)
				if err != nil {
					parseOk = false
					break
				}
				offsets[idx] = offset
			}

			if parseOk && len(offsets) > 0 {
				// Create TOC struct from database fields
				tocStruct := &toc.TOC{
					FirstAudio:  sub.FirstAudio,
					AudioTracks: sub.AudioTracks,
					TrackCount:  sub.TrackCount,
					Offsets:     offsets,
				}
				// Convert to colon-separated format with '-' prefixes for data tracks
				tocStr = tocStruct.String()
			}
		}

		rows[i] = map[string]interface{}{
			"c": []map[string]interface{}{
				{"v": sub.Artist},
				{"v": sub.Title},
				{"v": sub.TOCID},
				{"v": tracksStr},
				{"v": sub.ID},
				{"v": sub.SubCount},
				{"v": sub.CRC32},
				{"v": tocStr},
				{"v": trackCRCsStr},
			},
		}
	}

	return map[string]interface{}{
		"cols": cols,
		"rows": rows,
	}
}
