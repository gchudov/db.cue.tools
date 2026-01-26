package models

import "fmt"

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
