# Discogs XML to PostgreSQL Converter (Go)

A high-performance Go rewrite of the PHP discogs.php converter.

## Performance

Expected ~20-50x faster than the PHP version due to:
- Native compiled code
- Efficient map-based deduplication
- Streaming XML parsing with low memory usage
- Buffered gzip output

## Usage

### Build

```bash
cd go
go build -o discogs .
```

### Run

```bash
# Direct pipe from gzipped XML
gunzip -c discogs_releases.xml.gz | ./discogs

# Or use the wrapper script (includes XML cleanup)
gunzip -c discogs_releases.xml.gz | ../run_discogs_go.sh
```

All output files (including `discogs_enums_sql.gz`) are created in the current directory.

### Output

The converter generates the following gzipped PostgreSQL files:

**Enum definitions:**
- `discogs_enums_sql.gz` - CREATE TYPE statements for style_t, genre_t, format_t, description_t, idtype_t

**Data tables (COPY format):**
- `discogs_artist_name_sql.gz`
- `discogs_artist_credit_sql.gz`
- `discogs_artist_credit_name_sql.gz`
- `discogs_label_sql.gz`
- `discogs_track_title_sql.gz`
- `discogs_video_sql.gz`
- `discogs_release_sql.gz`
- `discogs_releases_labels_sql.gz`
- `discogs_releases_formats_sql.gz`
- `discogs_releases_identifiers_sql.gz`
- `discogs_releases_images_sql.gz`
- `discogs_releases_videos_sql.gz`
- `discogs_track_sql.gz`
- `discogs_toc_sql.gz`

## Importing to PostgreSQL

```bash
# Create enum types first
gunzip -c discogs_enums_sql.gz | psql -U user -d discogs

# Import data tables (skip the enums file)
for f in discogs_*_sql.gz; do
    [ "$f" = "discogs_enums_sql.gz" ] && continue
    gunzip -c "$f" | psql -U user -d discogs
done
```

