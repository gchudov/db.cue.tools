package metadata

import (
	"database/sql"
	"fmt"

	"github.com/cuetools/ctdbweb/internal/models"
	"github.com/cuetools/ctdbweb/internal/toc"
	"github.com/cuetools/ctdbweb/pkg/pgarray"
)

// MusicBrainzClient handles queries to the MusicBrainz database
type MusicBrainzClient struct {
	db *sql.DB
}

// NewMusicBrainzClient creates a new MusicBrainz client
func NewMusicBrainzClient(db *sql.DB) *MusicBrainzClient {
	return &MusicBrainzClient{db: db}
}

// LookupByTOC looks up metadata by TOC string
func (c *MusicBrainzClient) LookupByTOC(tocString string, fuzzy bool) ([]models.Metadata, error) {
	// Set search path
	if _, err := c.db.Exec("SET search_path TO musicbrainz,public"); err != nil {
		return nil, fmt.Errorf("failed to set search path: %w", err)
	}

	// Get medium IDs
	mediumIDs, err := c.lookupMediumIDs(tocString, fuzzy)
	if err != nil {
		return nil, fmt.Errorf("failed to lookup medium IDs: %w", err)
	}

	if len(mediumIDs) == 0 {
		return []models.Metadata{}, nil
	}

	// Fetch full metadata for each medium
	return c.fetchMetadata(mediumIDs)
}

// mediumID represents a MusicBrainz medium ID with optional distance for fuzzy matches
type mediumID struct {
	ID       int
	Distance *float64
}

// lookupMediumIDs finds MusicBrainz medium IDs by TOC
func (c *MusicBrainzClient) lookupMediumIDs(tocString string, fuzzy bool) ([]mediumID, error) {
	if fuzzy {
		return c.fuzzyLookupMediumIDs(tocString)
	}
	return c.exactLookupMediumIDs(tocString)
}

// exactLookupMediumIDs performs exact TOC matching via disc ID
func (c *MusicBrainzClient) exactLookupMediumIDs(tocString string) ([]mediumID, error) {
	// Convert TOC to MusicBrainz disc ID
	discID, err := toc.TOCsToMBDiscID(tocString)
	if err != nil {
		return nil, fmt.Errorf("failed to convert TOC to MusicBrainz disc ID: %w", err)
	}

	query := `
		SELECT DISTINCT mc.medium AS id
		FROM cdtoc c
		INNER JOIN medium_cdtoc mc ON mc.cdtoc = c.id
		WHERE c.discid = $1
	`

	rows, err := c.db.Query(query, discID)
	if err != nil {
		return nil, fmt.Errorf("query failed: %w", err)
	}
	defer rows.Close()

	var ids []mediumID
	for rows.Next() {
		var id mediumID
		if err := rows.Scan(&id.ID); err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}
		ids = append(ids, id)
	}

	return ids, rows.Err()
}

// fuzzyLookupMediumIDs performs fuzzy TOC matching using cube distance
func (c *MusicBrainzClient) fuzzyLookupMediumIDs(tocString string) ([]mediumID, error) {
	// Parse TOC and calculate durations
	t, err := toc.ParseTOC(tocString)
	if err != nil {
		return nil, fmt.Errorf("failed to parse TOC: %w", err)
	}

	durations := t.GetDurationsInMilliseconds()
	if len(durations) == 0 {
		return nil, fmt.Errorf("no audio tracks in TOC")
	}

	// Convert durations to PostgreSQL array format
	durationsArray := arrayToPostgresString(durations)

	query := `
		SELECT
			cube_distance(mi.toc::cube, create_cube_from_durations($1)) AS distance,
			m.id as id
		FROM medium_index mi
		JOIN medium m ON m.id = mi.medium
		WHERE mi.toc::cube <@ create_bounding_cube($1, 3000)
		AND m.track_count = array_upper($1, 1)
		AND (m.format = 1 OR m.format = 12 OR m.format IS NULL)
		ORDER BY distance
		LIMIT 30
	`

	rows, err := c.db.Query(query, durationsArray)
	if err != nil {
		return nil, fmt.Errorf("query failed: %w", err)
	}
	defer rows.Close()

	var ids []mediumID
	for rows.Next() {
		var id mediumID
		var distance float64
		if err := rows.Scan(&distance, &id.ID); err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}
		id.Distance = &distance
		ids = append(ids, id)
	}

	return ids, rows.Err()
}

