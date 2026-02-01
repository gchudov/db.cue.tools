package handlers

import (
	"encoding/json"
	"encoding/xml"
	"net/http"

	"github.com/cuetools/ctdbweb/internal/database"
	"github.com/cuetools/ctdbweb/internal/metadata"
	"github.com/cuetools/ctdbweb/internal/models"
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

	// Parse format parameter (default: "json")
	format := r.URL.Query().Get("format")
	if format == "" {
		format = "json"
	}

	// Handle XML format
	if format == "xml" {
		// Return 404 if no results in XML mode (matches legacy PHP behavior)
		if len(results.CTDB) == 0 && len(results.Metadata) == 0 {
			http.Error(w, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<error>No results found</error>", http.StatusNotFound)
			return
		}

		// Convert to XML response
		xmlResponse := models.ToXMLResponse(results.CTDB, results.Metadata)

		// Set content type and encode with single-space indentation
		w.Header().Set("Content-Type", "text/xml; charset=UTF-8")
		w.WriteHeader(http.StatusOK)

		// Write XML declaration
		w.Write([]byte(xml.Header))

		// Marshal with single-space indentation
		encoder := xml.NewEncoder(w)
		encoder.Indent("", " ")
		if err := encoder.Encode(xmlResponse); err != nil {
			http.Error(w, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<error>Failed to encode response</error>", http.StatusInternalServerError)
			return
		}
		return
	}

	// Handle JSON format (default)
	if format == "json" {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusOK)

		encoder := json.NewEncoder(w)
		encoder.SetIndent("", "  ")
		if err := encoder.Encode(results); err != nil {
			http.Error(w, `{"error":"Failed to encode response"}`, http.StatusInternalServerError)
			return
		}
		return
	}

	// Invalid format
	http.Error(w, `{"error":"Invalid format parameter. Use 'json' or 'xml'"}`, http.StatusBadRequest)
}
