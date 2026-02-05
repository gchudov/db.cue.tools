package models

import (
	"fmt"
	"strings"
)

// Submission represents a CD submission in the CTDB database
type Submission struct {
	ID           int     `json:"id"`
	Artist       string  `json:"artist"`
	Title        string  `json:"title"`
	TOCID        string  `json:"tocid"`
	FirstAudio   int     `json:"first_audio"`
	AudioTracks  int     `json:"audio_tracks"`
	TrackCount   int     `json:"track_count"`
	TrackOffsets string  `json:"track_offsets"`
	SubCount     int     `json:"sub_count"`
	CRC32        int32   `json:"crc32"`
	TrackCRCs    []int32 `json:"track_crcs,omitempty"`

	// Computed fields
	TOCFormatted      string `json:"toc_formatted"`
	TrackCountFormatted string `json:"track_count_formatted"`
	TrackCRCsFormatted  string `json:"track_crcs_formatted,omitempty"`
}

// PopulateComputedFields populates computed fields for the submission
func (s *Submission) PopulateComputedFields() {
	s.TrackCountFormatted = s.TrackCountString()
	s.TOCFormatted = s.FormatTOC()
	s.TrackCRCsFormatted = s.FormatTrackCRCs()
}

// TrackCountString returns formatted track count string
// e.g., "12" for audio-only CD, "1+12" for CD with data track first, "12+1" for enhanced CD
func (s *Submission) TrackCountString() string {
	if s.FirstAudio > 1 {
		return fmt.Sprintf("%d+%d", s.FirstAudio-1, s.AudioTracks)
	} else if s.AudioTracks < s.TrackCount {
		return fmt.Sprintf("%d+1", s.AudioTracks)
	}
	return fmt.Sprintf("%d", s.AudioTracks)
}

// FormatTOC returns colon-separated TOC with data track markers
// Data tracks are prefixed with '-' (e.g., "150:12345:-234567:...")
func (s *Submission) FormatTOC() string {
	if s.TrackOffsets == "" {
		return ""
	}

	parts := strings.Fields(s.TrackOffsets)
	if len(parts) == 0 {
		return ""
	}

	result := make([]string, len(parts))
	for i, offset := range parts {
		trackNum := i + 1

		// Data tracks are: tracks before firstAudio OR tracks after lastAudio (but not leadout)
		isDataTrack := false
		if trackNum < len(parts) { // Not leadout
			if trackNum < s.FirstAudio || trackNum >= s.FirstAudio+s.AudioTracks {
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

// FormatTrackCRCs returns space-separated hex CRC values
func (s *Submission) FormatTrackCRCs() string {
	if len(s.TrackCRCs) == 0 {
		return ""
	}
	crcStrs := make([]string, len(s.TrackCRCs))
	for i, crc := range s.TrackCRCs {
		crcStrs[i] = fmt.Sprintf("%08x", uint32(crc))
	}
	return strings.Join(crcStrs, " ")
}
