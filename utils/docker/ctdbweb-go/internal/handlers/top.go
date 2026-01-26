package handlers

import (
	"encoding/json"
	"net/http"
	"strconv"

	"github.com/cuetools/ctdbweb/internal/database"
)

// TopHandler handles requests for top/popular CD submissions
type TopHandler struct {
	db *database.DB
}

// NewTopHandler creates a new top handler
func NewTopHandler(db *database.DB) *TopHandler {
	return &TopHandler{db: db}
}

// ServeHTTP handles the /api/top endpoint
func (h *TopHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
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

	// Query database
	submissions, err := database.GetTopSubmissions(h.db.CTDB, start, limit)
	if err != nil {
		http.Error(w, `{"error":"Failed to fetch top submissions: `+err.Error()+`"}`, http.StatusInternalServerError)
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
