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
gunzip -c discogs_releases.xml.gz | ./discogs > discogs_enums.sql

# Or use the wrapper script (includes XML cleanup)
gunzip -c discogs_releases.xml.gz | ../run_discogs_go.sh
```

### Output

The converter generates the following gzipped PostgreSQL COPY files:
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

Plus enum type definitions on stdout.

## Importing to PostgreSQL

```bash
# Create enum types first
gunzip -c discogs_enums_sql.gz | psql -U user -d discogs

# Import data tables
for f in discogs_*_sql.gz; do
    gunzip -c "$f" | psql -U user -d discogs
done
```

