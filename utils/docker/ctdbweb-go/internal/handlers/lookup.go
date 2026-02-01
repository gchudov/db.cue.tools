package handlers

import (
	"encoding/json"
	"net/http"

	"github.com/cuetools/ctdbweb/internal/database"
	"github.com/cuetools/ctdbweb/internal/metadata"
)

// LookupHandler handles metadata lookup requests
type LookupHandler struct {
	aggregator *metadata.Aggregator
}

// NewLookupHandler creates a new lookup handler
func NewLookupHandler(db *database.DB) *LookupHandler {
	return &LookupHandler{
		aggregator: metadata.NewAggregator(db),
	}
}

// ServeHTTP handles the /api/lookup endpoint
func (h *LookupHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	// Parse query parameters
	toc := r.URL.Query().Get("toc")
	if toc == "" {
		http.Error(w, `{"error":"Missing toc parameter"}`, http.StatusBadRequest)
		return
	}

	metadataMode := r.URL.Query().Get("metadata")
	if metadataMode == "" {
		metadataMode = "default"
	}

	fuzzyParam := r.URL.Query().Get("fuzzy")
	includeFuzzy := fuzzyParam == "1" || fuzzyParam == "true"

	// Parse ctdb parameter (default: enabled)
	ctdbParam := r.URL.Query().Get("ctdb")
	includeCTDB := ctdbParam != "0" && ctdbParam != "false"

	// Determine lookup mode
	var mode metadata.LookupMode
	switch metadataMode {
	case "fast":
		mode = metadata.LookupFast
	case "default":
		mode = metadata.LookupDefault
	case "extensive":
		mode = metadata.LookupExtensive
	default:
		mode = metadata.LookupDefault
	}

	// Perform lookup
	opts := metadata.LookupOptions{
		Mode:         mode,
		IncludeFuzzy: includeFuzzy,
		IncludeCTDB:  includeCTDB,
	}

	results, err := h.aggregator.Lookup(toc, opts)
	if err != nil {
		http.Error(w, `{"error":"Lookup failed: `+err.Error()+`"}`, http.StatusInternalServerError)
		return
	}

	// Return JSON response
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)

	encoder := json.NewEncoder(w)
	encoder.SetIndent("", "  ")
	if err := encoder.Encode(results); err != nil {
		http.Error(w, `{"error":"Failed to encode response"}`, http.StatusInternalServerError)
		return
	}
}
