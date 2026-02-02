# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## CRITICAL: Production Safety Rules

**NEVER directly modify, restart, or deploy to production containers or services.** This is a live production system serving real users.

When code changes are made:
1. **Make the code changes** to files as requested
2. **Test in development containers** if available (ctdbweb-go-dev, react-dev)
3. **Always prompt the user** to manually deploy changes via utils/rebuild_production.sh, ansible or restart services
4. **Never run** `docker restart`, `docker stop`, `systemctl restart`, or any command that affects running services
5. **Never push** changes to remote repositories without explicit user instruction

**Exception:** Reading logs, checking status, and querying databases for information are safe operations.

## Project Overview

CUETools DB (db.cue.tools) is a CD database and metadata lookup service for audio CDs. It provides a web interface for browsing CD entries, performing TOC (Table of Contents) lookups, and retrieving metadata from multiple sources including MusicBrainz, Discogs, and FreeDB.

## Architecture

### Deployment Architecture

The application runs on AWS EC2 (Amazon Linux 2023) using a Docker-based microservices architecture:

- **postgres16**: PostgreSQL 16 database (main data store)
- **pgbouncer**: Connection pooler for PostgreSQL
- **ctdbweb-go**: Go 1.23 JSON API backend (production)
- **ctdbweb-go-dev**: Go development server with Air hot-reload
- **ctdbweb**: PHP 8.4 + Apache backend (production legacy XML API endpoints)
- **ctdbweb-dev**: PHP development server
- **react-prod**: Production React frontend (nginx)
- **react-dev**: Node.js 24 development server (React/Vite frontend with HMR)
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
   - `users`: Authentication (email PK, role, created_at, last_login)
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

#### Go Backend (`utils/docker/ctdbweb-go/`)

Modern Go 1.23 backend serving JSON APIs at https://db.cue.tools/api/

**JSON API Endpoints:**

- **GET /api/stats?type=totals** - Database totals (unique_tocs, submissions)
  ```json
  {"unique_tocs": 7585649, "submissions": 90513228}
  ```

- **GET /api/stats?type=drives** - CD drive statistics (top 100)
  ```json
  [{"drive": "HL-DT-STDVDRAM", "count": 10898}, ...]
  ```

- **GET /api/stats?type=agents** - User agent statistics (top 100)
  ```json
  [{"agent": "EACv1.8 CTDB 2.2.6", "count": 515167}, ...]
  ```

- **GET /api/stats?type=pregaps** - Pregap statistics (top 100)
  ```json
  [{"pregap": "32", "count": 57134}, ...]
  ```

- **GET /api/stats?type=submissions&count=365** - Daily submission history
  ```json
  [{"date": "2026-01-26", "eac": 28508, "cueripper": 670, "cuetools": 0}, ...]
  ```

- **GET /api/stats?type=submissions&count=336&hourly=1** - Hourly submission history
  ```json
  [{"date": "01-26 22:00", "eac": 1016, "cueripper": 25, "cuetools": 0}, ...]
  ```

- **GET /api/latest?limit=10&start=0** - Latest CD submissions
- **GET /api/top?limit=10&start=0** - Most popular CD submissions
- **GET /api/lookup?toc=...** - CD metadata lookup (in development)

**Authentication endpoints:**
- **GET /api/auth/login** - Redirects to Google OAuth consent screen
- **GET /api/auth/callback** - OAuth callback handler (sets JWT cookie)
- **POST /api/auth/logout** - Clears authentication cookie
- **GET /api/auth/me** - Returns current user info (requires authentication)

**Key characteristics:**
- Multi-stage Docker build (development with Air hot-reload, production optimized binary)
- Connects to PostgreSQL via pgbouncer TCP (host=pgbouncer port=6432)
- Parallel metadata queries using goroutines (MusicBrainz, Discogs, FreeDB)
- Modular architecture: handlers, database clients, models, TOC transformation library
- Production: ctdbweb-go container (port 8080 internal only)
- Development: ctdbweb-go-dev container with Air hot-reload (~1 second rebuild)
- Access via Apache proxy at `/api/*` (no direct port exposure)

**File structure:**
```
utils/docker/ctdbweb-go/
├── cmd/server/          # Main application entry point
├── internal/
│   ├── auth/            # Authentication (OAuth2, JWT, middleware)
│   ├── handlers/        # HTTP handlers (stats, latest, top, lookup, auth)
│   ├── database/        # Database clients (CTDB, MusicBrainz, Discogs, FreeDB, users)
│   ├── metadata/        # Metadata query clients
│   ├── models/          # Data models (Submission, Track, etc.)
│   └── toc/            # TOC transformation library (28 functions)
├── pkg/pgarray/         # PostgreSQL array parser
├── Dockerfile           # Multi-stage build (dev + prod)
├── docker-compose.yml   # Local development only
├── .air.toml           # Hot-reload configuration
└── go.mod              # Go 1.23 dependencies
```

