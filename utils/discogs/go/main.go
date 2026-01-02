package main

import (
	"bufio"
	"compress/gzip"
	"encoding/xml"
	"fmt"
	"io"
	"os"
	"regexp"
	"strconv"
	"strings"
)

// XML Structures
type Release struct {
	ID          int          `xml:"id,attr"`
	Status      string       `xml:"status,attr"`
	MasterID    string       `xml:"master_id"`
	Title       string       `xml:"title"`
	Country     string       `xml:"country"`
	Released    string       `xml:"released"`
	Notes       string       `xml:"notes"`
	Artists     *Artists     `xml:"artists"`
	Labels      *Labels      `xml:"labels"`
	Formats     *Formats     `xml:"formats"`
	Genres      *StringList  `xml:"genres"`
	Styles      *StringList  `xml:"styles"`
	Tracklist   *Tracklist   `xml:"tracklist"`
	Identifiers *Identifiers `xml:"identifiers"`
	Videos      *Videos      `xml:"videos"`
	Images      *Images      `xml:"images"`
}

type Artists struct {
	Artist []Artist `xml:"artist"`
}

type Artist struct {
	Name   string `xml:"name"`
	ANV    string `xml:"anv"`
	Join   string `xml:"join"`
	Role   string `xml:"role"`
	Tracks string `xml:"tracks"`
}

type Labels struct {
	Label []LabelRef `xml:"label"`
}

type LabelRef struct {
	Name  string `xml:"name,attr"`
	Catno string `xml:"catno,attr"`
}

type Formats struct {
	Format []Format `xml:"format"`
}

type Format struct {
	Name         string      `xml:"name,attr"`
	Qty          string      `xml:"qty,attr"`
	Descriptions *StringList `xml:"descriptions"`
}

type StringList struct {
	Items []string `xml:",any"`
}

type Tracklist struct {
	Track []Track `xml:"track"`
}

type Track struct {
	Position string   `xml:"position"`
	Title    string   `xml:"title"`
	Duration string   `xml:"duration"`
	Artists  *Artists `xml:"artists"`
}

type Identifiers struct {
	Identifier []Identifier `xml:"identifier"`
}

type Identifier struct {
	Type  string `xml:"type,attr"`
	Value string `xml:"value,attr"`
}

type Videos struct {
	Video []Video `xml:"video"`
}

type Video struct {
	Src      string `xml:"src,attr"`
	Duration int    `xml:"duration,attr"`
	Title    string `xml:"title"`
}

type Images struct {
	Image []Image `xml:"image"`
}

type Image struct {
	Type   string `xml:"type,attr"`
	URI    string `xml:"uri,attr"`
	Width  int    `xml:"width,attr"`
	Height int    `xml:"height,attr"`
}

// Global state for deduplication
var (
	seqArtistName = 1
	seqLabel      = 1
	seqTitle      = 1
	seqCredit     = 1
	seqVideo      = 1

	knownNames        = make(map[string]int)
	knownLabels       = make(map[string]int)
	knownTitles       = make(map[string]int)
	knownCredits      = make(map[string]int)
	knownVideos       = make(map[string]int)
	knownGenres       = make(map[string]bool)
	knownStyles       = make(map[string]bool)
	knownFormats      = make(map[string]bool)
	knownDescriptions = make(map[string]bool)
	knownIDTypes      = make(map[string]bool)

	writers = make(map[string]*TableWriter)
)

// TableWriter handles gzipped COPY output
type TableWriter struct {
	file   *os.File
	gzip   *gzip.Writer
	writer *bufio.Writer
}

func newTableWriter(table string, columns []string) *TableWriter {
	f, err := os.Create("discogs_" + table + "_sql.gz")
	if err != nil {
		panic(err)
	}
	gz, err := gzip.NewWriterLevel(f, 4)
	if err != nil {
		panic(err)
	}
	w := bufio.NewWriter(gz)
	
	// Write COPY header
	w.WriteString(fmt.Sprintf("COPY %s (%s) FROM stdin;\n", table, strings.Join(columns, ", ")))
	
	return &TableWriter{file: f, gzip: gz, writer: w}
}

