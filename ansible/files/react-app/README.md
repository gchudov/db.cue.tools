# CUETools DB React Frontend

A React + TypeScript + Vite application that provides a modern interface for browsing the CUETools Database (CTDB).

## Overview

This app is a React reimplementation of the PHP-based CUETools DB web interface. It displays CD entries from the database, allows metadata lookups via MusicBrainz/CTDB, and shows track details.

### Features

- **Main Table**: Displays CD entries with disc ID, artist, title, confidence, etc.
- **View Modes**: Toggle between "Latest" and "Popular" entries
- **Metadata Lookup**: Click a row to fetch metadata from multiple sources (MusicBrainz, Discogs, etc.)
- **Track Details**: View individual track information with CRC checksums
- **MusicBrainz Integration**: Direct links to MusicBrainz disc ID lookups

## Development Environment

### Docker Container

The app runs in a Docker container named `react-dev` using `node:24-bookworm-slim`.

**Container configuration** (from `ansible/playbook.yml`):
```yaml
name: react-dev
image: node:24-bookworm-slim
network_mode: ct
volumes:
  - /opt/db.cue.tools/ansible/files/react-app:/app:rw
  - react_node_modules:/app/node_modules
working_dir: /app
command: sh -c "npm run dev -- --host 0.0.0.0 --port 80"
```

### Access URLs

- **Production URL**: https://db.cue.tools/ui/
- **Internal (within Docker network)**: http://react-dev:80/ui/

### Apache Reverse Proxy

The app is served through an Apache reverse proxy configured in:
`utils/docker/proxy/httpd.conf`

Key proxy configuration:
```apache
# WebSocket support for Vite HMR
RewriteEngine On
RewriteCond %{HTTP:Upgrade} websocket [NC]
RewriteCond %{HTTP:Connection} upgrade [NC]
RewriteRule ^/ui/(.*) "ws://react-dev:80/ui/$1" [P,L]

<Location /ui/>
  ProxyPass "http://react-dev:80/ui/"
  ProxyPassReverse "http://react-dev:80/ui/"
</Location>
```

### Vite Configuration

The app is configured to work behind the reverse proxy at `/ui/`:

```typescript
// vite.config.ts
export default defineConfig({
  base: "/ui/",
  server: {
    allowedHosts: ["react-dev"],
    origin: "https://db.cue.tools",
    hmr: {
      host: "db.cue.tools",
      protocol: "wss",
      clientPort: 443,
    },
  },
})
```

## Reference Implementation

### Go Backend
- **`utils/docker/ctdbweb-go/internal/handlers/lookup.go`** - Metadata lookup handler
- **`utils/docker/ctdbweb-go/internal/metadata/`** - Metadata clients (MusicBrainz, Discogs, FreeDB)

## Project Structure

```
src/
├── App.tsx              # Main application component
├── index.css            # Global styles (dark theme, glassmorphism)
├── main.tsx             # Entry point
├── components/
│   └── ui/              # shadcn/ui components
│       └── select.tsx   # Select/dropdown component
├── types/
│   └── metadata.ts      # TypeScript types for Go metadata API
└── lib/
    ├── toc.ts           # TOC/CTDB utilities (tocs2mbid, buildTracks, etc.)
    ├── toc.test.ts      # Unit tests for TOC utilities
    └── utils.ts         # General utilities
```

## UI Components

This project uses [shadcn/ui](https://ui.shadcn.com/) for UI components where appropriate. Prefer shadcn components over custom implementations for:
- Form controls (Select, Input, Checkbox, etc.)
- Dialogs and modals
- Dropdowns and popovers
- Any component that benefits from Radix UI's accessibility and positioning

Install new components via:
```bash
docker exec react-dev npx shadcn@latest add <component-name> --yes
```

## API Endpoints Used

| Endpoint | Purpose |
|----------|---------|
| `/api/lookup?metadata=default&fuzzy=1&toc=...` | Fetch metadata for a TOC |

## Running Locally

```bash
# Install dependencies
npm install

# Start dev server (inside Docker, runs on port 80)
npm run dev -- --host 0.0.0.0 --port 80

# Run tests
npm test
```

## Key Implementation Details

### TOC Utilities (`src/lib/toc.ts`)

- **`tocs2mbid(tocString)`**: Converts CTDB TOC format to MusicBrainz disc ID (async, uses SHA-1)
- **`tocs2mbtoc(tocString)`**: Converts TOC to MusicBrainz lookup URL format
- **`buildTracks(toc, crcs, tracklist, artist)`**: Builds track data from TOC and metadata
- **`sectorsToTime(sectors)`**: Converts CD sectors to MM:SS.FF format

### Data Formats

**Metadata endpoint** (/api/lookup) returns:
```json
[
  {
    "source": "musicbrainz",
    "id": "...",
    "artistname": "Artist Name",
    "albumname": "Album Name",
    "tracklist": [...],
    "coverart": [...],
    ...
  }
]
```

## Styling

- Dark theme with gradient background
- Glassmorphism effects on tables
- JetBrains Mono / Fira Code fonts
- Color-coded tables (blue for main, purple for metadata, green for tracks)
- Responsive design with horizontal scroll for tables
