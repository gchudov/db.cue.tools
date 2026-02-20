package handlers

import (
	"encoding/json"
	"net/http"
	"strconv"

	"github.com/cuetools/ctdbweb/internal/database"
)

// UpdatesHandler handles requests for /api/updates (submission activity ordered by submissions.subid)
type UpdatesHandler struct {
	db *database.DB
}

// NewUpdatesHandler creates a new updates handler
func NewUpdatesHandler(db *database.DB) *UpdatesHandler {
	return &UpdatesHandler{db: db}
}

// ServeHTTP handles the /api/updates endpoint
func (h *UpdatesHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	query := r.URL.Query()

	// Parse limit (default 50, max 1000)
	limit := 50
	if limitStr := query.Get("limit"); limitStr != "" {
		if l, err := strconv.Atoi(limitStr); err == nil && l > 0 && l <= 1000 {
			limit = l
		}
	}

	// Parse cursor parameters
	var cursor, before int64
	if cursorStr := query.Get("cursor"); cursorStr != "" {
		if c, err := strconv.ParseInt(cursorStr, 10, 64); err == nil {
			cursor = c
		}
	}
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

	// Query database
	submissions, subIDs, err := database.GetUpdateSubmissions(h.db.CTDB, limit, cursor, before, filters)
	if err != nil {
		http.Error(w, `{"error":"Failed to fetch updates: `+err.Error()+`"}`, http.StatusInternalServerError)
		return
	}

	// Populate computed fields
	for i := range submissions {
		submissions[i].PopulateComputedFields()
	}

	// Build response with subid-based cursors
	hasMore := len(submissions) >= limit
	var newestSubID, oldestSubID int64

	if len(submissions) > 0 {
		// When fetching newer entries (cursor mode), results are ASC — reverse to DESC
		if cursor > 0 {
			for i, j := 0, len(submissions)-1; i < j; i, j = i+1, j-1 {
				submissions[i], submissions[j] = submissions[j], submissions[i]
				subIDs[i], subIDs[j] = subIDs[j], subIDs[i]
			}
		}
		newestSubID = subIDs[0]
		oldestSubID = subIDs[len(subIDs)-1]
	}

	response := map[string]interface{}{
		"data": submissions,
		"cursors": map[string]interface{}{
			"newest": newestSubID,
			"oldest": oldestSubID,
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