// fetchMetadata retrieves full metadata for given medium IDs
func (c *MusicBrainzClient) fetchMetadata(mediumIDs []mediumID) ([]models.Metadata, error) {
	if len(mediumIDs) == 0 {
		return []models.Metadata{}, nil
	}

	// Extract just the IDs for queries
	ids := make([]int, len(mediumIDs))
	for i, mid := range mediumIDs {
		ids[i] = mid.ID
	}

	// Build parameterized query placeholders
	placeholders := buildPlaceholders(len(ids))

	query := fmt.Sprintf(`
		SELECT
			m.id AS mediumid,
			rgm.first_release_date_year,
			rm.info_url,
			r.gid as id,
			r.artist_credit,
			r.name as albumname,
			m.position as discnumber,
			m.name as discname,
			(SELECT COUNT(*) FROM medium WHERE release = r.id) as totaldiscs,
			(SELECT MIN(SUBSTRING(u.url,32)) FROM l_release_url rurl
			 INNER JOIN url u ON rurl.entity1 = u.id
			 WHERE rurl.entity0 = r.id AND u.url ILIKE 'http://www.discogs.com/release/%%') as discogs_id,
			(SELECT array_agg(rl.catalog_number) FROM release_label rl WHERE rl.release = r.id) as catno,
			(SELECT array_agg(l.name) FROM release_label rl
			 LEFT JOIN label l ON l.id = rl.label WHERE rl.release = r.id) as label,
			r.barcode
		FROM medium m
		INNER JOIN release r ON r.id = m.release
		LEFT OUTER JOIN release_meta rm ON rm.id = r.id
		LEFT OUTER JOIN release_group_meta rgm ON rgm.id = r.release_group
		WHERE m.id IN (%s)
	`, placeholders)

	// Convert ids to interface{} slice for query args
	args := make([]interface{}, len(ids))
	for i, id := range ids {
		args[i] = id
	}

	rows, err := c.db.Query(query, args...)
	if err != nil {
		return nil, fmt.Errorf("query failed: %w", err)
	}
	defer rows.Close()

	var results []models.Metadata
	for rows.Next() {
		var m models.Metadata
		var infoURL, discName, discogsID, catno, label, barcode sql.NullString
		var year sql.NullInt64
		var artistCredit int
		var mediumID int

		err := rows.Scan(
			&mediumID,
			&year,
			&infoURL,
			&m.ID,
			&artistCredit,
			&m.AlbumName,
			&m.DiscNumber,
			&discName,
			&m.TotalDiscs,
			&discogsID,
			&catno,
			&label,
			&barcode,
		)
		if err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}

		m.Source = "musicbrainz"
		if year.Valid {
			m.Year = int(year.Int64)
		}
		if infoURL.Valid {
			m.InfoURL = infoURL.String
		}
		if discName.Valid {
			m.DiscName = discName.String
		}
		if discogsID.Valid {
			m.DiscogsID = discogsID.String
		}
		if barcode.Valid {
			m.Barcode = barcode.String
		}

		// Calculate relevance from distance
		for _, mid := range mediumIDs {
			if mid.ID == mediumID {
				if mid.Distance != nil {
					relevance := calculateRelevance(*mid.Distance, 6000)
					m.Relevance = &relevance
				}
				break
			}
		}

		// Fetch artist name
		artistName, err := c.fetchArtistCredit(artistCredit)
		if err == nil {
			m.ArtistName = artistName
		}

		// Fetch tracklist
		tracklist, err := c.fetchTracklist(mediumID)
		if err == nil {
			m.Tracklist = tracklist
		}

		// Fetch release dates
		releases, err := c.fetchReleases(mediumID)
		if err == nil {
			m.Releases = releases
		}

		// Fetch labels
		if catno.Valid && label.Valid {
			labels, err := c.parseLabels(catno.String, label.String)
			if err == nil {
				m.Labels = labels
			}
		}

		// Fetch cover art
		coverArt, err := c.fetchCoverArt(mediumID, m.ID)
		if err == nil && len(coverArt) > 0 {
			m.CoverArt = coverArt
		}

		results = append(results, m)
	}

	return results, rows.Err()
}