#### PHP Backend (`utils/docker/ctdbweb/db.cue.tools/`)

Minimal legacy PHP backend provides XML API endpoints for backward compatibility:

- `submit2.php` - CD submission endpoint (production)
- `lookup2.php` - Metadata lookup by TOC (XML format for legacy clients)

**Note:** Most PHP functionality has been migrated to Go backend. PHP only serves critical XML API endpoints (`submit2.php`, `lookup2.php`). The React UI has replaced legacy HTML views.

Key characteristics:
- Uses PostgreSQL via Unix socket `/var/run/postgresql` (port 6432)
- Configuration in `ctdbcfg.php`
- Docker image: `php:8.4-apache`

#### React Frontend (`ansible/files/react-app/`)

Modern React + TypeScript + Vite frontend at https://db.cue.tools/ui/

Key features:
- Displays latest/popular CD entries
- Real-time statistics dashboard (consumes Go API)
- Metadata lookup via MusicBrainz, CTDB, Discogs
- Track details with CRC checksums
- shadcn/ui components for UI elements

Technology stack:
- React 19.2 + TypeScript
- Vite 7.2 for bundling
- Tailwind CSS 4.1 for styling
- shadcn/ui components (Radix UI primitives)
- Recharts for statistics visualization

API Integration:
- Statistics: `/api/stats` (Go backend)
- CD listings: `/api/latest`, `/api/top` (Go backend)
- Legacy endpoints: PHP backend for XML compatibility

Important configuration:
- Base path: `/ui/`
- Vite proxy configuration for HMR over WSS
- Production: `react-prod` container (nginx)
- Development: `react-dev` container (Vite dev server on port 80)

#### Authentication System

The application uses Google OAuth 2.0 with JWT tokens for authentication.

**Backend (`internal/auth/`):**
- `config.go` - OAuth2 and JWT configuration from environment variables
- `jwt.go` - Token generation/validation (24-hour expiry, HMAC-SHA256)
- `middleware.go` - `RequireAuth()` and `RequireRole()` middleware
- `internal/handlers/auth.go` - OAuth handlers (login, callback, logout, me)
- `internal/database/users.go` - User lookup and last login tracking

**Database:**
- `users` table in `ctdb` database (email PK, role, timestamps)
- Permissions: `GRANT SELECT, UPDATE ON users TO ctdb_user`

**Auth endpoints:**
- `GET /api/auth/login` - Redirects to Google OAuth
- `GET /api/auth/callback` - Handles OAuth callback, sets JWT cookie
- `POST /api/auth/logout` - Clears auth cookie
- `GET /api/auth/me` - Returns current user (protected)

**Frontend:**
- `LoginButton.tsx` - Icon-only login button with tooltip
- `UserMenu.tsx` - User popover with email/role and logout
- Auth state in `App.tsx` - Checks `/api/auth/me` on mount

**Environment variables (required):**
- `GOOGLE_CLIENT_ID` - OAuth2 client ID from Google Cloud Console
- `GOOGLE_CLIENT_SECRET` - OAuth2 client secret
- `GOOGLE_REDIRECT_URL` - e.g., `https://dev.db.cue.tools/api/auth/callback`
- `JWT_SECRET` - Random key for signing tokens (generate with `openssl rand -base64 32`)
- `COOKIE_DOMAIN` - e.g., `.db.cue.tools`

**Security:**
- httpOnly cookies (no JavaScript access)
- Secure flag (HTTPS only), SameSite=Lax
- Manual user authorization (add users to database)
- No self-registration

**Adding users:**
```sql
INSERT INTO users (email, role) VALUES ('user@example.com', 'admin');
```

#### Go Applications

1. **ctdbweb-go** (`utils/docker/ctdbweb-go/`)
   - Main JSON API backend (documented above)
   - Serves `/api/*` endpoints
   - Multi-database support (CTDB, MusicBrainz, Discogs, FreeDB)

2. **s3uploader** (`utils/s3uploader/`)
   - Uploads parity files from local storage to S3 bucket `p.cuetools.net`
   - Processes batches of 10 submissions at a time
   - Updates `submissions2.s3` flag in database
   - Connection: `dbname=ctdb user=ctdb_user host=/var/run/postgresql port=6432`

3. **discogs2psql** (`utils/discogs/discogs2psql/`)
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

