package models

import (
	"encoding/base64"
	"encoding/xml"
	"strings"
)

// XMLBool marshals boolean values as "1" or "0" strings for XML attributes
type XMLBool bool

func (b XMLBool) MarshalXMLAttr(name xml.Name) (xml.Attr, error) {
	value := "0"
	if b {
		value = "1"
	}
	return xml.Attr{Name: name, Value: value}, nil
}

// XMLTrackCRCs marshals []string as space-separated hex string for XML attribute
type XMLTrackCRCs []string

func (t XMLTrackCRCs) MarshalXMLAttr(name xml.Name) (xml.Attr, error) {
	if len(t) == 0 {
		return xml.Attr{}, nil
	}
	return xml.Attr{
		Name:  name,
		Value: strings.Join(t, " "),
	}, nil
}

// XMLCTDBResponse is the root element for XML responses
type XMLCTDBResponse struct {
	XMLName  xml.Name      `xml:"ctdb"`
	XMLNS    string        `xml:"xmlns,attr"`
	XMLNSExt string        `xml:"xmlns:ext,attr"`
	Entries  []XMLEntry    `xml:"entry,omitempty"`
	Metadata []XMLMetadata `xml:"metadata,omitempty"`
}

// XMLEntry represents a CTDB entry in XML format
type XMLEntry struct {
	ID         int          `xml:"id,attr"`
	CRC32      string       `xml:"crc32,attr"`
	Confidence int          `xml:"confidence,attr"`
	NPar       int          `xml:"npar,attr"`
	Stride     int          `xml:"stride,attr"`
	HasParity  string       `xml:"hasparity,attr,omitempty"` // Parity URL or omitted
	Parity     string       `xml:"parity,attr,omitempty"`
	Syndrome   string       `xml:"syndrome,attr,omitempty"`
	TrackCRCs  XMLTrackCRCs `xml:"trackcrcs,attr,omitempty"`
	TOC        string       `xml:"toc,attr"` // Attribute, not nested element
}

// XMLMetadata represents metadata from various sources in XML format
type XMLMetadata struct {
	Source      string        `xml:"source,attr"`
	ID          string        `xml:"id,attr"`
	Artist      string        `xml:"artist,attr"`
	Album       string        `xml:"album,attr"`
	Year        int           `xml:"year,attr,omitempty"`
	DiscNumber  int           `xml:"discnumber,attr,omitempty"`
	DiscCount   int           `xml:"disccount,attr,omitempty"`
	DiscName    string        `xml:"discname,attr,omitempty"`
	InfoURL     string        `xml:"infourl,attr,omitempty"`
	Barcode     string        `xml:"barcode,attr,omitempty"`
	DiscogsID   string        `xml:"discogs_id,attr,omitempty"`
	Genre       string        `xml:"genre,attr,omitempty"`
	Relevance   *int          `xml:"relevance,attr,omitempty"`
	Tracks      []XMLTrack    `xml:"track,omitempty"`
	Labels      []XMLLabel    `xml:"label,omitempty"`
	Releases    []XMLRelease  `xml:"release,omitempty"`
	CoverArt    []XMLCoverArt `xml:"coverart,omitempty"`
}

// XMLTrack represents a track in XML format
type XMLTrack struct {
	Name   string `xml:"name,attr"`
	Artist string `xml:"artist,attr,omitempty"` // Omit if same as album artist
}

// XMLLabel represents a record label in XML format
type XMLLabel struct {
	Name  string `xml:"name,attr"`
	Catno string `xml:"catno,attr,omitempty"`
}

// XMLRelease represents a release in XML format
type XMLRelease struct {
	Country string `xml:"country,attr,omitempty"`
	Date    string `xml:"date,attr,omitempty"`
}

// XMLCoverArt represents cover art in XML format
type XMLCoverArt struct {
	URI     string  `xml:"uri,attr"`
	URI150  string  `xml:"uri150,attr,omitempty"`
	Width   int     `xml:"width,attr,omitempty"`
	Height  int     `xml:"height,attr,omitempty"`
	Primary XMLBool `xml:"primary,attr"`
}