// fetchArtistCredit retrieves artist name by artist credit ID
func (c *MusicBrainzClient) fetchArtistCredit(artistCredit int) (string, error) {
	query := `
		SELECT ac.name
		FROM artist_credit ac
		WHERE ac.id = $1
	`

	var name string
	err := c.db.QueryRow(query, artistCredit).Scan(&name)
	if err != nil {
		return "", fmt.Errorf("failed to fetch artist credit: %w", err)
	}

	return name, nil
}

// fetchTracklist retrieves track listing for a medium
func (c *MusicBrainzClient) fetchTracklist(mediumID int) ([]models.Track, error) {
	query := `
		SELECT t.artist_credit, t.name
		FROM track t
		WHERE t.medium = $1
		ORDER BY t.position
	`

	rows, err := c.db.Query(query, mediumID)
	if err != nil {
		return nil, fmt.Errorf("query failed: %w", err)
	}
	defer rows.Close()

	var tracks []models.Track
	var artistCredits []int

	for rows.Next() {
		var track models.Track
		var artistCredit int
		if err := rows.Scan(&artistCredit, &track.Name); err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}
		tracks = append(tracks, track)
		artistCredits = append(artistCredits, artistCredit)
	}

	if err := rows.Err(); err != nil {
		return nil, err
	}

	// Fetch artist names for tracks
	if len(artistCredits) > 0 {
		artistNames, err := c.fetchArtistCredits(artistCredits)
		if err == nil {
			for i, name := range artistNames {
				if i < len(tracks) {
					tracks[i].Artist = name
				}
			}
		}
	}

	return tracks, nil
}

// fetchArtistCredits retrieves multiple artist names by artist credit IDs
func (c *MusicBrainzClient) fetchArtistCredits(artistCredits []int) ([]string, error) {
	if len(artistCredits) == 0 {
		return []string{}, nil
	}

	// Remove duplicates
	uniqueCredits := make(map[int]bool)
	for _, ac := range artistCredits {
		uniqueCredits[ac] = true
	}

	var credits []int
	for ac := range uniqueCredits {
		credits = append(credits, ac)
	}

	placeholders := buildPlaceholders(len(credits))
	query := fmt.Sprintf(`
		SELECT ac.id, ac.name
		FROM artist_credit ac
		WHERE ac.id IN (%s)
	`, placeholders)

	args := make([]interface{}, len(credits))
	for i, ac := range credits {
		args[i] = ac
	}

	rows, err := c.db.Query(query, args...)
	if err != nil {
		return nil, fmt.Errorf("query failed: %w", err)
	}
	defer rows.Close()

	creditMap := make(map[int]string)
	for rows.Next() {
		var id int
		var name string
		if err := rows.Scan(&id, &name); err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}
		creditMap[id] = name
	}

	// Map back to original order
	names := make([]string, len(artistCredits))
	for i, ac := range artistCredits {
		names[i] = creditMap[ac]
	}

	return names, rows.Err()
}

