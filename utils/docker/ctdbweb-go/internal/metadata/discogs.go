package metadata

import (
	"database/sql"
	"fmt"
	"math"
	"strings"

	"github.com/cuetools/ctdbweb/internal/models"
	"github.com/cuetools/ctdbweb/internal/toc"
)

// DiscogsClient handles queries to the Discogs database
type DiscogsClient struct {
	db *sql.DB
}

// NewDiscogsClient creates a new Discogs client
func NewDiscogsClient(db *sql.DB) *DiscogsClient {
	return &DiscogsClient{db: db}
}

// LookupByTOC looks up metadata by TOC string
func (c *DiscogsClient) LookupByTOC(tocString string, fuzzy bool) ([]models.Metadata, error) {
	if fuzzy {
		return c.fuzzyLookup(tocString)
	}
	return []models.Metadata{}, nil // Exact TOC lookup not typically used for Discogs
}

// LookupByDiscogsIDs looks up metadata by Discogs release IDs
// Format: "discogs_id/disc_number/relevance"
func (c *DiscogsClient) LookupByDiscogsIDs(discogsIDs []string) ([]models.Metadata, error) {
	if len(discogsIDs) == 0 {
		return []models.Metadata{}, nil
	}

	// Parse Discogs IDs and extract disc numbers
	type discogsRef struct {
		ID        int
		DiscNo    int
		Relevance *int
	}

	var refs []discogsRef
	var ids []int

	for _, did := range discogsIDs {
		parts := strings.Split(did, "/")
		if len(parts) < 1 {
			continue
		}

		var ref discogsRef
		fmt.Sscanf(parts[0], "%d", &ref.ID)
		if len(parts) > 1 && parts[1] != "" {
			fmt.Sscanf(parts[1], "%d", &ref.DiscNo)
		}
		if len(parts) > 2 && parts[2] != "" {
			var rel int
			fmt.Sscanf(parts[2], "%d", &rel)
			ref.Relevance = &rel
		}

		refs = append(refs, ref)
		ids = append(ids, ref.ID)
	}

	if len(ids) == 0 {
		return []models.Metadata{}, nil
	}

	return c.fetchMetadata(ids, refs)
}

// fuzzyLookup performs fuzzy TOC matching using cube distance
func (c *DiscogsClient) fuzzyLookup(tocString string) ([]models.Metadata, error) {
	// Parse TOC and calculate track durations
	t, err := toc.ParseTOC(tocString)
	if err != nil {
		return nil, fmt.Errorf("failed to parse TOC: %w", err)
	}

	// Get ALL offsets including leadout (needed for last track duration)
	offsets := t.Offsets
	if len(offsets) < 2 {
		return nil, fmt.Errorf("insufficient tracks in TOC")
	}

	// Calculate durations in seconds (with rounding to match PHP behavior)
	// PHP: round((abs($toff[$tr]) - abs($toff[$tr-1])) / 75)
	var durations []int
	for i := 1; i < len(offsets); i++ {
		// Use rounding instead of truncation to match PHP's round() function
		duration := int(float64(offsets[i]-offsets[i-1])/75.0 + 0.5)
		durations = append(durations, duration)
	}

	durationsArray := arrayToPostgresString(durations)
	trackCount := len(durations)

	query := `
		SELECT
			cube_distance(create_cube_from_toc(t.duration), create_cube_from_toc($1)) as distance,
			t.disc,
			r.discogs_id,
			r.title,
			r.country,
			r.released,
			r.artist_credit,
			r.notes,
			(SELECT MAX(rf.qty) FROM releases_formats rf
			 WHERE rf.release_id = r.discogs_id AND rf.format_name = 'CD') as totaldiscs,
			(SELECT MIN(SUBSTRING(rr.released,1,4)::integer) FROM release rr
			 WHERE r.master_id != 0 AND rr.master_id = r.master_id AND rr.released ~ '^\d{4}') as year
		FROM toc t
		INNER JOIN release r ON r.discogs_id = t.discogs_id
		WHERE create_cube_from_toc(t.duration) <@ create_bounding_cube($1,3)
		AND array_upper(t.duration, 1) = $2
		ORDER BY distance
		LIMIT 30
	`

	rows, err := c.db.Query(query, durationsArray, trackCount)
	if err != nil {
		return nil, fmt.Errorf("query failed: %w", err)
	}
	defer rows.Close()

	type releaseRef struct {
		ID        int
		DiscNo    int
		Distance  float64
		Relevance int
	}

	var refs []releaseRef
	var ids []int

	for rows.Next() {
		var ref releaseRef
		var discNo sql.NullInt64
		var title, country, released, notes sql.NullString
		var artistCredit, totalDiscs, year sql.NullInt64

		err := rows.Scan(
			&ref.Distance,
			&discNo,
			&ref.ID,
			&title,
			&country,
			&released,
			&artistCredit,
			&notes,
			&totalDiscs,
			&year,
		)
		if err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}

		if discNo.Valid {
			ref.DiscNo = int(discNo.Int64)
		}

		// Calculate relevance: exp(-distance/6) * 100 (matches PHP)
		ref.Relevance = int(math.Exp(-ref.Distance/6.0) * 100)

		refs = append(refs, releaseRef{
			ID:        ref.ID,
			DiscNo:    ref.DiscNo,
			Relevance: ref.Relevance,
		})
		ids = append(ids, ref.ID)
	}

	if err := rows.Err(); err != nil {
		return nil, err
	}

	// Convert to the format expected by fetchMetadata
	var discogsRefs []struct {
		ID        int
		DiscNo    int
		Relevance *int
	}

	for _, ref := range refs {
		rel := ref.Relevance
		discogsRefs = append(discogsRefs, struct {
			ID        int
			DiscNo    int
			Relevance *int
		}{
			ID:        ref.ID,
			DiscNo:    ref.DiscNo,
			Relevance: &rel,
		})
	}

	return c.fetchMetadata(ids, discogsRefs)
}

