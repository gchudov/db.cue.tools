package handlers

import (
	"encoding/json"
	"net/http"

	"github.com/cuetools/ctdbweb/internal/database"
)

// StatsHandler handles requests for statistics
type StatsHandler struct {
	db *database.DB
}

// NewStatsHandler creates a new stats handler
func NewStatsHandler(db *database.DB) *StatsHandler {
	return &StatsHandler{db: db}
}

// ServeHTTP handles the /api/stats endpoint
func (h *StatsHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	// Parse query parameters
	statType := r.URL.Query().Get("type")
	if statType == "" {
		statType = "totals"
	}

	// Query database (pass request for parameter parsing)
	stats, err := database.GetStats(h.db.CTDB, statType, r)
	if err != nil {
		http.Error(w, `{"error":"Failed to fetch stats: `+err.Error()+`"}`, http.StatusInternalServerError)
		return
	}

	// Return JSON response
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	encoder := json.NewEncoder(w)
	encoder.SetIndent("", "  ")
	if err := encoder.Encode(stats); err != nil {
		http.Error(w, `{"error":"Failed to encode response"}`, http.StatusInternalServerError)
	}
}
