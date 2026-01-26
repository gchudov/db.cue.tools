package pgarray

import (
	"reflect"
	"testing"
)

func TestParse(t *testing.T) {
	tests := []struct {
		name    string
		input   string
		want    []interface{}
		wantErr bool
	}{
		{
			name:    "Empty array",
			input:   "{}",
			want:    []interface{}{},
			wantErr: false,
		},
		{
			name:  "Simple integer array",
			input: "{1,2,3}",
			want:  []interface{}{"1", "2", "3"},
		},
		{
			name:  "String array",
			input: `{"foo","bar","baz"}`,
			want:  []interface{}{"foo", "bar", "baz"},
		},
		{
			name:  "Array with NULL",
			input: "{1,NULL,3}",
			want:  []interface{}{"1", nil, "3"},
		},
		{
			name:  "Array with escaped quotes",
			input: `{"foo\"bar","baz"}`,
			want:  []interface{}{`foo"bar`, "baz"},
		},
		{
			name:  "Array with escaped backslash",
			input: `{"foo\\bar","baz"}`,
			want:  []interface{}{`foo\bar`, "baz"},
		},
		{
			name:  "Nested array",
			input: "{{1,2},{3,4}}",
			want: []interface{}{
				[]interface{}{"1", "2"},
				[]interface{}{"3", "4"},
			},
		},
		{
			name:  "Mixed types",
			input: `{1,"text",NULL,3.14}`,
			want:  []interface{}{"1", "text", nil, "3.14"},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := Parse(tt.input)
			if (err != nil) != tt.wantErr {
				t.Errorf("Parse() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("Parse() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestIndexes(t *testing.T) {
	tests := []struct {
		name string
		args []interface{}
		want string
	}{
		{
			name: "Empty",
			args: []interface{}{},
			want: "()",
		},
		{
			name: "Single element",
			args: []interface{}{1},
			want: "($1)",
		},
		{
			name: "Three elements",
			args: []interface{}{1, 2, 3},
			want: "($1,$2,$3)",
		},
		{
			name: "Five elements",
			args: []interface{}{"a", "b", "c", "d", "e"},
			want: "($1,$2,$3,$4,$5)",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := Indexes(tt.args)
			if got != tt.want {
				t.Errorf("Indexes() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestIndexesInt(t *testing.T) {
	got := IndexesInt([]int{1, 2, 3})
	want := "($1,$2,$3)"
	if got != want {
		t.Errorf("IndexesInt() = %v, want %v", got, want)
	}
}

func TestIndexesString(t *testing.T) {
	got := IndexesString([]string{"a", "b", "c"})
	want := "($1,$2,$3)"
	if got != want {
		t.Errorf("IndexesString() = %v, want %v", got, want)
	}
}

func TestByteaToString(t *testing.T) {
	tests := []struct {
		name    string
		input   string
		want    []byte
		wantErr bool
	}{
		{
			name:    "Empty",
			input:   "",
			want:    nil,
			wantErr: false,
		},
		{
			name:    "Hex format",
			input:   `\x48656c6c6f`,
			want:    []byte("Hello"),
			wantErr: false,
		},
		{
			name:    "Hex format - binary data",
			input:   `\x00010203`,
			want:    []byte{0x00, 0x01, 0x02, 0x03},
			wantErr: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := ByteaToString(tt.input)
			if (err != nil) != tt.wantErr {
				t.Errorf("ByteaToString() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("ByteaToString() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestHex2Int(t *testing.T) {
	tests := []struct {
		name   string
		hex    string
		signed bool
		want   int64
	}{
		{
			name:   "Unsigned small",
			hex:    "FF",
			signed: false,
			want:   255,
		},
		{
			name:   "Unsigned large",
			hex:    "FFFFFFFF",
			signed: false,
			want:   4294967295,
		},
		{
			name:   "Signed positive",
			hex:    "7FFFFFFF",
			signed: true,
			want:   2147483647,
		},
		{
			name:   "Signed negative",
			hex:    "FFFFFFFF",
			signed: true,
			want:   -1,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := Hex2Int(tt.hex, tt.signed)
			if got != tt.want {
				t.Errorf("Hex2Int() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestBigEndian2Int(t *testing.T) {
	tests := []struct {
		name   string
		bytes  []byte
		signed bool
		want   int64
	}{
		{
			name:   "Unsigned 1 byte",
			bytes:  []byte{0xFF},
			signed: false,
			want:   255,
		},
		{
			name:   "Unsigned 2 bytes",
			bytes:  []byte{0x01, 0x02},
			signed: false,
			want:   258,
		},
		{
			name:   "Unsigned 4 bytes",
			bytes:  []byte{0x00, 0x00, 0x01, 0x00},
			signed: false,
			want:   256,
		},
		{
			name:   "Signed positive",
			bytes:  []byte{0x7F, 0xFF},
			signed: true,
			want:   32767,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := BigEndian2Int(tt.bytes, tt.signed)
			if got != tt.want {
				t.Errorf("BigEndian2Int() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestHex2String(t *testing.T) {
	tests := []struct {
		name    string
		hex     string
		want    []byte
		wantErr bool
	}{
		{
			name:    "Simple",
			hex:     "48656c6c",
			want:    []byte("Hell"),
			wantErr: false,
		},
		{
			name:    "Zero padded",
			hex:     "00000001",
			want:    []byte{0x00, 0x00, 0x00, 0x01},
			wantErr: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := Hex2String(tt.hex)
			if (err != nil) != tt.wantErr {
				t.Errorf("Hex2String() error = %v, wantErr %v", err, tt.wantErr)
				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("Hex2String() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestLittleEndian2String(t *testing.T) {
	tests := []struct {
		name      string
		number    int64
		minBytes  int
		synchsafe bool
		want      []byte
	}{
		{
			name:      "Simple",
			number:    256,
			minBytes:  2,
			synchsafe: false,
			want:      []byte{0x00, 0x01},
		},
		{
			name:      "With padding",
			number:    1,
			minBytes:  4,
			synchsafe: false,
			want:      []byte{0x01, 0x00, 0x00, 0x00},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := LittleEndian2String(tt.number, tt.minBytes, tt.synchsafe)
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("LittleEndian2String() = %v, want %v", got, tt.want)
			}
		})
	}
}

func TestBigEndian2String(t *testing.T) {
	tests := []struct {
		name      string
		number    int64
		minBytes  int
		synchsafe bool
		want      []byte
	}{
		{
			name:      "Simple",
			number:    256,
			minBytes:  2,
			synchsafe: false,
			want:      []byte{0x01, 0x00},
		},
		{
			name:      "With padding",
			number:    1,
			minBytes:  4,
			synchsafe: false,
			want:      []byte{0x00, 0x00, 0x00, 0x01},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := BigEndian2String(tt.number, tt.minBytes, tt.synchsafe)
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("BigEndian2String() = %v, want %v", got, tt.want)
			}
		})
	}
}