// ToXMLResponse converts CTDB entries and metadata to XMLCTDBResponse
func ToXMLResponse(ctdbEntries []CTDBEntry, metadata []Metadata) *XMLCTDBResponse {
	response := &XMLCTDBResponse{
		XMLNS:    "http://db.cuetools.net/ns/mmd-1.0#",
		XMLNSExt: "http://db.cuetools.net/ns/ext-1.0#",
	}

	// Convert CTDB entries
	for _, entry := range ctdbEntries {
		xmlEntry := XMLEntry{
			ID:         entry.ID,
			CRC32:      entry.CRC32,
			Confidence: entry.Confidence,
			NPar:       8, // Default to 8
			Stride:     5880,
			TOC:        entry.TOC,
		}

		// Set parity URL if available
		if entry.HasParity && entry.ParityURL != "" {
			xmlEntry.HasParity = entry.ParityURL
		}

		// Set syndrome if available and calculate NPar
		if entry.Syndrome != "" {
			xmlEntry.Syndrome = entry.Syndrome
			// Calculate NPar from base64-encoded syndrome (matches PHP lookup2.php:130)
			// PHP: strlen($record['syndrome'])/2
			// PHP bytea_to_string returns hex-encoded string, so /2 converts to byte count
			// Go: decode base64 to get raw bytes, then len(bytes)/2 to match PHP behavior
			if decoded, err := base64.StdEncoding.DecodeString(entry.Syndrome); err == nil {
				xmlEntry.NPar = len(decoded) / 2
			}
		}

		// Convert track CRCs
		if len(entry.TrackCRCs) > 0 {
			xmlEntry.TrackCRCs = XMLTrackCRCs(entry.TrackCRCs)
		}

		response.Entries = append(response.Entries, xmlEntry)
	}

	// Convert metadata
	for _, meta := range metadata {
		xmlMeta := XMLMetadata{
			Source:     meta.Source,
			ID:         meta.ID,
			Artist:     meta.ArtistName,
			Album:      meta.AlbumName,
			Year:       meta.Year,
			DiscNumber: meta.DiscNumber,
			DiscCount:  meta.TotalDiscs,
			DiscName:   meta.DiscName,
			InfoURL:    meta.InfoURL,
			Barcode:    meta.Barcode,
			DiscogsID:  meta.DiscogsID,
			Genre:      meta.Genre,
		}

		// Set relevance if available
		if meta.Relevance != nil {
			xmlMeta.Relevance = meta.Relevance
		}

		// Convert tracks
		for _, track := range meta.Tracklist {
			xmlTrack := XMLTrack{
				Name: track.Name,
			}
			// Only include artist if different from album artist
			if track.Artist != "" && track.Artist != meta.ArtistName {
				xmlTrack.Artist = track.Artist
			}
			xmlMeta.Tracks = append(xmlMeta.Tracks, xmlTrack)
		}

		// Convert labels
		for _, label := range meta.Labels {
			xmlLabel := XMLLabel{
				Name:  label.Name,
				Catno: label.Catno,
			}
			xmlMeta.Labels = append(xmlMeta.Labels, xmlLabel)
		}

		// Convert releases
		for _, release := range meta.Releases {
			xmlRelease := XMLRelease{
				Country: release.Country,
				Date:    release.Date,
			}
			xmlMeta.Releases = append(xmlMeta.Releases, xmlRelease)
		}

		// Convert cover art
		for _, art := range meta.CoverArt {
			xmlArt := XMLCoverArt{
				URI:     art.URI,
				URI150:  art.URI150,
				Width:   art.Width,
				Height:  art.Height,
				Primary: XMLBool(art.Primary),
			}
			xmlMeta.CoverArt = append(xmlMeta.CoverArt, xmlArt)
		}

		response.Metadata = append(response.Metadata, xmlMeta)
	}

	return response
}