func (tw *TableWriter) WriteRow(values []string) {
	tw.writer.WriteString(strings.Join(values, "\t") + "\n")
}

func (tw *TableWriter) Close() {
	tw.writer.WriteString("\\.\n")
	tw.writer.Flush()
	tw.gzip.Close()
	tw.file.Close()
}

func getWriter(table string, columns []string) *TableWriter {
	if w, ok := writers[table]; ok {
		return w
	}
	w := newTableWriter(table, columns)
	writers[table] = w
	return w
}

// Escape functions
func escapeNode(s string) string {
	if s == "" {
		return "\\N"
	}
	s = strings.ReplaceAll(s, "\\", "\\\\")
	s = strings.ReplaceAll(s, "\t", "\\t")
	s = strings.ReplaceAll(s, "\r", "\\r")
	s = strings.ReplaceAll(s, "\n", "\\n")
	return s
}

func escapeNull(s string) string {
	if s == "" {
		return "\\N"
	}
	return s
}

// PostgreSQL array literal format for COPY text mode:
// - Elements with special chars must be quoted
// - Within quoted elements: \ -> \\, " -> \"
// - But COPY text format also interprets \, so we need \\\\ for \ and \\\" for "
var arrayNeedsQuote = regexp.MustCompile(`["\\\{\}, ]`)

func printArray(items []string) string {
	if len(items) == 0 {
		return ""
	}
	var parts []string
	for _, item := range items {
		if arrayNeedsQuote.MatchString(item) {
			// For COPY text format, backslashes need double escaping:
			// - First level: array literal escaping (\ -> \\, " -> \")
			// - Second level: COPY text escaping (\ -> \\)
			// So: \ -> \\\\ and " -> \\\"
			item = strings.ReplaceAll(item, "\\", "\\\\\\\\")
			item = strings.ReplaceAll(item, "\"", "\\\\\"")
			item = "\"" + item + "\""
		}
		parts = append(parts, item)
	}
	return "{" + strings.Join(parts, ",") + "}"
}

func escapeNodes(sl *StringList) string {
	if sl == nil || len(sl.Items) == 0 {
		return "\\N"
	}
	var items []string
	for _, item := range sl.Items {
		items = append(items, escapeNode(item))
	}
	return printArray(items)
}

// Duration parsing
var durationRe = regexp.MustCompile(`([0-9]*)[:'\.]([0-9]+)`)

const maxInt32 = 2147483647

func parseDuration(dur string) string {
	if dur == "" {
		return "\\N"
	}
	m := durationRe.FindStringSubmatch(dur)
	if m == nil {
		return "\\N"
	}
	
	// Parse seconds (required)
	sec, err := strconv.ParseInt(m[2], 10, 64)
	if err != nil {
		return "\\N"
	}
	
	// If no minutes part, just return seconds
	if m[1] == "" {
		if sec > maxInt32 {
			return "\\N"
		}
		return strconv.FormatInt(sec, 10)
	}
	
	// Parse minutes
	min, err := strconv.ParseInt(m[1], 10, 64)
	if err != nil {
		return "\\N"
	}
	
	// Calculate total seconds and check overflow
	total := min*60 + sec
	if total > maxInt32 || total < 0 {
		return "\\N"
	}
	
	return strconv.FormatInt(total, 10)
}

// Disc/Track number parsing
var discNoRe1 = regexp.MustCompile(`^([0-9]+)[-\.]([0-9]+)$`)
var discNoRe2 = regexp.MustCompile(`^CD([0-9]+)[-\.]([0-9]+)$`)
var discNoRe3 = regexp.MustCompile(`^[0-9]+$`)

func parseDiscno(pos string) (disc, track string) {
	if pos == "" {
		return "\\N", "\\N"
	}
	if m := discNoRe1.FindStringSubmatch(pos); m != nil {
		return m[1], m[2]
	}
	if m := discNoRe2.FindStringSubmatch(pos); m != nil {
		return m[1], m[2]
	}
	if m := discNoRe3.FindStringSubmatch(pos); m != nil {
		tr, _ := strconv.Atoi(m[0])
		if tr >= 0 && tr <= 0x7FFFFFFF {
			return "1", m[0]
		}
	}
	return "\\N", "\\N"
}