// fetchMetadata retrieves full metadata for given Discogs release IDs
func (c *DiscogsClient) fetchMetadata(ids []int, refs interface{}) ([]models.Metadata, error) {
	if len(ids) == 0 {
		return []models.Metadata{}, nil
	}

	placeholders := buildPlaceholders(len(ids))
	query := fmt.Sprintf(`
		SELECT
			r.discogs_id,
			r.title,
			r.country,
			r.released,
			r.artist_credit,
			r.notes,
			(SELECT MAX(rf.qty) FROM releases_formats rf
			 WHERE rf.release_id = r.discogs_id AND rf.format_name = 'CD') as totaldiscs,
			(SELECT MIN(SUBSTRING(rr.released,1,4)::integer) FROM release rr
			 WHERE r.master_id != 0 AND rr.master_id = r.master_id AND rr.released ~ '^\d{4}') as year
		FROM release r
		WHERE r.discogs_id IN (%s)
	`, placeholders)

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
		var country, released, notes sql.NullString
		var artistCredit, totalDiscs, year sql.NullInt64
		var discogsID int

		err := rows.Scan(
			&discogsID,
			&m.AlbumName,
			&country,
			&released,
			&artistCredit,
			&notes,
			&totalDiscs,
			&year,
		)
		if err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}

		m.Source = "discogs"
		m.ID = fmt.Sprintf("%d", discogsID)

		if year.Valid {
			m.Year = int(year.Int64)
		} else if released.Valid && len(released.String) >= 4 {
			fmt.Sscanf(released.String[:4], "%d", &m.Year)
		}

		if totalDiscs.Valid {
			m.TotalDiscs = int(totalDiscs.Int64)
		}

		if notes.Valid {
			m.Extra = notes.String
		}

		// Set disc number and relevance from refs
		switch r := refs.(type) {
		case []struct {
			ID        int
			DiscNo    int
			Relevance *int
		}:
			for _, ref := range r {
				if ref.ID == discogsID {
					m.DiscNumber = ref.DiscNo
					m.Relevance = ref.Relevance
					break
				}
			}
		}

		// Fetch artist name
		if artistCredit.Valid {
			artistName, err := c.fetchArtistCredit(int(artistCredit.Int64))
			if err == nil {
				m.ArtistName = artistName
			}
		}

		// Fetch labels
		labels, err := c.fetchLabels(discogsID)
		if err == nil && len(labels) > 0 {
			m.Labels = labels
		}

		// Fetch tracklist
		tracklist, err := c.fetchTracklist(discogsID, m.DiscNumber)
		if err == nil && len(tracklist) > 0 {
			m.Tracklist = tracklist
		}

		// Fetch barcode
		barcode, err := c.fetchBarcode(discogsID)
		if err == nil && barcode != "" {
			m.Barcode = barcode
		}

		// Fetch cover art
		coverArt, err := c.fetchCoverArt(discogsID)
		if err == nil && len(coverArt) > 0 {
			m.CoverArt = coverArt
		}

		// Fetch videos
		videos, err := c.fetchVideos(discogsID)
		if err == nil && len(videos) > 0 {
			m.Videos = videos
		}

		// Fetch release information
		if released.Valid && country.Valid {
			m.Releases = []models.Release{
				{
					Country: countryToISO(country.String),
					Date:    released.String,
				},
			}
		}

		results = append(results, m)
	}

	return results, rows.Err()
}

