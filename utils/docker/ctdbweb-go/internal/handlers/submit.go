package handlers

import (
	"encoding/json"
	"net/http"

	"github.com/cuetools/ctdbweb/internal/database"
)

// SubmitHandler handles CD submission requests
type SubmitHandler struct {
	db *database.DB
}

// NewSubmitHandler creates a new submit handler
func NewSubmitHandler(db *database.DB) *SubmitHandler {
	return &SubmitHandler{db: db}
}

// ServeHTTP handles the /api/submit endpoint
func (h *SubmitHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	// TODO: Implement CD submission logic
	// This will include:
	// 1. Parse submission data from request body
	// 2. Validate TOC and offsets
	// 3. Insert into submissions2 and submissions tables
	// 4. Upload parity data to S3 (async)
	// 5. Return success/error response

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusNotImplemented)
	json.NewEncoder(w).Encode(map[string]string{
		"error": "Submit endpoint not yet fully implemented",
	})
}