// fetchReleases retrieves release information for a medium
func (c *MusicBrainzClient) fetchReleases(mediumID int) ([]models.Release, error) {
	query := `
		SELECT m.id, ruc.date_year, ruc.date_month, ruc.date_day, NULL as country
		FROM medium m
		INNER JOIN release_unknown_country ruc ON m.release=ruc.release
		WHERE m.id = $1
		UNION
		SELECT m.id, rc.date_year, rc.date_month, rc.date_day, iso.code AS country
		FROM medium m
		INNER JOIN release_country rc ON m.release=rc.release
		INNER JOIN iso_3166_1 iso ON rc.country=iso.area
		WHERE m.id = $1
	`

	rows, err := c.db.Query(query, mediumID)
	if err != nil {
		return nil, fmt.Errorf("query failed: %w", err)
	}
	defer rows.Close()

	var releases []models.Release
	for rows.Next() {
		var id int
		var year, month, day sql.NullInt64
		var country sql.NullString

		if err := rows.Scan(&id, &year, &month, &day, &country); err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}

		var dateStr string
		if year.Valid {
			dateStr = fmt.Sprintf("%04d", year.Int64)
			if month.Valid && month.Int64 > 0 {
				dateStr += fmt.Sprintf("-%02d", month.Int64)
				if day.Valid && day.Int64 > 0 {
					dateStr += fmt.Sprintf("-%02d", day.Int64)
				}
			}
		}

		release := models.Release{
			Date: dateStr,
		}
		if country.Valid {
			release.Country = country.String
		}

		releases = append(releases, release)
	}

	return releases, rows.Err()
}

// fetchCoverArt retrieves cover art information for a release
func (c *MusicBrainzClient) fetchCoverArt(mediumID int, releaseGID string) ([]models.CoverArt, error) {
	query := `
		SELECT m.id as mediumid, ca.ordering, ca.id, cat.type_id
		FROM musicbrainz.medium m
		INNER JOIN cover_art_archive.cover_art ca ON m.release=ca.release
		JOIN cover_art_archive.cover_art_type cat ON ca.id=cat.id
		WHERE m.id = $1
	`

	rows, err := c.db.Query(query, mediumID)
	if err != nil {
		return nil, fmt.Errorf("query failed: %w", err)
	}
	defer rows.Close()

	var coverArt []models.CoverArt
	seenIDs := make(map[int64]bool)

	for rows.Next() {
		var mid int
		var ordering, id, typeID int64

		if err := rows.Scan(&mid, &ordering, &id, &typeID); err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}

		if seenIDs[id] {
			continue
		}
		seenIDs[id] = true

		art := models.CoverArt{
			URI:     fmt.Sprintf("http://coverartarchive.org/release/%s/%d.jpg", releaseGID, id),
			URI150:  fmt.Sprintf("http://coverartarchive.org/release/%s/%d-250.jpg", releaseGID, id),
			Primary: typeID == 1,
		}

		coverArt = append(coverArt, art)
	}

	return coverArt, rows.Err()
}

// parseLabels parses PostgreSQL arrays of catalog numbers and labels
func (c *MusicBrainzClient) parseLabels(catnoStr, labelStr string) ([]models.Label, error) {
	catnos, err := pgarray.Parse(catnoStr)
	if err != nil {
		return nil, err
	}

	labels, err := pgarray.Parse(labelStr)
	if err != nil {
		return nil, err
	}

	var result []models.Label
	for i := 0; i < len(catnos) && i < len(labels); i++ {
		label := models.Label{}
		if catno, ok := catnos[i].(string); ok {
			label.Catno = catno
		}
		if name, ok := labels[i].(string); ok {
			label.Name = name
		}
		result = append(result, label)
	}

	return result, nil
}

// Helper functions

func buildPlaceholders(count int) string {
	if count == 0 {
		return ""
	}
	placeholders := "$1"
	for i := 2; i <= count; i++ {
		placeholders += fmt.Sprintf(",$%d", i)
	}
	return placeholders
}

func arrayToPostgresString(arr []int) string {
	if len(arr) == 0 {
		return "{}"
	}
	result := "{"
	for i, val := range arr {
		if i > 0 {
			result += ","
		}
		result += fmt.Sprintf("%d", val)
	}
	result += "}"
	return result
}

func calculateRelevance(distance float64, scale float64) int {
	// Relevance calculation: exp(-distance/scale) * 100
	// Returns 0-100, or nil if relevance is > 100
	relevance := int(100.0 * (1.0 / (1.0 + distance/scale)))
	if relevance > 100 {
		return 0
	}
	return relevance
}