// fetchArtistCredit retrieves artist name by artist credit ID
func (c *DiscogsClient) fetchArtistCredit(artistCredit int) (string, error) {
	query := `
		SELECT an.name
		FROM artist_credit ac
		INNER JOIN artist_name an ON an.id = ac.name
		WHERE ac.id = $1
	`

	var name string
	err := c.db.QueryRow(query, artistCredit).Scan(&name)
	if err != nil {
		return "", fmt.Errorf("failed to fetch artist credit: %w", err)
	}

	return name, nil
}

// fetchLabels retrieves label information for a release
func (c *DiscogsClient) fetchLabels(releaseID int) ([]models.Label, error) {
	query := `
		SELECT rl.catno, l.name
		FROM releases_labels rl
		INNER JOIN label l ON l.id = rl.label_id
		WHERE rl.release_id = $1
	`

	rows, err := c.db.Query(query, releaseID)
	if err != nil {
		return nil, fmt.Errorf("query failed: %w", err)
	}
	defer rows.Close()

	var labels []models.Label
	for rows.Next() {
		var label models.Label
		if err := rows.Scan(&label.Catno, &label.Name); err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}
		labels = append(labels, label)
	}

	return labels, rows.Err()
}

// fetchTracklist retrieves track listing for a release
func (c *DiscogsClient) fetchTracklist(releaseID, discNo int) ([]models.Track, error) {
	query := `
		SELECT t.artist_credit, tt.name
		FROM track t
		LEFT OUTER JOIN track_title tt ON t.title = tt.id
		WHERE t.release_id = $1
		AND t.discno IS NOT NULL AND t.trno IS NOT NULL
	`

	if discNo > 0 {
		query += fmt.Sprintf(" AND t.discno = %d", discNo)
	}

	query += " ORDER BY t.index"

	rows, err := c.db.Query(query, releaseID)
	if err != nil {
		return nil, fmt.Errorf("query failed: %w", err)
	}
	defer rows.Close()

	var tracks []models.Track
	var artistCredits []int

	for rows.Next() {
		var track models.Track
		var artistCredit sql.NullInt64
		var name sql.NullString

		if err := rows.Scan(&artistCredit, &name); err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}

		if name.Valid {
			track.Name = name.String
		}

		tracks = append(tracks, track)

		if artistCredit.Valid {
			artistCredits = append(artistCredits, int(artistCredit.Int64))
		} else {
			artistCredits = append(artistCredits, 0)
		}
	}

	// Fetch artist names
	if len(artistCredits) > 0 {
		for i, ac := range artistCredits {
			if ac > 0 {
				artistName, err := c.fetchArtistCredit(ac)
				if err == nil {
					tracks[i].Artist = artistName
				}
			}
		}
	}

	return tracks, rows.Err()
}

// fetchBarcode retrieves barcode for a release
func (c *DiscogsClient) fetchBarcode(releaseID int) (string, error) {
	query := `
		SELECT id_value
		FROM releases_identifiers
		WHERE release_id = $1 AND id_type = 'Barcode'::idtype_t
		LIMIT 1
	`

	var barcode string
	err := c.db.QueryRow(query, releaseID).Scan(&barcode)
	if err == sql.ErrNoRows {
		return "", nil
	}
	if err != nil {
		return "", fmt.Errorf("failed to fetch barcode: %w", err)
	}

	// Remove spaces and dashes
	barcode = strings.ReplaceAll(barcode, " ", "")
	barcode = strings.ReplaceAll(barcode, "-", "")

	return barcode, nil
}

