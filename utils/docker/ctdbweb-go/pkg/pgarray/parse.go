package pgarray

import (
	"fmt"
	"regexp"
	"strconv"
	"strings"
)

// Parse parses a PostgreSQL array string into a Go slice
// Handles nested arrays, quoted strings with escapes, and NULL values
// Port of PHP: pg_array_parse()
func Parse(text string) ([]interface{}, error) {
	if text == "{}" {
		return []interface{}{}, nil
	}

	result := []interface{}{}
	_, err := parse(text, &result, len(text)-1, 1)
	if err != nil {
		return nil, err
	}
	return result, nil
}

// parse is the recursive parser implementation
func parse(text string, output *[]interface{}, limit int, offset int) (int, error) {
	// Pattern: matches element (quoted string, unquoted value, or nested array) + delimiter
	// (\{?"([^"\\]|\\.)*"|[^,{}]+)+ matches the value
	// ([,}]+) matches the delimiter
	pattern := regexp.MustCompile(`(\{?"([^"\\]|\\.)*"|[^,{}]+)+([,}]+)`)

	for offset < limit {
		// Check if we're entering a nested array
		if text[offset] == '{' {
			nestedOutput := []interface{}{}
			newOffset, err := parse(text, &nestedOutput, limit, offset+1)
			if err != nil {
				return 0, err
			}
			*output = append(*output, nestedOutput)
			offset = newOffset
			continue
		}

		// Find the next match starting from offset
		match := pattern.FindStringSubmatchIndex(text[offset:])
		if match == nil {
			break
		}

		// Extract indices (relative to text[offset:])
		matchEnd := match[1]
		valueStart := match[2]
		valueEnd := match[3]
		delimStart := match[6]
		delimEnd := match[7]

		// Extract actual strings
		value := text[offset+valueStart : offset+valueEnd]
		delimiter := text[offset+delimStart : offset+delimEnd]

		// Parse the value
		parsedValue, err := parseValue(value)
		if err != nil {
			return 0, err
		}
		*output = append(*output, parsedValue)

		// Move offset past the entire match
		offset += matchEnd

		// If we hit '},' we're done with this level
		if strings.HasPrefix(delimiter, "},") {
			return offset, nil
		}
	}

	return offset, nil
}

// parseValue parses a single value (NULL, quoted string, or unquoted value)
func parseValue(value string) (interface{}, error) {
	// Handle NULL
	if value == "NULL" {
		return nil, nil
	}

	// Handle quoted strings
	if len(value) > 0 && value[0] == '"' {
		if len(value) < 2 || value[len(value)-1] != '"' {
			return nil, fmt.Errorf("malformed quoted string: %s", value)
		}
		// Remove quotes and unescape
		unquoted := value[1 : len(value)-1]
		return unescapeString(unquoted), nil
	}

	// Unquoted value - return as-is
	return value, nil
}

