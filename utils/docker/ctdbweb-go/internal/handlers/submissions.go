package handlers

import (
	"encoding/json"
	"fmt"
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

// ServeHTTP handles the /api/additions and /api/top endpoints
func (h *SubmissionsHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	query := r.URL.Query()

	// Parse limit (default 50, max 1000)
	limit := 50
	if limitStr := query.Get("limit"); limitStr != "" {
		if l, err := strconv.Atoi(limitStr); err == nil && l > 0 && l <= 1000 {
			limit = l
		}
	}

	// Build params based on sort mode
	params := database.SubmissionParams{
		Limit:  limit,
		SortBy: h.sortBy,
	}

	// Parse filter parameters
	tocidFilter := query.Get("tocid")
	artistFilter := query.Get("artist")
	if tocidFilter != "" || artistFilter != "" {
		params.Filters = &database.SubmissionFilters{
			TOCID:  tocidFilter,
			Artist: artistFilter,
		}
	}

	// Parse cursor parameters based on sort mode
	if h.sortBy == "latest" {
		// Parse simple numeric cursors for "latest" mode
		if cursorStr := query.Get("cursor"); cursorStr != "" {
			if c, err := strconv.ParseInt(cursorStr, 10, 64); err == nil {
				params.Cursor = c
			}
		}
		if beforeStr := query.Get("before"); beforeStr != "" {
			if b, err := strconv.ParseInt(beforeStr, 10, 64); err == nil {
				params.Before = b
			}
		}
	} else if h.sortBy == "top" {
		// Parse composite cursor for "top" mode (format: "subcount:id")
		if beforeStr := query.Get("before"); beforeStr != "" {
			topCursor, err := database.ParseTopCursor(beforeStr)
			if err != nil {
				http.Error(w, `{"error":"Invalid cursor format for top mode: expected 'subcount:id'"}`, http.StatusBadRequest)
				return
			}
			params.TopBefore = topCursor
		}
	}

	// Query database
	submissions, err := database.GetSubmissions(h.db.CTDB, params)
	if err != nil {
		http.Error(w, `{"error":"Failed to fetch submissions: `+err.Error()+`"}`, http.StatusInternalServerError)
		return
	}

	// Populate computed fields for all submissions
	for i := range submissions {
		submissions[i].PopulateComputedFields()
	}

	// Build response based on mode
	hasMore := len(submissions) >= limit
	var response map[string]interface{}

	if h.sortBy == "top" {
		// Composite string cursors for "top" mode
		var newestCursor, oldestCursor string
		if len(submissions) > 0 {
			first := submissions[0]
			last := submissions[len(submissions)-1]
			newestCursor = fmt.Sprintf("%d:%d", first.SubCount, first.ID)
			oldestCursor = fmt.Sprintf("%d:%d", last.SubCount, last.ID)
		}

		response = map[string]interface{}{
			"data": submissions,
			"cursors": map[string]interface{}{
				"newest": newestCursor,
				"oldest": oldestCursor,
			},
			"has_more": hasMore,
		}
	} else {
		// Simple ID cursors for "latest" mode (existing behavior)
		var newestID, oldestID int64

		if len(submissions) > 0 {
			// For "latest" with cursor param, results are ASC order, so reverse for response
			if params.Cursor > 0 {
				// Reverse the slice to get DESC order (newest first)
				for i, j := 0, len(submissions)-1; i < j; i, j = i+1, j-1 {
					submissions[i], submissions[j] = submissions[j], submissions[i]
				}
			}
			newestID = int64(submissions[0].ID)
			oldestID = int64(submissions[len(submissions)-1].ID)
		}

		response = map[string]interface{}{
			"data": submissions,
			"cursors": map[string]interface{}{
				"newest": newestID,
				"oldest": oldestID,
			},
			"has_more": hasMore,
		}
	}

	w.Header().Set("Content-Type", "application/json")
	encoder := json.NewEncoder(w)
	encoder.SetIndent("", "  ")
	if err := encoder.Encode(response); err != nil {
		http.Error(w, `{"error":"Failed to encode response"}`, http.StatusInternalServerError)
	}
}
