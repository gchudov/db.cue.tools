package models

// Metadata represents CD metadata from various sources (MusicBrainz, Discogs, FreeDB)
type Metadata struct {
	Source     string    `json:"source"`
	ID         string    `json:"id"`
	ArtistName string    `json:"artistname"`
	AlbumName  string    `json:"albumname"`
	Year       int       `json:"first_release_date_year,omitempty"`
	Genre      string    `json:"genre,omitempty"`
	Extra      string    `json:"extra,omitempty"`
	Tracklist  []Track   `json:"tracklist,omitempty"`
	Labels     []Label   `json:"label,omitempty"`
	DiscNumber int       `json:"discnumber,omitempty"`
	TotalDiscs int       `json:"totaldiscs,omitempty"`
	DiscName   string    `json:"discname,omitempty"`
	Barcode    string    `json:"barcode,omitempty"`
	CoverArt   []CoverArt `json:"coverart,omitempty"`
	Videos     []Video   `json:"videos,omitempty"`
	InfoURL    string    `json:"info_url,omitempty"`
	Releases   []Release `json:"release,omitempty"`
	Relevance  *int      `json:"relevance,omitempty"`
	DiscogsID  string    `json:"discogs_id,omitempty"`
}

// Track represents a single track in a CD
type Track struct {
	Name   string `json:"name"`
	Artist string `json:"artist,omitempty"`
	Extra  string `json:"extra,omitempty"`
}

// Label represents a record label
type Label struct {
	Name  string `json:"name"`
	Catno string `json:"catno,omitempty"`
}

// CoverArt represents album cover art
type CoverArt struct {
	URI     string `json:"uri"`
	URI150  string `json:"uri150,omitempty"`
	Width   int    `json:"width,omitempty"`
	Height  int    `json:"height,omitempty"`
	Primary bool   `json:"primary"`
}

// Video represents a video associated with a release
type Video struct {
	URI   string `json:"uri"`
	Title string `json:"title"`
}

// Release represents a release date and country
type Release struct {
	Country string `json:"country,omitempty"`
	Date    string `json:"date,omitempty"`
}
