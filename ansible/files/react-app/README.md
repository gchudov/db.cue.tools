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

- **Production URL**: https://db.cue.tools/react/
- **Internal (within Docker network)**: http://react-dev:80/react/

### Apache Reverse Proxy

The app is served through an Apache reverse proxy configured in:
`utils/docker/proxy/httpd.conf`

Key proxy configuration:
```apache
# WebSocket support for Vite HMR
RewriteEngine On
RewriteCond %{HTTP:Upgrade} websocket [NC]
RewriteCond %{HTTP:Connection} upgrade [NC]
RewriteRule ^/react/(.*) "ws://react-dev:80/react/$1" [P,L]

<Location /react/>
  ProxyPass "http://react-dev:80/react/"
  ProxyPassReverse "http://react-dev:80/react/"
</Location>
```

### Vite Configuration

The app is configured to work behind the reverse proxy at `/react/`:

```typescript
// vite.config.ts
export default defineConfig({
  base: "/react/",
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

## Reference PHP Implementation

This React app is modeled on the existing PHP implementation located at:

### Main Files
- **`utils/docker/ctdbweb/db.cue.tools/index.php`** - Latest entries endpoint (`?json=1&start=0`)
- **`utils/docker/ctdbweb/db.cue.tools/top.php`** - Popular entries endpoint (`?json=1&start=0`)
- **`utils/docker/ctdbweb/db.cue.tools/lookup2.php`** - Metadata lookup endpoint
- **`utils/docker/ctdbweb/db.cue.tools/list1.php`** - Original HTML table/JS logic for metadata display

### JavaScript Reference
- **`utils/docker/ctdbweb/db.cue.tools/s3/ctdb.js`** - Client-side JavaScript with:
  - `tocs2mbid()` - MusicBrainz disc ID calculation
  - `tocs2mbtoc()` - MusicBrainz TOC format conversion
  - `resetMetadata()` - Metadata fetching logic
  - Table rendering and row selection handlers

## Project Structure

```
src/
├── App.tsx              # Main application component
├── index.css            # Global styles (dark theme, glassmorphism)
├── main.tsx             # Entry point
├── components/
│   └── ui/              # shadcn/ui components
│       └── select.tsx   # Select/dropdown component
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
| `/index.php?json=1&start=0` | Fetch latest CD entries |
| `/top.php?json=1&start=0` | Fetch popular CD entries |
| `/lookup2.php?version=3&ctdb=0&metadata=default&fuzzy=1&toc=...` | Fetch metadata for a TOC |

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

### Data Format

The PHP endpoints return JSON in this format:
```json
{
  "cols": [
    { "label": "Disc Id", "type": "string" },
    { "label": "Artist", "type": "string" },
    ...
  ],
  "rows": [
    { "c": [{ "v": "abc123" }, { "v": "Artist Name" }, ...] },
    ...
  ]
}
```

## Styling

- Dark theme with gradient background
- Glassmorphism effects on tables
- JetBrains Mono / Fira Code fonts
- Color-coded tables (blue for main, purple for metadata, green for tracks)
- Responsive design with horizontal scroll for tables
