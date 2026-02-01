package metadata

import (
	"fmt"
	"sort"
	"sync"

	"github.com/cuetools/ctdbweb/internal/database"
	"github.com/cuetools/ctdbweb/internal/models"
)

// Aggregator coordinates metadata lookups across multiple sources
type Aggregator struct {
	ctdb        *CTDBClient
	musicbrainz *MusicBrainzClient
	discogs     *DiscogsClient
	freedb      *FreeDBClient
}

// NewAggregator creates a new metadata aggregator
func NewAggregator(db *database.DB) *Aggregator {
	return &Aggregator{
		ctdb:        NewCTDBClient(db.CTDB),
		musicbrainz: NewMusicBrainzClient(db.MusicBrainz),
		discogs:     NewDiscogsClient(db.Discogs),
		freedb:      NewFreeDBClient(db.FreeDB),
	}
}

// LookupMode defines the extent of metadata searching
type LookupMode int

const (
	LookupFast      LookupMode = iota // MusicBrainz exact only
	LookupDefault                      // MusicBrainz + Discogs (via MusicBrainz IDs)
	LookupExtensive                    // All sources including fuzzy matches
)

// LookupOptions configures metadata lookup behavior
type LookupOptions struct {
	Mode         LookupMode
	IncludeFuzzy bool
	IncludeCTDB  bool     // controls CTDB database queries
	Sources      []string // e.g., ["musicbrainz", "discogs", "freedb"]
}

// LookupResult contains both CTDB entries and external metadata
type LookupResult struct {
	CTDB     []models.CTDBEntry `json:"ctdb,omitempty"`
	Metadata []models.Metadata  `json:"metadata,omitempty"`
}

// Lookup performs metadata lookup across all configured sources
// Uses priority-based sequential querying with early exit to match PHP behavior
func (a *Aggregator) Lookup(tocString string, opts LookupOptions) (*LookupResult, error) {
	result := &LookupResult{}

	// STEP 1: Query CTDB first and build TOC array (matches PHP behavior - lookup2.php:45-61)
	tocs := []string{tocString} // Start with original TOC

	if opts.IncludeCTDB {
		ctdbEntries, err := a.ctdb.LookupByTOC(tocString, opts.IncludeFuzzy)
		if err != nil {
			fmt.Printf("CTDB lookup error: %v\n", err)
		} else {
			result.CTDB = ctdbEntries

			// If fuzzy matching is enabled and we found CTDB results,
			// extract TOC strings to expand our search (matches PHP - lookup2.php:58-61)
			if opts.IncludeFuzzy && len(ctdbEntries) > 0 {
				const maxFuzzyTOCs = 10 // Limit for performance
				limit := len(ctdbEntries)
				if limit > maxFuzzyTOCs {
					limit = maxFuzzyTOCs
				}

				for i := 0; i < limit; i++ {
					// entry.TOC is already in the correct format (colon-separated with - for data tracks)
					tocs = append(tocs, ctdbEntries[i].TOC)
				}
			}
		}
	}

	// STEP 2: Query metadata sources (existing priority-based logic)
	// Use priority-based configuration
	priorities := GetPriorityConfig(opts.Mode)

	// Group by priority level
	priorityGroups := make(map[int][]SourcePriority)
	maxPriority := 0
	for _, p := range priorities {
		priorityGroups[p.Priority] = append(priorityGroups[p.Priority], p)
		if p.Priority > maxPriority {
			maxPriority = p.Priority
		}
	}

	// Query each priority level sequentially
	for priority := 1; priority <= maxPriority; priority++ {
		sources := priorityGroups[priority]
		if len(sources) == 0 {
			continue
		}

		// Query all sources at this priority level in parallel
		var wg sync.WaitGroup
		results := make(chan []models.Metadata, len(sources))
		errors := make(chan error, len(sources))

		for _, source := range sources {
			wg.Add(1)
			go func(s SourcePriority) {
				defer wg.Done()

				var meta []models.Metadata
				var err error

				switch s.Source {
				case "musicbrainz":
					// Use multi-TOC method if we have fuzzy CTDB matches
					// (matches PHP behavior - lookup2.php:69-71)
					if len(tocs) > 1 {
						meta, err = a.musicbrainz.LookupByTOCs(tocs, s.Fuzzy)
					} else {
						meta, err = a.musicbrainz.LookupByTOC(tocString, s.Fuzzy)
					}
				case "discogs":
					// Discogs always uses single TOC (not affected by fuzzy CTDB expansion)
					meta, err = a.discogs.LookupByTOC(tocString, s.Fuzzy)
				case "freedb":
					// FreeDB always uses single TOC (not affected by fuzzy CTDB expansion)
					meta, err = a.freedb.LookupByTOC(tocString, s.Fuzzy)
				}

				if err != nil {
					errors <- fmt.Errorf("%s lookup failed: %w", s.Source, err)
					return
				}

				if len(meta) > 0 {
					results <- meta
				}
			}(source)
		}

		// Wait for all queries at this priority level
		go func() {
			wg.Wait()
			close(results)
			close(errors)
		}()

		// Collect results from this priority level
		var allMetadata []models.Metadata
		for meta := range results {
			allMetadata = append(allMetadata, meta...)
		}

		// Log errors (non-fatal)
		for err := range errors {
			fmt.Printf("Metadata lookup error: %v\n", err)
		}

		// EARLY EXIT: If we found results at this priority, stop here
		if len(allMetadata) > 0 {
			// Extract Discogs IDs from MusicBrainz results for cross-reference lookup
			// This happens after priority-based queries, matching PHP behavior (line 86-87)
			discogsIDs := extractDiscogsIDs(allMetadata)
			if len(discogsIDs) > 0 {
				discogsMeta, err := a.discogs.LookupByDiscogsIDs(discogsIDs)
				if err == nil {
					allMetadata = append(allMetadata, discogsMeta...)
				}
			}

			sortMetadataByPriority(allMetadata)
			result.Metadata = allMetadata
			return result, nil
		}
	}

	// No results found at any priority level
	result.Metadata = []models.Metadata{}
	return result, nil
}

