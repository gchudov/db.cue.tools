package models

import (
	"fmt"
	"strconv"
	"strings"
	"time"
)

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

	// Computed fields
	TOCFormatted     string `json:"toc_formatted"`
	TrackCountString string `json:"track_count_string"`
}

// PopulateComputedFields populates computed fields for the submission
func (r *RecentSubmission) PopulateComputedFields() {
	r.TrackCountString = r.FormatTrackCount()
	r.TOCFormatted = r.FormatTOC()
}

// FormatTrackCount returns track count as formatted string
// Returns: "12" for normal CDs, "1+12" for data+audio, "12+1" for audio+data
func (r *RecentSubmission) FormatTrackCount() string {
	if r.FirstAudio > 1 {
		// Data tracks at the beginning
		dataTracks := r.FirstAudio - 1
		return fmt.Sprintf("%d+%d", dataTracks, r.AudioTracks)
	} else if r.AudioTracks < r.TrackCount {
		// Data tracks at the end (Enhanced CD)
		return fmt.Sprintf("%d+1", r.AudioTracks)
	}
	// Normal audio CD
	return strconv.Itoa(r.AudioTracks)
}

// FormatTOC returns colon-separated TOC with data track markers
// Data tracks are prefixed with '-' (e.g., "150:12345:-234567:...")
func (r *RecentSubmission) FormatTOC() string {
	if r.TrackOffsets == "" {
		return ""
	}

	// Parse track offsets from space-separated string
	parts := strings.Fields(r.TrackOffsets)
	if len(parts) == 0 {
		return ""
	}

	// Mark data tracks with '-' prefix
	result := make([]string, len(parts))
	for i, offset := range parts {
		trackNum := i + 1

		// Determine if this is a data track
		// Data tracks are: tracks before firstAudio OR tracks after lastAudio (but not leadout)
		isDataTrack := false
		if trackNum < len(parts) { // Not leadout
			if trackNum < r.FirstAudio || trackNum >= r.FirstAudio+r.AudioTracks {
				isDataTrack = true
			}
		}

		if isDataTrack {
			result[i] = "-" + offset
		} else {
			result[i] = offset
		}
	}

	return strings.Join(result, ":")
}
