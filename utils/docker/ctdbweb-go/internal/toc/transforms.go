package toc

import (
	"crypto/sha1"
	"encoding/base64"
	"fmt"
	"strings"
)

// ToMusicBrainzTOC converts TOC to MusicBrainz lookup format
// Port of PHP: toc2mbtoc()
func (t *TOC) ToMusicBrainzTOC() string {
	lastAudio := t.LastAudioTrack()
	// Leadout is always at index TrackCount (after all tracks)
	leadoutOffset := t.Offsets[t.TrackCount]

	// Calculate leadout for MusicBrainz
	if t.IsEnhancedCD() {
		// Enhanced CD: subtract 11400 sectors from leadout
		leadoutOffset = leadoutOffset + 150 - 11400
	} else {
		// Audio CD: just add 150 (pregap)
		leadoutOffset = leadoutOffset + 150
	}

	// Build MusicBrainz TOC string
	parts := []string{
		"1",                          // First track
		fmt.Sprintf("%d", lastAudio), // Last audio track
		fmt.Sprintf("%d", leadoutOffset),
	}

	// Add track offsets (with 150 sector pregap)
	for i := 0; i < lastAudio; i++ {
		parts = append(parts, fmt.Sprintf("%d", t.Offsets[i]+150))
	}

	return strings.Join(parts, " ")
}

// ToMusicBrainzDiscID converts TOC to MusicBrainz disc ID
// Port of PHP: tocs2mbid()
func ToMusicBrainzDiscID(tocStr string) (string, error) {
	toc, err := ParseTOCString(tocStr)
	if err != nil {
		return "", err
	}

	lastAudio := toc.LastAudioTrack()
	leadoutOffset := toc.Offsets[lastAudio]

	// Calculate leadout
	if toc.IsEnhancedCD() {
		leadoutOffset = leadoutOffset + 150 - 11400
	} else {
		leadoutOffset = leadoutOffset + 150
	}

	// Build hex string for hashing
	hexStr := fmt.Sprintf("%02X%02X", 1, lastAudio)
	hexStr += fmt.Sprintf("%08X", leadoutOffset)

	// Add track offsets
	for i := 0; i < lastAudio; i++ {
		hexStr += fmt.Sprintf("%08X", toc.Offsets[i]+150)
	}

	// Pad to 804 characters (100 tracks max)
	hexStr = fmt.Sprintf("%-804s", hexStr)
	hexStr = strings.ReplaceAll(hexStr, " ", "0")

	// SHA-1 hash
	hash := sha1.Sum([]byte(hexStr))

	// Base64 encode and make URL-safe
	encoded := base64.StdEncoding.EncodeToString(hash[:])
	encoded = strings.ReplaceAll(encoded, "+", ".")
	encoded = strings.ReplaceAll(encoded, "/", "_")
	encoded = strings.ReplaceAll(encoded, "=", "-")

	return encoded, nil
}

// ToTOCID converts TOC to unique CTDB TOC ID
// Port of PHP: toc2tocid()
func (t *TOC) ToTOCID() string {
	lastAudio := t.LastAudioTrack()
	pregap := t.Offsets[t.FirstAudio-1]

	// Build hex string from relative offsets
	hexStr := ""
	for i := t.FirstAudio; i < t.FirstAudio+t.AudioTracks-1; i++ {
		hexStr += fmt.Sprintf("%08X", t.Offsets[i]-pregap)
	}

	// Calculate leadout
	leadout := t.Offsets[lastAudio]
	if t.IsEnhancedCD() {
		leadout -= 11400
	}
	hexStr += fmt.Sprintf("%08X", leadout-pregap)

	// Pad to 800 characters (100 tracks max)
	hexStr = fmt.Sprintf("%-800s", hexStr)
	hexStr = strings.ReplaceAll(hexStr, " ", "0")

	// SHA-1 hash
	hash := sha1.Sum([]byte(hexStr))

	// Base64 encode and make URL-safe
	encoded := base64.StdEncoding.EncodeToString(hash[:])
	encoded = strings.ReplaceAll(encoded, "+", ".")
	encoded = strings.ReplaceAll(encoded, "/", "_")
	encoded = strings.ReplaceAll(encoded, "=", "-")

	return encoded
}

// ToCDDBID converts TOC to CDDB disc ID
// Port of PHP: toc2cddbid()
func (t *TOC) ToCDDBID() string {
	// Build time string for checksum
	timeStr := ""
	for i := 0; i < t.TrackCount; i++ {
		timeStr += fmt.Sprintf("%d", t.Offsets[i]/75+2)
	}

	// Calculate checksum
	checksum := 0
	for _, ch := range timeStr {
		checksum += int(ch - '0')
	}

	// Build CDDB ID
	id0 := fmt.Sprintf("%02X", checksum%255)
	id1 := fmt.Sprintf("%04X", t.Offsets[t.TrackCount]/75-t.Offsets[0]/75)
	id2 := fmt.Sprintf("%02X", t.TrackCount)

	return id0 + id1 + id2
}

// ToARID converts TOC to AccurateRip ID
// Port of PHP: toc2arid()
func (t *TOC) ToARID() string {
	discID1 := 0
	discID2 := 0

	for i := t.FirstAudio; i < t.FirstAudio+t.AudioTracks; i++ {
		offset := t.Offsets[i-1]
		discID1 += offset
		if offset < 1 {
			offset = 1
		}
		discID2 += offset * (i - t.FirstAudio + 1)
	}

	leadout := t.Offsets[t.TrackCount]
	discID1 += leadout
	if leadout < 1 {
		leadout = 1
	}
	discID2 += leadout * (t.AudioTracks + 1)

	cddbID := strings.ToLower(t.ToCDDBID())
	return fmt.Sprintf("%08x-%08x-%s", discID1, discID2, cddbID)
}

// TOCsToMBDiscID is an alias for ToMusicBrainzDiscID for consistency
func TOCsToMBDiscID(tocStr string) (string, error) {
	return ToMusicBrainzDiscID(tocStr)
}
