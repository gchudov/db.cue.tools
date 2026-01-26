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
	musicbrainz *MusicBrainzClient
	discogs     *DiscogsClient
	freedb      *FreeDBClient
}

// NewAggregator creates a new metadata aggregator
func NewAggregator(db *database.DB) *Aggregator {
	return &Aggregator{
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
	Sources      []string // e.g., ["musicbrainz", "discogs", "freedb"]
}

// Lookup performs metadata lookup across all configured sources
func (a *Aggregator) Lookup(tocString string, opts LookupOptions) ([]models.Metadata, error) {
	var wg sync.WaitGroup
	results := make(chan []models.Metadata, 3)
	errors := make(chan error, 3)

	// Determine which sources to query
	queryMB := opts.Mode != 0 || len(opts.Sources) == 0
	queryDiscogs := opts.Mode == LookupDefault || opts.Mode == LookupExtensive
	queryFreeDB := opts.Mode == LookupExtensive

	// Allow explicit source selection
	if len(opts.Sources) > 0 {
		queryMB = contains(opts.Sources, "musicbrainz")
		queryDiscogs = contains(opts.Sources, "discogs")
		queryFreeDB = contains(opts.Sources, "freedb")
	}

	// Query MusicBrainz
	if queryMB {
		wg.Add(1)
		go func() {
			defer wg.Done()
			meta, err := a.musicbrainz.LookupByTOC(tocString, opts.IncludeFuzzy)
			if err != nil {
				errors <- fmt.Errorf("musicbrainz lookup failed: %w", err)
				return
			}
			results <- meta
		}()
	}

	// Query FreeDB if extensive mode
	if queryFreeDB {
		wg.Add(1)
		go func() {
			defer wg.Done()
			meta, err := a.freedb.LookupByTOC(tocString, opts.IncludeFuzzy)
			if err != nil {
				errors <- fmt.Errorf("freedb lookup failed: %w", err)
				return
			}
			results <- meta
		}()
	}

	// Wait for initial queries to complete
	go func() {
		wg.Wait()
		close(results)
		close(errors)
	}()

	// Collect results
	var allMetadata []models.Metadata
	for meta := range results {
		allMetadata = append(allMetadata, meta...)
	}

	// Check for errors (non-fatal, log them)
	for err := range errors {
		// Log error but don't fail the entire request
		fmt.Printf("Metadata lookup error: %v\n", err)
	}

	// If we have MusicBrainz results with Discogs IDs, query Discogs
	if queryDiscogs && len(allMetadata) > 0 {
		discogsIDs := extractDiscogsIDs(allMetadata)
		if len(discogsIDs) > 0 {
			discogsMeta, err := a.discogs.LookupByDiscogsIDs(discogsIDs)
			if err == nil {
				allMetadata = append(allMetadata, discogsMeta...)
			}
		}
	}

	// If extensive mode and we want fuzzy Discogs lookups
	if opts.Mode == LookupExtensive && opts.IncludeFuzzy {
		discogsMeta, err := a.discogs.LookupByTOC(tocString, true)
		if err == nil {
			allMetadata = append(allMetadata, discogsMeta...)
		}
	}

	// Sort results by priority
	sortMetadataByPriority(allMetadata)

	return allMetadata, nil
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
