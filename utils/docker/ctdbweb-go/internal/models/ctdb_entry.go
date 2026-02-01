package models

// CTDBEntry represents a CTDB submission entry returned to API clients
type CTDBEntry struct {
	ID         int      `json:"id"`
	Artist     string   `json:"artist,omitempty"`
	Title      string   `json:"title,omitempty"`
	TOCID      string   `json:"tocid"`
	TOC        string   `json:"toc"`
	CRC32      string   `json:"crc32"`                    // Formatted as 8-digit hex
	Confidence int      `json:"confidence"`               // Submission count
	TrackCRCs  []string `json:"track_crcs,omitempty"`     // Array of hex strings
	HasParity  bool     `json:"has_parity"`
	ParityURL  string   `json:"parity_url,omitempty"`
	Syndrome   string   `json:"syndrome,omitempty"` // Base64 if needed
}