// extractDiscogsIDs extracts Discogs IDs from MusicBrainz results
// Returns IDs in format "discogs_id/disc_number/relevance"
func extractDiscogsIDs(metadata []models.Metadata) []string {
	var discogsIDs []string
	seen := make(map[string]bool)

	for _, m := range metadata {
		if m.DiscogsID == "" {
			continue
		}

		// Check if this Discogs ID is already represented by a direct Discogs result
		isClone := false
		for _, other := range metadata {
			if other.Source == "discogs" && other.ID == m.DiscogsID {
				isClone = true
				break
			}
		}

		if isClone {
			continue
		}

		// Build ID string
		idStr := m.DiscogsID
		if m.DiscNumber > 0 {
			idStr += fmt.Sprintf("/%d", m.DiscNumber)
		} else {
			idStr += "/"
		}

		if m.Relevance != nil {
			idStr += fmt.Sprintf("/%d", *m.Relevance)
		}

		if !seen[idStr] {
			discogsIDs = append(discogsIDs, idStr)
			seen[idStr] = true
		}
	}

	return discogsIDs
}

// sortMetadataByPriority sorts metadata by source priority and relevance
func sortMetadataByPriority(metadata []models.Metadata) {
	sort.Slice(metadata, func(i, j int) bool {
		return compareMetadata(metadata[i], metadata[j])
	})
}

// compareMetadata implements the priority comparison logic
// Returns true if a should come before b
func compareMetadata(a, b models.Metadata) bool {
	// Source priority: musicbrainz > discogs > cdstub > freedb
	sourceOrder := map[string]int{
		"musicbrainz": 0,
		"discogs":     1,
		"cdstub":      2,
		"freedb":      3,
	}

	aOrder := sourceOrder[a.Source]
	bOrder := sourceOrder[b.Source]

	// Different sources: use source priority
	if aOrder != bOrder {
		return aOrder < bOrder
	}

	// Same source: sort by relevance (higher relevance first)
	aRel := 101
	if a.Relevance != nil {
		aRel = *a.Relevance
	}

	bRel := 101
	if b.Relevance != nil {
		bRel = *b.Relevance
	}

	if aRel != bRel {
		return aRel > bRel // Higher relevance comes first
	}

	// Same source and relevance: sort by release date
	return compareReleaseDate(a, b)
}

// compareReleaseDate compares metadata by first release date
// Returns true if a should come before b (earlier date first)
func compareReleaseDate(a, b models.Metadata) bool {
	aDate := getFirstReleaseDate(a)
	bDate := getFirstReleaseDate(b)

	if aDate == "" && bDate == "" {
		return false
	}
	if aDate == "" {
		return false // Put entries without dates last
	}
	if bDate == "" {
		return true
	}

	return aDate < bDate // Earlier dates first
}

// getFirstReleaseDate extracts the earliest release date from metadata
func getFirstReleaseDate(m models.Metadata) string {
	if len(m.Releases) == 0 {
		return ""
	}

	earliest := ""
	for _, rel := range m.Releases {
		if rel.Date == "" {
			continue
		}

		// Pad partial dates for comparison
		// "2020" -> "2020-12-28"
		// "2020-06" -> "2020-06-28"
		date := rel.Date
		if len(date) == 4 {
			date += "-12-28"
		} else if len(date) == 7 {
			date += "-28"
		}

		if earliest == "" || date < earliest {
			earliest = date
		}
	}

	return earliest
}

// UniqueIDs returns deduplicated list of unique IDs from metadata
func UniqueIDs(metadata []models.Metadata, field string) []string {
	seen := make(map[string]bool)
	var ids []string

	for _, m := range metadata {
		var id string
		switch field {
		case "id":
			id = m.ID
		case "discogs_id":
			id = m.DiscogsID
		default:
			continue
		}

		if id != "" && !seen[id] {
			ids = append(ids, id)
			seen[id] = true
		}
	}

	return ids
}

// Helper function
func contains(slice []string, item string) bool {
	for _, s := range slice {
		if s == item {
			return true
		}
	}
	return false
}
