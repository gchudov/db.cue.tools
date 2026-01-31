package metadata

// SourcePriority defines a metadata source with its priority level
type SourcePriority struct {
	Source   string
	Fuzzy    bool
	Priority int
}

// GetPriorityConfig returns the priority configuration for a given lookup mode
// This matches the PHP lookup2.php priority-based query logic
func GetPriorityConfig(mode LookupMode) []SourcePriority {
	switch mode {
	case LookupFast:
		// Fast mode: Only MusicBrainz exact match
		return []SourcePriority{
			{Source: "musicbrainz", Fuzzy: false, Priority: 1},
		}
	case LookupDefault:
		// Default mode: Matches PHP metadata=default
		// Priority 1: MusicBrainz exact only
		// Priority 2: MusicBrainz fuzzy, Discogs fuzzy, FreeDB exact
		// Priority 4: FreeDB fuzzy
		// Note: Discogs cross-reference by MusicBrainz IDs happens after priority loop (in aggregator)
		return []SourcePriority{
			{Source: "musicbrainz", Fuzzy: false, Priority: 1},
			{Source: "musicbrainz", Fuzzy: true, Priority: 2},
			{Source: "discogs", Fuzzy: true, Priority: 2},
			{Source: "freedb", Fuzzy: false, Priority: 2},
			{Source: "freedb", Fuzzy: true, Priority: 4},
		}
	case LookupExtensive:
		// Extensive mode: All sources at priority 1 (parallel, no early exit)
		return []SourcePriority{
			{Source: "musicbrainz", Fuzzy: false, Priority: 1},
			{Source: "musicbrainz", Fuzzy: true, Priority: 1},
			{Source: "discogs", Fuzzy: true, Priority: 1},
			{Source: "freedb", Fuzzy: true, Priority: 1},
		}
	default:
		return []SourcePriority{}
	}
}
