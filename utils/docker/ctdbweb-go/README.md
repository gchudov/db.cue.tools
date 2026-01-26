# CTDB Web Go Backend

Go-based JSON API backend for CUETools Database (db.cue.tools). Provides high-performance metadata lookups across MusicBrainz, Discogs, and FreeDB databases.

## Quick Start

**Production:**
```bash
docker-compose up -d ctdbweb-go
curl http://localhost:8080/api/stats?type=totals
```

**Development (with hot-reload):**
```bash
docker-compose --profile dev up -d ctdbweb-go-dev
# Edit code → Auto-rebuilds in ~1 second
curl http://localhost:8081/health
```

## API Endpoints

All accessible via `https://db.cue.tools/api/`:

- `GET /api/lookup?toc=...&metadata=default&fuzzy=1` - Metadata lookup
- `GET /api/latest?limit=10` - Latest CD submissions  
- `GET /api/top?limit=10` - Popular CDs
- `GET /api/stats?type=totals` - Database statistics
- `POST /api/submit` - Submit CD (placeholder)

## Deployment

Via Ansible (recommended):
```bash
cd /opt/db.cue.tools
ansible-playbook ansible/playbook.yml
```

Manual:
```bash
docker-compose build ctdbweb-go
docker-compose up -d ctdbweb-go
docker restart proxy  # Apply Apache config
```

## Development

Edit code in `internal/` or `cmd/` → Air auto-rebuilds → Test at http://localhost:8081

## See Full README

For complete documentation, architecture details, troubleshooting, and API examples, see the full README at:
https://github.com/cuetools/ctdbweb-go/blob/main/README.md

Or view inline documentation in source files.