// fetchCoverArt retrieves cover art for a release
func (c *DiscogsClient) fetchCoverArt(releaseID int) ([]models.CoverArt, error) {
	query := `
		SELECT image_type, uri, height, width
		FROM releases_images
		WHERE release_id = $1
	`

	rows, err := c.db.Query(query, releaseID)
	if err != nil {
		return nil, fmt.Errorf("query failed: %w", err)
	}
	defer rows.Close()

	var coverArt []models.CoverArt
	for rows.Next() {
		var art models.CoverArt
		var imageType, uri string
		var height, width sql.NullInt64

		if err := rows.Scan(&imageType, &uri, &height, &width); err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}

		// Add URL prefix if not already present
		if !strings.HasPrefix(uri, "http://") && !strings.HasPrefix(uri, "https://") {
			art.URI = "http://api.discogs.com/images/R-" + uri
			art.URI150 = "http://api.discogs.com/images/R-150-" + uri
		} else {
			art.URI = uri
			art.URI150 = uri
		}

		if height.Valid {
			art.Height = int(height.Int64)
		}
		if width.Valid {
			art.Width = int(width.Int64)
		}

		art.Primary = imageType == "primary"

		coverArt = append(coverArt, art)
	}

	return coverArt, rows.Err()
}

// fetchVideos retrieves videos for a release
func (c *DiscogsClient) fetchVideos(releaseID int) ([]models.Video, error) {
	query := `
		SELECT v.src, v.title
		FROM releases_videos rv
		INNER JOIN video v ON v.id = rv.video_id
		WHERE rv.release_id = $1
	`

	rows, err := c.db.Query(query, releaseID)
	if err != nil {
		return nil, fmt.Errorf("query failed: %w", err)
	}
	defer rows.Close()

	var videos []models.Video
	for rows.Next() {
		var video models.Video
		var src, title string

		if err := rows.Scan(&src, &title); err != nil {
			return nil, fmt.Errorf("scan failed: %w", err)
		}

		video.URI = "http://www.youtube.com/watch?v=" + src
		video.Title = title

		videos = append(videos, video)
	}

	return videos, rows.Err()
}

