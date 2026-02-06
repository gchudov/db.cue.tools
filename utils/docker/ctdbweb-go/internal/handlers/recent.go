package handlers

import (
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"

	"github.com/cuetools/ctdbweb/internal/database"
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
}
