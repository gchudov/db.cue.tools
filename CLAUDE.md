# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

CUETools DB (db.cue.tools) is a CD database and metadata lookup service for audio CDs. It provides a web interface for browsing CD entries, performing TOC (Table of Contents) lookups, and retrieving metadata from multiple sources including MusicBrainz, Discogs, and FreeDB.

## Architecture

### Deployment Architecture

The application runs on AWS EC2 (Amazon Linux 2023) using a Docker-based microservices architecture:

- **postgres16**: PostgreSQL 16 database (main data store)
- **pgbouncer**: Connection pooler for PostgreSQL
- **ctdbweb**: PHP 8.4 + Apache backend (API endpoints and legacy UI)
- **react-dev**: Node.js 24 development server (React/Vite frontend)
- **proxy**: Apache 2.4 reverse proxy (TLS termination, routing)
- **adminer**: Database administration interface
- **musicbrainz-docker**: MusicBrainz database mirror
- **ctwiki**: MediaWiki instance for documentation
- **sendmail**: Email server
- **logspout**: Log aggregation

All containers run on a shared Docker network named `ct`.

### Database Architecture

The system uses PostgreSQL with three main databases:

1. **ctdb** - Main CUETools database containing:
   - `submissions2`: Deduplicated CD TOC submissions with parity data
   - `submissions`: Raw submission data with user agent info
   - Statistics tables (`hourly_stats`, `stats_totals`, `stats_drives`, `stats_agents`)

2. **discogs** - Discogs music release metadata:
   - `release`: Main release information
   - `artist_credit`, `artist_name`: Deduplicated artist data
   - `track`, `track_title`: Track information
   - `label`: Record labels
   - `toc`: CD TOC fingerprints for matching
   - Relationship tables: `releases_labels`, `releases_formats`, `releases_identifiers`, `releases_images`, `releases_videos`
   - Uses PostgreSQL enums for genres, styles, formats
   - GIST indexes on `create_cube_from_toc()` for fuzzy audio matching

3. **freedb** - FreeDB CD database entries:
   - `entries`: Main FreeDB CD entries with TOC offsets
   - `tracks`: Individual track data
   - `artist_names`, `genre_names`: Deduplicated reference data
   - GIST/HASH indexes for TOC-based CD matching

### Application Components

#### PHP Backend (`utils/docker/ctdbweb/db.cue.tools/`)

The PHP backend provides JSON API endpoints:

- `index.php` - Latest CD entries (`?json=1&start=0`)
- `top.php` - Popular CD entries (`?json=1&start=0`)
- `lookup2.php` - Metadata lookup by TOC (`?version=3&ctdb=0&metadata=default&fuzzy=1&toc=...`)
- `submit2.php` - CD submission endpoint
- `list1.php` - Original HTML/JS metadata display
- `show.php` - Display individual CD entry
- `stats.php`, `statsjson.php` - Statistics endpoints

Key characteristics:
- Uses PostgreSQL via Unix socket `/var/run/postgresql` (port 6432)
- Configuration in `ctdbcfg.php`
- Docker image: `php:8.4-apache`

#### React Frontend (`ansible/files/react-app/`)

Modern React + TypeScript + Vite frontend at https://db.cue.tools/ui/

Key features:
- Displays latest/popular CD entries
- Metadata lookup via MusicBrainz, CTDB, Discogs
- Track details with CRC checksums
- shadcn/ui components for UI elements

Technology stack:
- React 19.2 + TypeScript
- Vite 7.2 for bundling
- Tailwind CSS 4.1 for styling
- shadcn/ui components (Radix UI primitives)

Important configuration:
- Base path: `/ui/`
- Vite proxy configuration for HMR over WSS
- Runs in Docker container `react-dev` on port 80

#### Go Utilities

1. **s3uploader** (`utils/s3uploader/`)
   - Uploads parity files from local storage to S3 bucket `p.cuetools.net`
   - Processes batches of 10 submissions at a time
   - Updates `submissions2.s3` flag in database
   - Connection: `dbname=ctdb user=ctdb_user host=/var/run/postgresql port=6432`

2. **discogs2psql** (`utils/discogs/discogs2psql/`)
   - Converts Discogs XML dumps to PostgreSQL COPY format
   - Performs artist/label/title deduplication
   - Generates enum types dynamically
   - Outputs gzipped COPY files for efficient import

#### C Utilities

- **freedb** (`utils/freedb/`) - FreeDB data importer (C + libbz2)

## Common Development Commands

### React Frontend

```bash
# Enter the React dev container
docker exec -it react-dev bash

# Install dependencies (inside container)
npm install

# Start dev server (already running in container)
npm run dev -- --host 0.0.0.0 --port 80

# Build for production
npm run build

# Run linter
npm run lint

# Add shadcn/ui component
npx shadcn@latest add <component-name> --yes
```

Note: The React app has no test script configured. Test file exists at `src/lib/toc.test.ts` but requires test runner setup.

### PHP Backend

```bash
# Access PHP container
docker exec -it ctdbweb bash

# View PHP logs
docker logs ctdbweb

# Restart after code changes (code is mounted, restart may not be needed)
docker restart ctdbweb
```

### Go Utilities

```bash
# Build s3uploader
cd utils/s3uploader
go build -o s3uploader main.go

# Run s3uploader
./s3uploader

# Build discogs2psql
cd utils/discogs/discogs2psql
go build -o discogs2psql main.go
```

