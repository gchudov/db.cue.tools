package metadata

import (
	"database/sql"
	"fmt"

	"github.com/cuetools/ctdbweb/internal/models"
	"github.com/cuetools/ctdbweb/internal/toc"
)

// FreeDBClient handles queries to the FreeDB database
type FreeDBClient struct {
	db *sql.DB
}

// NewFreeDBClient creates a new FreeDB client
func NewFreeDBClient(db *sql.DB) *FreeDBClient {
	return &FreeDBClient{db: db}
}

// LookupByTOC looks up metadata by TOC string
func (c *FreeDBClient) LookupByTOC(tocString string, fuzzy bool) ([]models.Metadata, error) {
	// Parse TOC and convert to FreeDB offset format
	t, err := toc.ParseTOC(tocString)
	if err != nil {
		return nil, fmt.Errorf("failed to parse TOC: %w", err)
	}

	// Get ALL offsets including leadout for FreeDB matching
	offsets := t.Offsets
	if len(offsets) < 2 {
		return nil, fmt.Errorf("no tracks in TOC")
	}

	// Build PostgreSQL array of offsets (track offsets + 150, leadout converted to time)
	// PHP: for tracks: offset + 150, for leadout: floor(leadout/75 + 2) * 75
	var offsetsArray string
	{
		offsetsArray = "{"
		// Process all offsets except the last one (leadout)
		for i := 0; i < len(offsets)-1; i++ {
			if i > 0 {
				offsetsArray += ","
			}
			// Add 150 for CD-DA standard pregap
			offsetsArray += fmt.Sprintf("%d", offsets[i]+150)
		}

		// Add leadout (last offset converted to time format)
		// PHP: ((floor(abs($ids[count($ids) - 1]) / 75) + 2) * 75)
		leadout := offsets[len(offsets)-1]
		leadoutTime := (leadout/75 + 2) * 75
		offsetsArray += fmt.Sprintf(",%d", leadoutTime)
		offsetsArray += "}"
	}

	var rows *sql.Rows
	if !fuzzy {
		// Exact match
		query := `
			SELECT e.id, e.freedbid, e.category, e.year, e.title, e.extra,
			       an.name as artist, gn.name as genre
			FROM entries e
			LEFT OUTER JOIN artist_names an ON an.id = e.artist
			LEFT OUTER JOIN genre_names gn ON gn.id = e.genre
			WHERE offsets = $1
		`

		rows, err = c.db.Query(query, offsetsArray)
		if err != nil {
			return nil, fmt.Errorf("query failed: %w", err)
		}
	} else {
		// Fuzzy match using cube distance
		query := `
			SELECT e.id, e.freedbid, e.category, e.year, e.title, e.extra,
			       an.name as artist, gn.name as genre,
			       cube_distance(create_cube_from_toc(offsets), create_cube_from_toc($1)) as distance
			FROM entries e
			LEFT OUTER JOIN artist_names an ON an.id = e.artist
			LEFT OUTER JOIN genre_names gn ON gn.id = e.genre
			WHERE create_cube_from_toc(offsets) <@ create_bounding_cube($1, $2)
			AND array_upper(offsets, 1) = $3
			ORDER BY distance
			LIMIT 30
		`

		fuzzyThreshold := 3 // sectors
		trackCount := len(offsets)

		rows, err = c.db.Query(query, offsetsArray, fuzzyThreshold, trackCount)
		if err != nil {
			return nil, fmt.Errorf("query failed: %w", err)
		}
	}
	defer rows.Close()

	var results []models.Metadata
	for rows.Next() {
		var m models.Metadata
		var entryID, freedbID int
		var category, title, extra, artist, genre sql.NullString
		var year sql.NullInt64
		var distance sql.NullFloat64

		if !fuzzy {
			err = rows.Scan(
				&entryID,
				&freedbID,
				&category,
				&year,
				&title,
				&extra,
				&artist,
				&genre,
			)
		} else {
			err = rows.Scan(
				&entryID,
				&freedbID,
				&category,
				&year,
				&title,
				&extra,
				&artist,
				&genre,
				&distance,
			)
		}

		if err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}

		m.Source = "freedb"

		// Format ID as "category/freedbid"
		if category.Valid {
			m.ID = fmt.Sprintf("%s/%08x", category.String, uint32(freedbID))
		} else {
			m.ID = fmt.Sprintf("unknown/%08x", uint32(freedbID))
		}

		if artist.Valid {
			m.ArtistName = artist.String
		}

		if title.Valid {
			m.AlbumName = title.String
		}

		if year.Valid {
			m.Year = int(year.Int64)
		}

		if genre.Valid {
			m.Genre = genre.String
		}

		if extra.Valid {
			m.Extra = extra.String
		}

		// Calculate relevance if fuzzy match
		if fuzzy && distance.Valid {
			// Relevance calculation: exp(-distance/450) * 100
			relevance := int(100.0 * (1.0 / (1.0 + distance.Float64/450.0)))
			m.Relevance = &relevance
		}

		// Fetch tracklist
		tracklist, err := c.fetchTracklist(entryID, len(offsets))
		if err == nil && len(tracklist) > 0 {
			m.Tracklist = tracklist
		}

		results = append(results, m)
	}

	if err = rows.Err(); err != nil {
		return nil, err
	}

	return results, nil
}

// fetchTracklist retrieves track listing for a FreeDB entry
func (c *FreeDBClient) fetchTracklist(entryID int, trackCount int) ([]models.Track, error) {
	query := `
		SELECT t.number, t.title, t.extra, an.name AS artist
		FROM tracks t
		LEFT OUTER JOIN artist_names an ON an.id = t.artist
		WHERE t.id = $1
		ORDER BY t.number
	`

	rows, err := c.db.Query(query, entryID)
	if err != nil {
		return nil, fmt.Errorf("query failed: %w", err)
	}
	defer rows.Close()

	// Initialize tracklist with empty tracks
	tracklist := make([]models.Track, trackCount)

	for rows.Next() {
		var trackNum int
		var title, extra, artist sql.NullString

		if err := rows.Scan(&trackNum, &title, &extra, &artist); err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}

		// Track numbers are 1-indexed in FreeDB
		idx := trackNum - 1
		if idx >= 0 && idx < trackCount {
			if title.Valid {
				tracklist[idx].Name = title.String
			}
			if artist.Valid {
				tracklist[idx].Artist = artist.String
			}
			if extra.Valid {
				tracklist[idx].Extra = extra.String
			}
		}
	}

	if err = rows.Err(); err != nil {
		return nil, err
	}

	return tracklist, nil
}
