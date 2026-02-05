package handlers

import (
	"encoding/json"
	"net/http"
	"strconv"

	"github.com/cuetools/ctdbweb/internal/database"
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
	query := r.URL.Query()

	// Parse limit (default 50, max 1000)
	limit := 50
	if limitStr := query.Get("limit"); limitStr != "" {
		if l, err := strconv.Atoi(limitStr); err == nil && l > 0 && l <= 1000 {
			limit = l
		}
	}

	// Parse cursor parameters for cursor-based pagination
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

	// Parse filter parameters
	var filters *database.SubmissionFilters
	tocidFilter := query.Get("tocid")
	artistFilter := query.Get("artist")
	if tocidFilter != "" || artistFilter != "" {
		filters = &database.SubmissionFilters{
			TOCID:  tocidFilter,
			Artist: artistFilter,
		}
	}

	// Query database with cursor-based pagination
	submissions, err := database.GetSubmissions(h.db.CTDB, limit, cursor, before, h.sortBy, filters)
	if err != nil {
		http.Error(w, `{"error":"Failed to fetch submissions: `+err.Error()+`"}`, http.StatusInternalServerError)
		return
	}

	// Populate computed fields for all submissions
	for i := range submissions {
		submissions[i].PopulateComputedFields()
	}

	// Build cursor-based response with metadata
	var newestID, oldestID int64
	hasMore := len(submissions) >= limit

	if len(submissions) > 0 {
		// For "latest" with cursor param, results are ASC order, so reverse for response
		if h.sortBy == "latest" && cursor > 0 {
			// Reverse the slice to get DESC order (newest first)
			for i, j := 0, len(submissions)-1; i < j; i, j = i+1, j-1 {
				submissions[i], submissions[j] = submissions[j], submissions[i]
			}
		}

		// After potential reversal: first entry has newest id, last has oldest
		newestID = int64(submissions[0].ID)
		oldestID = int64(submissions[len(submissions)-1].ID)
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
		http.Error(w, `{"error":"Failed to encode response"}`, http.StatusInternalServerError)
	}
}