PHP code is mounted from the host, so changes to `.php` files are reflected immediately without restart.

```bash
# View PHP error logs (development)
docker logs ctdbweb-dev

# Check PHP version and modules (debugging)
docker exec ctdbweb-dev php -v
docker exec ctdbweb-dev php -m

# Test PHP endpoints in development
docker exec ctdbweb-dev curl -s "http://localhost/lookup2.php?toc=1+11+242457+150+26572+49252+68002+88955+107697+131380+149575+165992+192925" | head -10

# Or test via dev proxy
curl "https://dev.db.cue.tools/lookup2.php?toc=1+11+242457+150+26572+49252+68002+88955+107697+131380+149575+165992+192925" -k
```

**Note:** Use `ctdbweb-dev` for development and testing. The `ctdbweb` container is production - never restart or modify it directly.

### Go Backend

**Development workflow (with hot-reload):**

```bash
# Dev container runs automatically with Air hot-reload
docker logs -f ctdbweb-go-dev

# Edit code - Air detects changes and rebuilds (~1 second)
vim utils/docker/ctdbweb-go/internal/handlers/stats.go

# Test dev endpoint
curl "https://dev.db.cue.tools/api/stats?type=totals" -k

# Or test locally in container
docker exec ctdbweb-go-dev wget -qO- "http://localhost:8080/api/stats?type=totals"
```

**Local development (outside Docker):**

```bash
cd utils/docker/ctdbweb-go

# Install Air for hot-reload
go install github.com/air-verse/air@v1.61.1

# Run with Air
air -c .air.toml

# Or build and run directly
go build -o server ./cmd/server
PORT=8080 POSTGRES_HOST=pgbouncer POSTGRES_PORT=6432 ./server
```

**Testing endpoints:**

```bash
# Statistics
curl "http://localhost:8080/api/stats?type=totals"
curl "http://localhost:8080/api/stats?type=drives"
curl "http://localhost:8080/api/stats?type=agents"
curl "http://localhost:8080/api/stats?type=pregaps"
curl "http://localhost:8080/api/stats?type=submissions&count=7"
curl "http://localhost:8080/api/stats?type=submissions&count=24&hourly=1"

# CD listings
curl "http://localhost:8080/api/latest?limit=5"
curl "http://localhost:8080/api/top?limit=5"

# Health check
curl "http://localhost:8080/health"
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

**IMPORTANT:** All production deployments MUST go through Ansible. Never use docker-compose or direct docker commands for production changes.

The application is deployed via Ansible playbooks:

```bash
# Deploy entire stack
ansible-playbook ansible/playbook.yml

# Deploy specific component (start at a specific task)
ansible-playbook ansible/playbook.yml --start-at-task="Build React production container image"

# Deploy MusicBrainz mirror
ansible-playbook ansible/musicbrainz.yml
```

Deployment workflow:
1. **Development**: Test changes in dev containers (ctdbweb-go-dev, react-dev)
   - Dev containers have hot-reload for rapid iteration
   - Accessible at dev.db.cue.tools subdomain
2. **Production**: Deploy via Ansible once changes are tested
   - Ansible rebuilds production Docker images
   - Restarts production containers with new images
   - Ensures consistent deployment across environments

Key deployment features:
- Automatic database backup restore from S3 (`backups.cuetools.net`)
- SSL certificates via Certbot (Let's Encrypt DNS-01 with Route53)
- Docker container orchestration
- PGBouncer connection pooling
- Force rebuild with `force_source: true` in docker_image tasks

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

**Go Backend (JSON API)** - Returns clean, structured JSON:

```json
// GET /api/stats?type=totals
{"unique_tocs": 7585649, "submissions": 90513228}

// GET /api/stats?type=drives
[{"drive": "HL-DT-STDVDRAM", "count": 10898}, ...]