### Database Operations

```bash
# Connect to PostgreSQL via psql
./utils/connect_psql.sh

# Or connect directly via Docker
docker exec -it postgres16 psql -U postgres -d ctdb

# Connect via pgbouncer
docker exec -it pgbouncer psql -h /var/run/postgresql -p 6432 -U ctdb_user -d ctdb

# Access Adminer (database web UI)
# Available internally at http://adminer:8080
```

### Deployment

The application is deployed via Ansible playbooks:

```bash
# Deploy entire stack
ansible-playbook ansible/playbook.yml

# Deploy MusicBrainz mirror
ansible-playbook ansible/musicbrainz.yml
```

Key deployment features:
- Automatic database backup restore from S3 (`backups.cuetools.net`)
- SSL certificates via Certbot (Let's Encrypt DNS-01 with Route53)
- Docker container orchestration
- PGBouncer connection pooling

## Key Implementation Patterns

### TOC (Table of Contents) Handling

TOC format represents CD track offsets in sectors. The codebase includes utilities for:

- **TOC to MusicBrainz Disc ID** (`tocs2mbid()` in `ansible/files/react-app/src/lib/toc.ts`)
  - Converts CTDB TOC format to MusicBrainz disc ID using SHA-1
  - Async function (uses Web Crypto API)

- **TOC to MusicBrainz URL** (`tocs2mbtoc()`)
  - Converts TOC to MusicBrainz lookup URL format
  - Used for direct MusicBrainz queries

- **Sector to Time Conversion** (`sectorsToTime()`)
  - Converts CD sectors to MM:SS.FF format
  - 75 sectors = 1 second (CD-DA standard)

### Audio Fingerprint Matching

The database uses PostgreSQL GIST indexes with custom cube functions for fuzzy TOC matching:

- `create_cube_from_toc()` - Converts TOC array to multidimensional cube
- GIST index enables efficient similarity searches
- Used in both `discogs.toc` and `freedb.entries` tables

### Data Deduplication

To minimize storage, the system deduplicates common strings:

- Artist names (via `artist_name` table in discogs, `artist_names` in freedb)
- Track titles (via `track_title` table)
- Record labels (via `label` table)
- Genre names (via `genre_names` table)

References use integer foreign keys instead of repeating strings.

### API Response Format

PHP endpoints return JSON in Google Visualization API format:

```json
{
  "cols": [
    { "label": "Column Name", "type": "string" },
    ...
  ],
  "rows": [
    { "c": [{ "v": "value1" }, { "v": "value2" }, ...] },
    ...
  ]
}
```

This format is consumed by the React frontend.

## File Structure

```
.
├── ansible/                      # Deployment automation
│   ├── playbook.yml             # Main deployment playbook
│   ├── musicbrainz.yml          # MusicBrainz mirror setup
│   └── files/
│       └── react-app/           # React frontend source
├── terraform/                   # AWS infrastructure as code
├── utils/
│   ├── docker/                  # Docker container configs
│   │   ├── ctdbweb/            # PHP backend + Dockerfile
│   │   ├── proxy/              # Apache reverse proxy config
│   │   ├── pgbouncer/          # Connection pooler config
│   │   └── mediawiki/          # Wiki container
│   ├── s3uploader/             # Go utility for S3 uploads
│   ├── discogs/                # Discogs data import
│   │   ├── discogs2psql/       # Go converter
│   │   └── *.sql               # Schema definitions
│   ├── freedb/                 # FreeDB data import
│   │   ├── freedb.c            # C importer
│   │   └── *.sql               # Schema definitions
│   ├── purge.sh, purge.sql     # Data maintenance
│   └── hourly_stats.sql        # Statistics aggregation
└── .cursor/rules/              # IDE-specific rules
```

## Important Notes

### React App Development

- The app runs behind Apache reverse proxy at `/ui/`
- Vite HMR configured for WebSocket support over WSS
- Base path must be `/ui/` in `vite.config.ts`
- Runs in `react-dev` container, not on host
- shadcn/ui components should be used for new UI elements

### Database Connections

- PostgreSQL listens on Unix socket `/var/run/postgresql` (shared via Docker volume)
- PGBouncer pools connections on port 6432
- Use `ctdb_user` role for application connections
- Connection string format: `dbname=ctdb user=ctdb_user host=/var/run/postgresql port=6432 sslmode=disable`

### Backup and Restore

- Automated backups stored in S3 bucket `backups.cuetools.net`
- `LATEST` file contains current backup version
- Backups in PostgreSQL custom format (`pg_dump -Fc`)
- Ansible playbook auto-restores database on fresh deployment

### Production URLs

- Main site: https://db.cue.tools/
- React UI: https://db.cue.tools/ui/
- Wiki: https://cue.tools/
- Log viewer: https://db.cue.tools/logs/ (HTTP auth required)

### Docker Network

All services communicate via Docker network `ct`. Internal hostnames:
- `postgres16` - PostgreSQL database
- `pgbouncer` - Connection pooler
- `ctdbweb` - PHP backend
- `react-dev` - React dev server
- `proxy` - Reverse proxy (externally accessible)
- `adminer` - Database admin UI

### Security Considerations

- PostgreSQL uses trust authentication within Docker network
- Apache proxy handles TLS termination
- Secrets managed via AWS Secrets Manager
- Log viewer protected by HTTP basic auth (htpasswd)