// unescapeString unescapes PostgreSQL string escapes
func unescapeString(s string) string {
	// Replace escape sequences
	s = strings.ReplaceAll(s, `\\`, `\`)
	s = strings.ReplaceAll(s, `\"`, `"`)
	return s
}

// Indexes generates PostgreSQL parameterized query placeholders
// Port of PHP: pg_array_indexes()
// Example: []interface{}{1, 2, 3} -> "($1,$2,$3)"
func Indexes(args []interface{}) string {
	if len(args) == 0 {
		return "()"
	}

	parts := make([]string, len(args))
	for i := range args {
		parts[i] = fmt.Sprintf("$%d", i+1)
	}
	return "(" + strings.Join(parts, ",") + ")"
}

// IndexesInt is a convenience function for integer slices
func IndexesInt(args []int) string {
	iface := make([]interface{}, len(args))
	for i, v := range args {
		iface[i] = v
	}
	return Indexes(iface)
}

// IndexesString is a convenience function for string slices
func IndexesString(args []string) string {
	iface := make([]interface{}, len(args))
	for i, v := range args {
		iface[i] = v
	}
	return Indexes(iface)
}

// ByteaToString converts PostgreSQL bytea format to string
// Port of PHP: bytea_to_string()
func ByteaToString(str string) ([]byte, error) {
	if str == "" {
		return nil, nil
	}

	// Hex format: \x followed by hex digits
	if len(str) >= 2 && str[0] == '\\' && str[1] == 'x' {
		hexStr := str[2:]
		result := make([]byte, len(hexStr)/2)
		for i := 0; i < len(hexStr); i += 2 {
			val, err := strconv.ParseUint(hexStr[i:i+2], 16, 8)
			if err != nil {
				return nil, fmt.Errorf("invalid bytea hex format: %v", err)
			}
			result[i/2] = byte(val)
		}
		return result, nil
	}

	// Escape format: backslash-escaped octals
	return unescapeBytea(str), nil
}

// unescapeBytea unescapes PostgreSQL escape format
func unescapeBytea(s string) []byte {
	result := []byte{}
	i := 0
	for i < len(s) {
		if s[i] == '\\' && i+3 < len(s) {
			// Octal escape: \NNN
			octal := s[i+1 : i+4]
			val, err := strconv.ParseUint(octal, 8, 8)
			if err == nil {
				result = append(result, byte(val))
				i += 4
				continue
			}
		}
		result = append(result, s[i])
		i++
	}
	return result
}

// Hex2Int converts hex string to signed/unsigned integer
// Port of PHP: Hex2Int()
func Hex2Int(hex string, signed bool) int64 {
	val, err := strconv.ParseUint(hex, 16, 64)
	if err != nil {
		return 0
	}

	if signed {
		// Handle two's complement for signed values
		if val > 0x7FFFFFFF {
			return -int64(0x100000000 - val)
		}
		return int64(val)
	}

	return int64(val)
}

// BigEndian2Int converts big-endian byte sequence to integer
// Port of PHP: BigEndian2Int()
func BigEndian2Int(bytes []byte, signed bool) int64 {
	var result int64
	for i := 0; i < len(bytes); i++ {
		result += int64(bytes[i]) << (8 * (len(bytes) - 1 - i))
	}

	if signed {
		signBit := int64(0x80) << (8 * (len(bytes) - 1))
		if result&signBit != 0 {
			result = -(result & (signBit - 1))
		}
	}

	return result
}

// Hex2String converts hex string to byte string
// Port of PHP: Hex2String()
func Hex2String(hexNumber string) ([]byte, error) {
	// Pad to 8 characters
	hexWord := fmt.Sprintf("%08s", hexNumber)

	result := make([]byte, 4)
	for i := 0; i < 4; i++ {
		val, err := strconv.ParseUint(hexWord[i*2:i*2+2], 16, 8)
		if err != nil {
			return nil, err
		}
		result[i] = byte(val)
	}
	return result, nil
}

// LittleEndian2String converts number to little-endian byte string
// Port of PHP: LittleEndian2String()
func LittleEndian2String(number int64, minBytes int, synchsafe bool) []byte {
	result := []byte{}
	for number > 0 {
		if synchsafe {
			result = append(result, byte(number&127))
			number >>= 7
		} else {
			result = append(result, byte(number&255))
			number >>= 8
		}
	}

	// Pad to minimum length
	for len(result) < minBytes {
		result = append(result, 0)
	}

	return result
}

// BigEndian2String converts number to big-endian byte string
// Port of PHP: BigEndian2String()
func BigEndian2String(number int64, minBytes int, synchsafe bool) []byte {
	littleEndian := LittleEndian2String(number, minBytes, synchsafe)
	// Reverse the bytes
	for i, j := 0, len(littleEndian)-1; i < j; i, j = i+1, j-1 {
		littleEndian[i], littleEndian[j] = littleEndian[j], littleEndian[i]
	}
	return littleEndian
}