// GET /api/latest?limit=2
[
  {
    "id": 12616939,
    "artist": "Unknown Artist",
    "title": "Unknown Title",
    "tocid": "...",
    "crc32": -1978947008,
    "track_crcs": [123456, 789012, ...]
  }
]
```

**PHP Backend (Legacy XML/Google Viz format)** - Returns Google Visualization API format:

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

The React frontend consumes the Go JSON API for modern features.

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
│   │   ├── ctdbweb-go/         # Go JSON API backend
│   │   │   ├── cmd/            # Main application
│   │   │   ├── internal/       # Handlers, database, models, TOC lib
│   │   │   ├── pkg/            # PostgreSQL array parser
│   │   │   ├── Dockerfile      # Multi-stage build (dev + prod)
│   │   │   ├── .air.toml       # Hot-reload config
│   │   │   └── go.mod          # Go 1.23 dependencies
│   │   ├── ctdbweb/            # PHP backend + Dockerfile
│   │   ├── proxy/              # Apache reverse proxy config
│   │   ├── pgbouncer/          # Connection pooler config
│   │   ├── mediawiki/          # Wiki container
│   │   └── migrations/         # SQL migration scripts
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
- PGBouncer pools connections on port 6432 (accessible via both Unix socket and TCP)
- Use `ctdb_user` role for application connections

**Connection methods:**
- PHP: Unix socket `host=/var/run/postgresql port=6432`
- Go: TCP `host=pgbouncer port=6432`
- Connection string: `dbname=ctdb user=ctdb_user host=pgbouncer port=6432 sslmode=disable`

### Backup and Restore

- Automated backups stored in S3 bucket `backups.cuetools.net`
- `LATEST` file contains current backup version
- Backups in PostgreSQL custom format (`pg_dump -Fc`)
- Ansible playbook auto-restores database on fresh deployment

### Production URLs

- Main site: https://db.cue.tools/
- JSON API: https://db.cue.tools/api/ (Go backend)
- React UI: https://db.cue.tools/ui/
- Wiki: https://cue.tools/
- Log viewer: https://db.cue.tools/logs/ (HTTP auth required)

**Development URLs:**
- Dev API: https://dev.db.cue.tools/api/ (Go dev backend with hot-reload)
- Dev UI: https://dev.db.cue.tools/ui/ (React dev with HMR)

### Docker Network

All services communicate via Docker network `ct`. Internal hostnames:
- `postgres16` - PostgreSQL database
- `pgbouncer` - Connection pooler (port 6432)
- `ctdbweb-go` - Go production backend (port 8080)
- `ctdbweb-go-dev` - Go dev backend with hot-reload (port 8080)
- `ctdbweb` - PHP production backend (port 80)
- `ctdbweb-dev` - PHP dev backend (port 80)
- `react-prod` - React production UI (nginx, port 80)
- `react-dev` - React dev server (Vite, port 80)
- `proxy` - Reverse proxy (externally accessible, ports 80/443)
- `adminer` - Database admin UI

**Routing via proxy:**
- `db.cue.tools/api/*` → ctdbweb-go:8080 (Go production JSON API)
- `dev.db.cue.tools/api/*` → ctdbweb-go-dev:8080 (Go dev JSON API)
- `db.cue.tools/ui/*` → react-prod:80 (React production UI)
- `dev.db.cue.tools/ui/*` → react-dev:80 (React dev UI with HMR)
- `db.cue.tools/*` → ctdbweb:80 (PHP production legacy endpoints)
- `dev.db.cue.tools/*` → ctdbweb-dev:80 (PHP dev legacy endpoints)

All backend containers use internal ports only (no external exposure).

### Security Considerations

- PostgreSQL uses trust authentication within Docker network
- Apache proxy handles TLS termination
- Secrets managed via AWS Secrets Manager
- Log viewer protected by HTTP basic auth (htpasswd)
- No direct port exposure - all backend access via Apache proxy

## Migration Notes

### PHP to Go Backend Migration

The project is actively migrating from PHP to Go for improved performance and type safety.

**Completed:**
- ✅ Statistics API (`/api/stats`) - All 5 stat types (totals, drives, agents, pregaps, submissions)
- ✅ Latest/Top listings (`/api/latest`, `/api/top`)
- ✅ React frontend updated to consume Go JSON APIs
- ✅ Development environment with Air hot-reload
- ✅ Production deployment via Ansible

**In Progress:**
- 🚧 Metadata lookup API (`/api/lookup`) - TOC transformation and parallel queries implemented
- 🚧 Submission API (`/api/submit`) - Handler structure in place

**Legacy PHP endpoints (maintained for compatibility):**
- `submit2.php` - CD submission endpoint (production)
- `lookup2.php` - XML metadata lookup (used by legacy clients)

**PHP codebase cleanup (completed):**
- ✅ Removed 15 legacy PHP files (disabled pages, unused HTML UI, orphaned templates)
- ✅ Reduced PHP codebase by ~79% (14 files → 4 core files)
- ✅ Kept only critical API endpoints and dependencies

**Migration Benefits:**
- 3-4x lower memory usage (30-50 MB vs 150-200 MB)
- 10x smaller Docker image (50 MB vs 500+ MB)
- Parallel database queries (goroutines vs sequential PHP)
- Compile-time type safety
- Clean JSON API (vs Google Visualization API format)
- Hot-reload development (~1 second rebuild vs full restart)
