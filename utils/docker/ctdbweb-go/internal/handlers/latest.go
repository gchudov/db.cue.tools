package handlers

import (
	"encoding/json"
	"net/http"
	"strconv"

	"github.com/cuetools/ctdbweb/internal/database"
)

// LatestHandler handles requests for latest CD submissions
type LatestHandler struct {
	db *database.DB
}

// NewLatestHandler creates a new latest handler
func NewLatestHandler(db *database.DB) *LatestHandler {
	return &LatestHandler{db: db}
}

// ServeHTTP handles the /api/latest endpoint
func (h *LatestHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
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
	submissions, err := database.GetLatestSubmissions(h.db.CTDB, start, limit)
	if err != nil {
		http.Error(w, `{"error":"Failed to fetch latest submissions: `+err.Error()+`"}`, http.StatusInternalServerError)
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

	rows := make([]map[string]interface{}, 0)
	// TODO: Implement proper conversion from submissions to Google Viz format
	// For now, returning empty rows

	return map[string]interface{}{
		"cols": cols,
		"rows": rows,
	}
}