// Entity parsing with deduplication
func parseArtistName(name string) string {
	if name == "" {
		return "\\N"
	}
	if id, ok := knownNames[name]; ok {
		return strconv.Itoa(id)
	}
	id := seqArtistName
	seqArtistName++
	
	w := getWriter("artist_name", []string{"id", "name"})
	w.WriteRow([]string{strconv.Itoa(id), escapeNode(name)})
	
	knownNames[name] = id
	return strconv.Itoa(id)
}

func parseLabel(name string) string {
	if name == "" {
		return "\\N"
	}
	if id, ok := knownLabels[name]; ok {
		return strconv.Itoa(id)
	}
	id := seqLabel
	seqLabel++
	
	w := getWriter("label", []string{"id", "name"})
	w.WriteRow([]string{strconv.Itoa(id), escapeNode(name)})
	
	knownLabels[name] = id
	return strconv.Itoa(id)
}

func parseTitle(name string) string {
	if name == "" {
		return "\\N"
	}
	if id, ok := knownTitles[name]; ok {
		return strconv.Itoa(id)
	}
	id := seqTitle
	seqTitle++
	
	w := getWriter("track_title", []string{"id", "name"})
	w.WriteRow([]string{strconv.Itoa(id), escapeNode(name)})
	
	knownTitles[name] = id
	return strconv.Itoa(id)
}

func parseVideo(vid Video) string {
	if vid.Src == "" {
		return "\\N"
	}
	src := vid.Src
	if strings.HasPrefix(src, "http://www.youtube.com/watch?v=") {
		src = src[31:]
	}
	if id, ok := knownVideos[src]; ok {
		return strconv.Itoa(id)
	}
	id := seqVideo
	seqVideo++
	
	w := getWriter("video", []string{"id", "src", "title", "duration"})
	w.WriteRow([]string{
		strconv.Itoa(id),
		escapeNode(src),
		escapeNode(vid.Title),
		strconv.Itoa(vid.Duration),
	})
	
	knownVideos[src] = id
	return strconv.Itoa(id)
}

type artistCredit struct {
	name     string
	anv      string
	joinVerb string
	role     string
	tracks   string
}

func parseCredits(artists *Artists) string {
	if artists == nil || len(artists.Artist) == 0 {
		return "\\N"
	}
	
	var ac []artistCredit
	var nameParts []string
	join := ""
	
	for _, art := range artists.Artist {
		ac = append(ac, artistCredit{
			name:     parseArtistName(art.Name),
			anv:      parseArtistName(art.ANV),
			joinVerb: escapeNode(art.Join),
			role:     escapeNode(art.Role),
			tracks:   escapeNode(art.Tracks),
		})
		displayName := art.ANV
		if displayName == "" {
			displayName = art.Name
		}
		nameParts = append(nameParts, join+displayName)
		if art.Join != "" {
			join = " " + art.Join + " "
		} else {
			join = ""
		}
	}
	
	artistName := strings.Join(nameParts, "")
	artistNameID := parseArtistName(artistName)
	if artistNameID == "\\N" {
		return "\\N"
	}
	
	// Build key for deduplication
	var keyParts []string
	for _, a := range ac {
		keyParts = append(keyParts, a.name+"\t"+a.anv+"\t"+a.joinVerb+"\t"+a.role+"\t"+a.tracks)
	}
	key := strings.Join(keyParts, "\t")
	
	if id, ok := knownCredits[key]; ok {
		return strconv.Itoa(id)
	}
	
	creditID := seqCredit
	seqCredit++
	
	// Write artist_credit
	w := getWriter("artist_credit", []string{"id", "name", "count"})
	w.WriteRow([]string{strconv.Itoa(creditID), artistNameID, strconv.Itoa(len(ac))})
	
	// Write artist_credit_name entries
	wn := getWriter("artist_credit_name", []string{"artist_credit", "position", "name", "anv", "join_verb", "role", "tracks"})
	for i, a := range ac {
		wn.WriteRow([]string{strconv.Itoa(creditID), strconv.Itoa(i), a.name, a.anv, a.joinVerb, a.role, a.tracks})
	}
	
	knownCredits[key] = creditID
	return strconv.Itoa(creditID)
}

