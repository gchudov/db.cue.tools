package toc

import (
	"testing"
)

func TestToMusicBrainzTOC(t *testing.T) {
	tests := []struct {
		name    string
		tocStr  string
		want    string
	}{
		{
			name:   "Standard audio CD",
			tocStr: "0:16157:34440:51585:68107:87552:107437:127397:147512:168205:188807:209155:226627",
			want:   "1 12 226777 150 16307 34590 51735 68257 87702 107587 127547 147662 168355 188957 209305",
		},
		{
			name:   "Enhanced CD",
			tocStr: "0:14052:32392:50197:68330:87420:106077:125272:144132:161707:180757:199920:219617:-238840:256877",
			want:   "1 13 245627 150 14202 32542 50347 68480 87570 106227 125422 144282 161857 180907 200070 219767",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			toc, err := ParseTOCString(tt.tocStr)
			if err != nil {
				t.Fatalf("ParseTOCString() error = %v", err)
			}
			got := toc.ToMusicBrainzTOC()
			if got != tt.want {
				t.Errorf("ToMusicBrainzTOC() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestToMusicBrainzDiscID(t *testing.T) {
	tests := []struct {
		name    string
		tocStr  string
		want    string
		wantErr bool
	}{
		{
			name:    "Standard audio CD (12 tracks)",
			tocStr:  "0:16157:34440:51585:68107:87552:107437:127397:147512:168205:188807:209155:226627",
			want:    "", // TODO: Add expected MusicBrainz disc ID from PHP
			wantErr: false,
		},
		{
			name:    "Enhanced CD",
			tocStr:  "0:14052:32392:50197:68330:87420:106077:125272:144132:161707:180757:199920:219617:-238840:256877",
			want:    "", // TODO: Add expected MusicBrainz disc ID from PHP
			wantErr: false,
		},
		{
			name:    "Invalid TOC",
			tocStr:  "",
			want:    "",
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := ToMusicBrainzDiscID(tt.tocStr)
			if (err != nil) != tt.wantErr {
				t.Errorf("ToMusicBrainzDiscID() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if tt.wantErr {
				return
			}
			// For now, just verify it returns something non-empty
			if got == "" {
				t.Errorf("ToMusicBrainzDiscID() returned empty string")
			}
			// TODO: Compare with known good PHP output
			if tt.want != "" && got != tt.want {
				t.Errorf("ToMusicBrainzDiscID() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestToTOCID(t *testing.T) {
	tests := []struct {
		name   string
		tocStr string
		want   string
	}{
		{
			name:   "Standard audio CD",
			tocStr: "0:16157:34440:51585:68107:87552:107437:127397:147512:168205:188807:209155:226627",
			want:   "", // TODO: Add expected TOCID from PHP
		},
		{
			name:   "Enhanced CD",
			tocStr: "0:14052:32392:50197:68330:87420:106077:125272:144132:161707:180757:199920:219617:-238840:256877",
			want:   "", // TODO: Add expected TOCID from PHP
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			toc, err := ParseTOCString(tt.tocStr)
			if err != nil {
				t.Fatalf("ParseTOCString() error = %v", err)
			}
			got := toc.ToTOCID()
			// For now, just verify it returns something non-empty
			if got == "" {
				t.Errorf("ToTOCID() returned empty string")
			}
			// TODO: Compare with known good PHP output
			if tt.want != "" && got != tt.want {
				t.Errorf("ToTOCID() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestToCDDBID(t *testing.T) {
	tests := []struct {
		name   string
		tocStr string
		want   string
	}{
		{
			name:   "Standard audio CD",
			tocStr: "0:16157:34440:51585:68107:87552:107437:127397:147512:168205:188807:209155:226627",
			want:   "", // TODO: Add expected CDDB ID from PHP
		},
		{
			name:   "Enhanced CD",
			tocStr: "0:14052:32392:50197:68330:87420:106077:125272:144132:161707:180757:199920:219617:-238840:256877",
			want:   "", // TODO: Add expected CDDB ID from PHP
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			toc, err := ParseTOCString(tt.tocStr)
			if err != nil {
				t.Fatalf("ParseTOCString() error = %v", err)
			}
			got := toc.ToCDDBID()
			// Verify format (8 hex characters)
			if len(got) != 8 {
				t.Errorf("ToCDDBID() length = %v, want 8", len(got))
			}
			// TODO: Compare with known good PHP output
			if tt.want != "" && got != tt.want {
				t.Errorf("ToCDDBID() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestToARID(t *testing.T) {
	tests := []struct {
		name   string
		tocStr string
		want   string
	}{
		{
			name:   "Standard audio CD",
			tocStr: "0:16157:34440:51585:68107:87552:107437:127397:147512:168205:188807:209155:226627",
			want:   "", // TODO: Add expected ARID from PHP
		},
		{
			name:   "Enhanced CD",
			tocStr: "0:14052:32392:50197:68330:87420:106077:125272:144132:161707:180757:199920:219617:-238840:256877",
			want:   "", // TODO: Add expected ARID from PHP
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			toc, err := ParseTOCString(tt.tocStr)
			if err != nil {
				t.Fatalf("ParseTOCString() error = %v", err)
			}
			got := toc.ToARID()
			// Verify format (8hex-8hex-8HEX)
			if len(got) < 20 {
				t.Errorf("ToARID() length = %v, want at least 20", len(got))
			}
			// TODO: Compare with known good PHP output
			if tt.want != "" && got != tt.want {
				t.Errorf("ToARID() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestIsEnhancedCD(t *testing.T) {
	tests := []struct {
		name   string
		tocStr string
		want   bool
	}{
		{
			name:   "Standard audio CD",
			tocStr: "0:16157:34440:51585:68107:87552:107437:127397:147512:168205:188807:209155:226627",
			want:   false,
		},
		{
			name:   "Enhanced CD",
			tocStr: "0:14052:32392:50197:68330:87420:106077:125272:144132:161707:180757:199920:219617:-238840:256877",
			want:   true,
		},
		{
			name:   "Data track at beginning",
			tocStr: "-0:11400:28955:46490:64290:82845:102022:120165:138007:156220:173917:192267",
			want:   false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			toc, err := ParseTOCString(tt.tocStr)
			if err != nil {
				t.Fatalf("ParseTOCString() error = %v", err)
			}
			got := toc.IsEnhancedCD()
			if got != tt.want {
				t.Errorf("IsEnhancedCD() = %v, want %v", got, tt.want)
			}
		})
	}
}
