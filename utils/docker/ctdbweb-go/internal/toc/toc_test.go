package toc

import (
	"testing"
)

func TestParseTOCString(t *testing.T) {
	tests := []struct {
		name        string
		input       string
		wantAudio   int
		wantTracks  int
		wantCount   int
		wantOffsets []int
		wantErr     bool
	}{
		{
			name:        "Standard audio CD",
			input:       "0:16157:34440:51585:68107:87552:107437:127397:147512:168205:188807:209155:226627",
			wantAudio:   1,
			wantTracks:  12,
			wantCount:   12,
			wantOffsets: []int{0, 16157, 34440, 51585, 68107, 87552, 107437, 127397, 147512, 168205, 188807, 209155, 226627},
			wantErr:     false,
		},
		{
			name:        "Enhanced CD with data track",
			input:       "0:14052:32392:50197:68330:87420:106077:125272:144132:161707:180757:199920:219617:-238840:256877",
			wantAudio:   1,
			wantTracks:  13,
			wantCount:   14,
			wantOffsets: []int{0, 14052, 32392, 50197, 68330, 87420, 106077, 125272, 144132, 161707, 180757, 199920, 219617, 238840, 256877},
			wantErr:     false,
		},
		{
			name:        "CD with data track at beginning",
			input:       "-0:11400:28955:46490:64290:82845:102022:120165:138007:156220:173917:192267",
			wantAudio:   2,
			wantTracks:  10,  // Tracks 2-11 are audio (10 tracks)
			wantCount:   11,  // 11 total tracks
			wantOffsets: []int{0, 11400, 28955, 46490, 64290, 82845, 102022, 120165, 138007, 156220, 173917, 192267},
			wantErr:     false,
		},
		{
			name:    "Empty string",
			input:   "",
			wantErr: true,
		},
		{
			name:    "Invalid format",
			input:   "12345",
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := ParseTOCString(tt.input)
			if (err != nil) != tt.wantErr {
				t.Errorf("ParseTOCString() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if tt.wantErr {
				return
			}
			if got.FirstAudio != tt.wantAudio {
				t.Errorf("FirstAudio = %v, want %v", got.FirstAudio, tt.wantAudio)
			}
			if got.AudioTracks != tt.wantTracks {
				t.Errorf("AudioTracks = %v, want %v", got.AudioTracks, tt.wantTracks)
			}
			if got.TrackCount != tt.wantCount {
				t.Errorf("TrackCount = %v, want %v", got.TrackCount, tt.wantCount)
			}
			if len(got.Offsets) != len(tt.wantOffsets) {
				t.Errorf("Offsets length = %v, want %v", len(got.Offsets), len(tt.wantOffsets))
				return
			}
			for i, offset := range got.Offsets {
				if offset != tt.wantOffsets[i] {
					t.Errorf("Offsets[%d] = %v, want %v", i, offset, tt.wantOffsets[i])
				}
			}
		})
	}
}

func TestTOCString(t *testing.T) {
	tests := []struct {
		name string
		toc  *TOC
		want string
	}{
		{
			name: "Standard audio CD",
			toc: &TOC{
				FirstAudio:  1,
				AudioTracks: 12,
				TrackCount:  12,
				Offsets:     []int{0, 16157, 34440, 51585, 68107, 87552, 107437, 127397, 147512, 168205, 188807, 209155, 226627},
			},
			want: "0:16157:34440:51585:68107:87552:107437:127397:147512:168205:188807:209155:226627",
		},
		{
			name: "Enhanced CD with data track at end",
			toc: &TOC{
				FirstAudio:  1,
				AudioTracks: 13,
				TrackCount:  14,
				Offsets:     []int{0, 14052, 32392, 50197, 68330, 87420, 106077, 125272, 144132, 161707, 180757, 199920, 219617, 238840, 256877},
			},
			want: "0:14052:32392:50197:68330:87420:106077:125272:144132:161707:180757:199920:219617:-238840:256877",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := tt.toc.String()
			if got != tt.want {
				t.Errorf("String() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestSectorsToTime(t *testing.T) {
	tests := []struct {
		name    string
		sectors int
		want    string
	}{
		{"Zero", 0, "00:00.00"},
		{"One second", 75, "00:01.00"},
		{"One minute", 4500, "01:00.00"},
		{"One hour", 270000, "60:00.00"},
		{"Complex time", 16157, "03:35.32"},
		{"With frames", 226627, "50:21.52"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := SectorsToTime(tt.sectors)
			if got != tt.want {
				t.Errorf("SectorsToTime(%d) = %v, want %v", tt.sectors, got, tt.want)
			}
		})
	}
}

func TestRoundTrip(t *testing.T) {
	testCases := []string{
		"0:16157:34440:51585:68107:87552:107437:127397:147512:168205:188807:209155:226627",
		"0:14052:32392:50197:68330:87420:106077:125272:144132:161707:180757:199920:219617:-238840:256877",
		"-0:11400:28955:46490:64290:82845:102022:120165:138007:156220:173917:192267",
	}

	for _, tc := range testCases {
		t.Run(tc, func(t *testing.T) {
			toc, err := ParseTOCString(tc)
			if err != nil {
				t.Fatalf("ParseTOCString() error = %v", err)
			}
			got := toc.String()
			if got != tc {
				t.Errorf("Round trip failed: got %v, want %v", got, tc)
			}
		})
	}
}