func parseImage(releaseID int, img Image) {
	uri := img.URI
	if strings.HasPrefix(uri, "http://api.discogs.com/images/R-") {
		uri = uri[32:]
	}
	
	w := getWriter("releases_images", []string{"release_id", "uri", "height", "width", "image_type"})
	w.WriteRow([]string{
		strconv.Itoa(releaseID),
		escapeNode(uri),
		strconv.Itoa(img.Height),
		strconv.Itoa(img.Width),
		escapeNode(img.Type),
	})
}

func parseRelease(rel Release) {
	// Collect genres and styles
	if rel.Genres != nil {
		for _, g := range rel.Genres.Items {
			knownGenres[g] = true
		}
	}
	if rel.Styles != nil {
		for _, s := range rel.Styles.Items {
			knownStyles[s] = true
		}
	}
	
	// Write release
	masterID := "\\N"
	if rel.MasterID != "" {
		masterID = rel.MasterID
	}
	
	w := getWriter("release", []string{"discogs_id", "master_id", "artist_credit", "title", "status", "country", "released", "notes", "genres", "styles"})
	w.WriteRow([]string{
		strconv.Itoa(rel.ID),
		masterID,
		parseCredits(rel.Artists),
		escapeNode(rel.Title),
		escapeNode(rel.Status),
		escapeNode(rel.Country),
		escapeNode(rel.Released),
		escapeNode(rel.Notes),
		escapeNodes(rel.Genres),
		escapeNodes(rel.Styles),
	})
	
	// Write labels
	if rel.Labels != nil {
		wl := getWriter("releases_labels", []string{"release_id", "label_id", "catno"})
		for _, lbl := range rel.Labels.Label {
			wl.WriteRow([]string{
				strconv.Itoa(rel.ID),
				parseLabel(lbl.Name),
				escapeNode(lbl.Catno),
			})
		}
	}
	
	// Write identifiers
	if rel.Identifiers != nil {
		wi := getWriter("releases_identifiers", []string{"release_id", "id_type", "id_value"})
		for _, id := range rel.Identifiers.Identifier {
			if id.Value != "" {
				knownIDTypes[escapeNode(id.Type)] = true
				wi.WriteRow([]string{
					strconv.Itoa(rel.ID),
					escapeNode(id.Type),
					escapeNode(id.Value),
				})
			}
		}
	}
	
	// Parse tracklist and build TOC
	toc := make(map[int]map[int]int) // disc -> track -> duration
	if rel.Tracklist != nil {
		wt := getWriter("track", []string{"release_id", "index", "discno", "trno", "position", "duration", "artist_credit", "title"})
		for i, trk := range rel.Tracklist.Track {
			disc, tr := parseDiscno(trk.Position)
			dur := parseDuration(trk.Duration)
			
			if disc != "\\N" && tr != "\\N" && dur != "\\N" {
				discNum, _ := strconv.Atoi(disc)
				trNum, _ := strconv.Atoi(tr)
				durNum, _ := strconv.Atoi(dur)
				if toc[discNum] == nil {
					toc[discNum] = make(map[int]int)
				}
				toc[discNum][trNum] = durNum
			}
			
			wt.WriteRow([]string{
				strconv.Itoa(rel.ID),
				strconv.Itoa(i + 1),
				disc,
				tr,
				escapeNode(trk.Position),
				dur,
				parseCredits(trk.Artists),
				parseTitle(trk.Title),
			})
		}
	}
	
	// Write formats
	isCD := false
	if rel.Formats != nil {
		wf := getWriter("releases_formats", []string{"release_id", "format_name", "qty", "descriptions"})
		for _, fmt := range rel.Formats.Format {
			if fmt.Name == "CD" {
				isCD = true
			}
			knownFormats[escapeNode(fmt.Name)] = true
			if fmt.Descriptions != nil {
				for _, d := range fmt.Descriptions.Items {
					knownDescriptions[d] = true
				}
			}
			qty, _ := strconv.Atoi(fmt.Qty)
			if qty > 0x7FFFFFFF {
				qty = 0x7FFFFFFF
			}
			wf.WriteRow([]string{
				strconv.Itoa(rel.ID),
				escapeNode(fmt.Name),
				strconv.Itoa(qty),
				escapeNodes(fmt.Descriptions),
			})
		}
	}
	
	// Write TOC for CDs
	if isCD && len(toc) > 0 {
		// Check if disc numbers are sequential
		maxDisc := 0
		for d := range toc {
			if d > maxDisc {
				maxDisc = d
			}
		}
		if maxDisc == len(toc) {
			wToc := getWriter("toc", []string{"discogs_id", "disc", "duration"})
			for disc := 1; disc <= maxDisc; disc++ {
				tracks := toc[disc]
				if len(tracks) >= 100 {
					continue
				}
				// Check if track numbers are sequential
				maxTrack := 0
				for t := range tracks {
					if t > maxTrack {
						maxTrack = t
					}
				}
				if maxTrack != len(tracks) {
					continue
				}
				// Build duration array
				var durs []string
				for t := 1; t <= maxTrack; t++ {
					durs = append(durs, strconv.Itoa(tracks[t]))
				}
				wToc.WriteRow([]string{
					strconv.Itoa(rel.ID),
					strconv.Itoa(disc),
					printArray(durs),
				})
			}
		}
	}
	
	// Write images
	if rel.Images != nil {
		for _, img := range rel.Images.Image {
			parseImage(rel.ID, img)
		}
	}
	
	// Write videos
	if rel.Videos != nil {
		wv := getWriter("releases_videos", []string{"release_id", "video_id"})
		for _, vid := range rel.Videos.Video {
			videoID := parseVideo(vid)
			if videoID != "\\N" {
				wv.WriteRow([]string{strconv.Itoa(rel.ID), videoID})
			}
		}
	}
}

