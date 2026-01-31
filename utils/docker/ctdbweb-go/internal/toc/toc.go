package toc

import (
	"fmt"
	"strconv"
	"strings"
)

// TOC represents a CD Table of Contents with track offsets
type TOC struct {
	FirstAudio  int    // First audio track number (1-based)
	AudioTracks int    // Number of audio tracks
	TrackCount  int    // Total number of tracks (including data tracks)
	Offsets     []int  // Track offsets in sectors (including leadout at end)
}

// ParseTOCString parses a TOC string in the format "offset1:offset2:...:leadout"
// Data tracks are prefixed with '-' (e.g., "-150:12345:...")
// Port of PHP: toc_s2toc()
func ParseTOCString(tocStr string) (*TOC, error) {
	if tocStr == "" {
		return nil, fmt.Errorf("empty TOC string")
	}

	parts := strings.Split(tocStr, ":")
	if len(parts) < 2 {
		return nil, fmt.Errorf("invalid TOC format: need at least 2 parts")
	}

	// Track whether each offset is a data track
	isDataTrack := make([]bool, len(parts))
	offsets := make([]int, len(parts))

	// Parse offsets and mark data tracks
	for i, part := range parts {
		if strings.HasPrefix(part, "-") {
			isDataTrack[i] = true
			part = part[1:] // Remove '-' prefix
		}

		offset, err := strconv.Atoi(part)
		if err != nil {
			return nil, fmt.Errorf("invalid offset at position %d: %v", i, err)
		}
		offsets[i] = offset
	}

	// Find first audio track (skip leading data tracks)
	// Note: len(parts)-1 is the leadout, not a track
	firstAudio := 1
	trackCount := len(parts) - 1
	for firstAudio <= trackCount && isDataTrack[firstAudio-1] {
		firstAudio++
	}

	// Count consecutive audio tracks starting from firstAudio
	audioTracks := 0
	for firstAudio+audioTracks <= trackCount && !isDataTrack[firstAudio+audioTracks-1] {
		audioTracks++
	}

	return &TOC{
		FirstAudio:  firstAudio,
		AudioTracks: audioTracks,
		TrackCount:  len(parts) - 1, // Exclude leadout from count
		Offsets:     offsets,
	}, nil
}

// String converts TOC struct back to string format
// Port of PHP: toc_toc2s()
func (t *TOC) String() string {
	parts := make([]string, len(t.Offsets))
	for i := 0; i < len(t.Offsets); i++ {
		offset := t.Offsets[i]
		trackNum := i + 1

		// Mark data tracks with '-' prefix (all tracks except audio and leadout)
		if i < t.TrackCount && (trackNum < t.FirstAudio || trackNum >= t.FirstAudio+t.AudioTracks) {
			parts[i] = fmt.Sprintf("-%d", offset)
		} else {
			parts[i] = strconv.Itoa(offset)
		}
	}
	return strings.Join(parts, ":")
}

// OffsetsString returns just the space-separated offsets without data track markers
// Used for database storage
func (t *TOC) OffsetsString() string {
	parts := make([]string, len(t.Offsets))
	for i, offset := range t.Offsets {
		parts[i] = strconv.Itoa(offset)
	}
	return strings.Join(parts, " ")
}

// IsEnhancedCD returns true if this is an Enhanced CD (has data tracks at the end)
func (t *TOC) IsEnhancedCD() bool {
	return t.FirstAudio == 1 && t.AudioTracks < t.TrackCount
}

// LastAudioTrack returns the track number of the last audio track (1-based)
func (t *TOC) LastAudioTrack() int {
	return t.FirstAudio + t.AudioTracks - 1
}

// SectorsToTime converts CD sectors to time format MM:SS.FF (75 sectors = 1 second)
func SectorsToTime(sectors int) string {
	frames := sectors % 75
	seconds := (sectors / 75) % 60
	minutes := sectors / (75 * 60)
	return fmt.Sprintf("%02d:%02d.%02d", minutes, seconds, frames)
}

// ParseTOC is an alias for ParseTOCString for consistency with other packages
func ParseTOC(tocStr string) (*TOC, error) {
	return ParseTOCString(tocStr)
}

// GetTrackOffsets returns the track offsets (excluding leadout)
func (t *TOC) GetTrackOffsets() []int {
	if len(t.Offsets) <= 1 {
		return []int{}
	}
	// Return all offsets except the leadout (last element)
	return t.Offsets[:len(t.Offsets)-1]
}

// GetDurationsInMilliseconds returns track durations in milliseconds for fuzzy matching
// Returns duration of each audio track
func (t *TOC) GetDurationsInMilliseconds() []int {
	if t.AudioTracks == 0 {
		return []int{}
	}

	durations := make([]int, t.AudioTracks)
	for i := 0; i < t.AudioTracks; i++ {
		trackIdx := t.FirstAudio - 1 + i
		nextIdx := trackIdx + 1

		// Calculate duration as difference between consecutive offsets
		if nextIdx < len(t.Offsets) {
			durationSectors := t.Offsets[nextIdx] - t.Offsets[trackIdx]
			// Convert sectors to milliseconds: 75 sectors = 1000ms
			// Use rounding instead of truncation to match PHP's round() function
			durations[i] = int(float64(durationSectors)*1000.0/75.0 + 0.5)
		}
	}

	return durations
}
