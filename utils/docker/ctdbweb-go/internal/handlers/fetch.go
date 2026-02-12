package handlers

import (
	"encoding/json"
	"net/http"
	"strconv"

	"github.com/cuetools/ctdbweb/internal/database"
)

// FetchHandler handles requests to fetch a single CD submission by ID
type FetchHandler struct {
	db *database.DB
}

// NewFetchHandler creates a new fetch handler
func NewFetchHandler(db *database.DB) *FetchHandler {
	return &FetchHandler{db: db}
}

// ServeHTTP handles the /api/fetch?id=N endpoint
func (h *FetchHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	idStr := r.URL.Query().Get("id")
	if idStr == "" {
		http.Error(w, `{"error":"Missing required parameter: id"}`, http.StatusBadRequest)
		return
	}

	id, err := strconv.Atoi(idStr)
	if err != nil {
		http.Error(w, `{"error":"Invalid id parameter"}`, http.StatusBadRequest)
		return
	}

	submission, err := database.GetSubmissionByID(h.db.CTDB, id)
	if err != nil {
		http.Error(w, `{"error":"Failed to fetch submission: `+err.Error()+`"}`, http.StatusInternalServerError)
		return
	}

	if submission == nil {
		http.Error(w, `{"error":"Submission not found"}`, http.StatusNotFound)
		return
	}

	submission.PopulateComputedFields()

	w.Header().Set("Content-Type", "application/json")
	encoder := json.NewEncoder(w)
	encoder.SetIndent("", "  ")
	if err := encoder.Encode(submission); err != nil {
		http.Error(w, `{"error":"Failed to encode response"}`, http.StatusInternalServerError)
	}
}