func pgEscape(s string) string {
	s = strings.ReplaceAll(s, "'", "''")
	s = strings.ReplaceAll(s, "\\", "\\\\")
	return s
}

func main() {
	// Decompress gzipped stdin
	gz, err := gzip.NewReader(os.Stdin)
	if err != nil {
		panic(err)
	}
	defer gz.Close()

	decoder := xml.NewDecoder(gz)
	
	// Find the first release element
	for {
		tok, err := decoder.Token()
		if err == io.EOF {
			break
		}
		if err != nil {
			panic(err)
		}
		
		if se, ok := tok.(xml.StartElement); ok && se.Name.Local == "release" {
			var rel Release
			if err := decoder.DecodeElement(&rel, &se); err != nil {
				fmt.Fprintf(os.Stderr, "Error decoding release: %v\n", err)
				continue
			}
			parseRelease(rel)
		}
	}
	
	// Close all writers
	for _, w := range writers {
		w.Close()
	}
	
	// Write enum definitions to gzipped file
	writeEnums()
}

func writeEnums() {
	f, err := os.Create("discogs_enums_sql.gz")
	if err != nil {
		panic(err)
	}
	defer f.Close()
	
	gz, err := gzip.NewWriterLevel(f, 5)
	if err != nil {
		panic(err)
	}
	defer gz.Close()
	
	w := bufio.NewWriter(gz)
	defer w.Flush()
	
	writeEnum := func(name string, values map[string]bool) {
		w.WriteString("CREATE TYPE " + name + " AS ENUM (\n")
		var items []string
		for v := range values {
			items = append(items, "    E'"+pgEscape(v)+"'")
		}
		w.WriteString(strings.Join(items, ",\n"))
		w.WriteString("\n);\n\n")
	}
	
	writeEnum("style_t", knownStyles)
	writeEnum("genre_t", knownGenres)
	writeEnum("description_t", knownDescriptions)
	writeEnum("format_t", knownFormats)
	writeEnum("idtype_t", knownIDTypes)
}