// countryToISO converts Discogs country names to ISO codes
// Full mapping from discogs_countries in ctdb.php
func countryToISO(country string) string {
	countryMap := map[string]string{
		"Afghanistan": "AF", "Africa": "", "Albania": "AL", "Algeria": "DZ", "American Samoa": "AS", "Andorra": "AD",
		"Angola": "AO", "Antarctica": "AQ", "Antigua & Barbuda": "AG", "Argentina": "AR", "Armenia": "AM", "Aruba": "AW",
		"Asia": "", "Australasia": "", "Australia": "AU", "Australia & New Zealand": "", "Austria": "AT", "Azerbaijan": "AZ",
		"Bahamas, The": "BS", "Bahrain": "BH", "Bangladesh": "BD", "Barbados": "BB", "Belarus": "BY", "Belgium": "BE",
		"Belize": "BZ", "Benelux": "", "Benin": "BJ", "Bermuda": "BM", "Bhutan": "BT", "Bolivia": "BO", "Bosnia & Herzegovina": "BA",
		"Botswana": "BW", "Brazil": "BR", "Bulgaria": "BG", "Burkina Faso": "BF", "Burma": "MM", "Cambodia": "KH", "Cameroon": "CM",
		"Canada": "CA", "Cape Verde": "CV", "Cayman Islands": "KY", "Central America": "", "Chile": "CL", "China": "CN",
		"Cocos (Keeling) Islands": "CC", "Colombia": "CO", "Congo, Democratic Republic of the": "CD", "Congo, Republic of the": "CG",
		"Cook Islands": "CK", "Costa Rica": "CR", "Croatia": "HR", "Cuba": "CU", "Cyprus": "CY", "Czechoslovakia": "XC",
		"Czech Republic": "CZ", "Denmark": "DK", "Dominica": "DM", "Dominican Republic": "DO", "East Timor": "TL", "Ecuador": "EC",
		"Egypt": "EG", "El Salvador": "SV", "Estonia": "EE", "Ethiopia": "ET", "Europa Island": "", "Europe": "XE",
		"Faroe Islands": "FO", "Fiji": "FJ", "Finland": "FI", "France": "FR", "France & Benelux": "", "French Guiana": "GF",
		"French Polynesia": "PF", "French Southern & Antarctic Lands": "TF", "Gabon": "GA", "Georgia": "GE",
		"German Democratic Republic (GDR)": "XG", "Germany": "DE", "Germany, Austria, & Switzerland": "", "Germany & Switzerland": "",
		"Ghana": "GH", "Greece": "GR", "Greenland": "GL", "Grenada": "GD", "Guadeloupe": "GP", "Guam": "GU", "Guatemala": "GT",
		"Guinea": "GN", "Gulf Cooperation Council": "", "Guyana": "GY", "Haiti": "HT", "Honduras": "HN", "Hong Kong": "HK",
		"Hungary": "HU", "Iceland": "IS", "India": "IN", "Indonesia": "ID", "Iran": "IR", "Iraq": "IQ", "Ireland": "IE",
		"Israel": "IL", "Italy": "IT", "Ivory Coast": "CI", "Jamaica": "JM", "Japan": "JP", "Jordan": "JO", "Kazakhstan": "KZ",
		"Kenya": "KE", "Korea": "KR", "Korea, North": "KP", "Kuwait": "KW", "Kyrgyzstan": "KG", "Latvia": "LV", "Lebanon": "LB",
		"Lesotho": "LS", "Liechtenstein": "LI", "Lithuania": "LT", "Luxembourg": "LU", "Macedonia": "MK", "Madagascar": "MG",
		"Malawi": "MW", "Malaysia": "MY", "Maldives": "MV", "Mali": "ML", "Malta": "MT", "Marshall Islands": "MH",
		"Martinique": "MQ", "Mauritius": "MU", "Mexico": "MX", "Moldova": "MD", "Monaco": "MC", "Mongolia": "MN",
		"Montenegro": "ME", "Morocco": "MA", "Mozambique": "MZ", "Namibia": "NA", "Nepal": "NP", "Netherlands": "NL",
		"Netherlands Antilles": "AN", "New Caledonia": "NC", "New Zealand": "NZ", "Nicaragua": "NI", "Nigeria": "NG",
		"North America (inc Mexico)": "", "Northern Mariana Islands": "MP", "North Korea": "KP", "Norway": "NO", "Oman": "OM",
		"Pakistan": "PK", "Panama": "PA", "Papua New Guinea": "PG", "Paraguay": "PY", "Peru": "PE", "Philippines": "PH",
		"Pitcairn Islands": "PN", "Poland": "PL", "Portugal": "PT", "Puerto Rico": "PR", "Reunion": "RE", "Romania": "RO",
		"Russia": "RU", "Saint Kitts and Nevis": "KN", "Saint Vincent and the Grenadines": "VC", "San Marino": "SM",
		"Saudi Arabia": "SA", "Scandinavia": "", "Senegal": "SN", "Serbia": "RS", "Serbia and Montenegro": "CS",
		"Seychelles": "SC", "Sierra Leone": "SL", "Singapore": "SG", "Slovakia": "SK", "Slovenia": "SI", "South Africa": "ZA",
		"South America": "", "South Korea": "KR", "Spain": "ES", "Sri Lanka": "LK", "Sudan": "SD", "Suriname": "SR",
		"Svalbard": "SJ", "Swaziland": "SZ", "Sweden": "SE", "Switzerland": "CH", "Syria": "SY", "Taiwan": "TW",
		"Tajikistan": "TJ", "Tanzania": "TZ", "Thailand": "TH", "Togo": "TG", "Trinidad & Tobago": "TT", "Tunisia": "TN",
		"Turkey": "TR", "Turks and Caicos Islands": "TC", "Tuvalu": "TV", "Uganda": "UG", "UK": "GB", "UK & Europe": "XE",
		"UK, Europe & US": "XW", "UK & Ireland": "", "Ukraine": "UA", "UK & US": "", "United Arab Emirates": "AE",
		"Uruguay": "UY", "US": "US", "USA & Canada": "", "USA, Canada & UK": "", "USSR": "SU", "Uzbekistan": "UZ",
		"Vatican City": "VA", "Venezuela": "VE", "Vietnam": "VN", "Virgin Islands": "VI", "Wake Island": "",
		"Wallis and Futuna": "WF", "Yugoslavia": "YU", "Zambia": "ZM", "Zimbabwe": "ZW",
	}

	if iso, ok := countryMap[country]; ok {
		// Return empty string for regional groupings that don't have ISO codes
		if iso == "" {
			return ""
		}
		return iso
	}
	return country
}
