package models

import "time"

// RecentSubmission represents a recent CD submission with metadata
// Combines data from submissions and submissions2 tables
type RecentSubmission struct {
	// Fields from submissions2 table
	ID           int     `json:"id"`            // entryid
	Artist       string  `json:"artist"`
	Title        string  `json:"title"`
	TOCID        string  `json:"tocid"`
	FirstAudio   int     `json:"first_audio"`
	AudioTracks  int     `json:"audio_tracks"`
	TrackCount   int     `json:"track_count"`
	TrackOffsets string  `json:"track_offsets"`
	SubCount     int     `json:"sub_count"`
	CRC32        int32   `json:"crc32"`

	// Additional fields from submissions table
	Time      time.Time `json:"time"`
	Agent     string    `json:"agent,omitempty"`
	DriveName string    `json:"drivename,omitempty"`
	UserID    string    `json:"userid,omitempty"`
	IP        string    `json:"ip,omitempty"`
	Quality   *int      `json:"quality,omitempty"` // nullable
	Barcode   string    `json:"barcode,omitempty"`
}
