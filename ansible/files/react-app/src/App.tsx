import { useState, useEffect, useMemo } from 'react'
import { tocs2mbid, tocs2mbtoc, buildTracks, type Track } from '@/lib/toc'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

interface Column {
  label: string
  type: string
}

interface Cell {
  v: unknown
}

interface Row {
  c: Cell[]
}

interface ApiResponse {
  cols: Column[]
  rows: Row[]
}

// Helper to format cell values (handles objects like Release and Label)
function formatCellValue(value: unknown): string {
  if (value === null || value === undefined) {
    return ''
  }
  if (typeof value === 'string' || typeof value === 'number') {
    return String(value)
  }
  if (Array.isArray(value)) {
    return value
      .map(item => {
        if (typeof item === 'object' && item !== null) {
          if ('name' in item) {
            const name = (item as { name?: string }).name || ''
            const catno = (item as { catno?: string }).catno
            return catno ? `${name} (${catno})` : name
          }
          if ('country' in item || 'date' in item) {
            const country = (item as { country?: string }).country || ''
            const date = (item as { date?: string }).date || ''
            return [country, date].filter(Boolean).join(': ')
          }
          return JSON.stringify(item)
        }
        return String(item)
      })
      .filter(Boolean)
      .join(', ')
  }
  if (typeof value === 'object') {
    return JSON.stringify(value)
  }
  return String(value)
}

const METADATA_PAGE_SIZE = 5

type ViewMode = 'latest' | 'popular'

const VIEW_ENDPOINTS: Record<ViewMode, string> = {
  latest: '/index.php',
  popular: '/top.php',
}

function App() {
  const [viewMode, setViewMode] = useState<ViewMode>('latest')
  const [data, setData] = useState<ApiResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [selectedRow, setSelectedRow] = useState<number | null>(null)
  const [metadata, setMetadata] = useState<ApiResponse | null>(null)
  const [metadataLoading, setMetadataLoading] = useState(false)
  const [selectedMetadataRow, setSelectedMetadataRow] = useState<number | null>(null)
  const [metadataPage, setMetadataPage] = useState(0)
  const [selectedEntryInfo, setSelectedEntryInfo] = useState<{
    discId: string
    toc: string
    mbtoc: string
    mbid: string | null
    mbUrl: string
    ctdbUrl: string
  } | null>(null)

  // Fetch data when view mode changes
  useEffect(() => {
    setLoading(true)
    setError(null)
    setSelectedRow(null)
    setSelectedEntryInfo(null)
    setMetadata(null)

    fetch(`${VIEW_ENDPOINTS[viewMode]}?json=1&start=0`)
      .then(response => {
        if (!response.ok) {
          throw new Error('Failed to fetch data')
        }
        return response.json()
      })
      .then((json: ApiResponse) => {
        setData(json)
        setLoading(false)
      })
      .catch(err => {
        setError(err.message)
        setLoading(false)
      })
  }, [viewMode])

  // Fetch metadata when a row is selected
  useEffect(() => {
    if (selectedRow === null || !data) {
      setMetadata(null)
      setSelectedMetadataRow(null)
      return
    }

    const tocIndex = data.cols.findIndex(col => col.label === 'TOC')
    if (tocIndex === -1) return

    const toc = data.rows[selectedRow].c[tocIndex].v
    if (!toc) return

    setMetadataLoading(true)
    setMetadata(null)
    setSelectedMetadataRow(null)
    setMetadataPage(0)

    fetch(`/lookup2.php?version=3&ctdb=0&metadata=default&fuzzy=1&type=json&toc=${encodeURIComponent(String(toc))}`)
      .then(response => {
        if (!response.ok) {
          throw new Error('Failed to fetch metadata')
        }
        return response.json()
      })
      .then((json: ApiResponse) => {
        setMetadata(json)
        setMetadataLoading(false)
        // Auto-select first row if available
        if (json.rows.length > 0) {
          setSelectedMetadataRow(0)
        }
      })
      .catch(() => {
        setMetadata(null)
        setMetadataLoading(false)
      })
  }, [selectedRow, data])

  // Compute selected entry info (including async mbid) when row is selected
  useEffect(() => {
    if (selectedRow === null || !data) {
      setSelectedEntryInfo(null)
      return
    }

    const tocIndex = data.cols.findIndex(col => col.label === 'TOC')
    const discIdIndex = data.cols.findIndex(col => col.label === 'Disc Id')

    if (tocIndex === -1 || discIdIndex === -1) {
      setSelectedEntryInfo(null)
      return
    }

    const toc = String(data.rows[selectedRow].c[tocIndex].v || '')
    const discId = String(data.rows[selectedRow].c[discIdIndex].v || '')
    const mbtoc = tocs2mbtoc(toc)

    // Set initial info with null mbid
    const info = {
      discId,
      toc,
      mbtoc,
      mbid: null as string | null,
      mbUrl: `https://musicbrainz.org/bare/cdlookup.html?toc=${encodeURIComponent(mbtoc)}`,
      ctdbUrl: `/lookup2.php?version=3&ctdb=1&metadata=extensive&fuzzy=1&toc=${encodeURIComponent(toc)}`,
    }
    setSelectedEntryInfo(info)

    // Compute mbid async and update
    tocs2mbid(toc)
      .then(mbid => setSelectedEntryInfo(prev => prev ? { ...prev, mbid } : null))
      .catch(() => {})
  }, [selectedRow, data])

  // Build tracks data
  const tracks = useMemo<Track[] | null>(() => {
    if (selectedRow === null || !data || !metadata || selectedMetadataRow === null) {
      return null
    }

    const tocIndex = data.cols.findIndex(col => col.label === 'TOC')
    const crcsIndex = data.cols.findIndex(col => col.label === 'Track CRCs')
    const tracklistIndex = metadata.cols.findIndex(col => col.label.toLowerCase() === 'tracks')
    const artistIndex = metadata.cols.findIndex(col => col.label.toLowerCase() === 'artist')

    if (tocIndex === -1) return null

    const tocString = String(data.rows[selectedRow].c[tocIndex].v || '')
    const crcsString = crcsIndex !== -1 ? String(data.rows[selectedRow].c[crcsIndex].v || '') : null
    const tracklist = tracklistIndex !== -1 
      ? (metadata.rows[selectedMetadataRow].c[tracklistIndex]?.v as Array<{ name?: string; artist?: string }> | null)
      : null
    const mainArtist = artistIndex !== -1
      ? String(metadata.rows[selectedMetadataRow].c[artistIndex]?.v || '')
      : null

    return buildTracks(tocString, crcsString, tracklist, mainArtist)
  }, [selectedRow, data, metadata, selectedMetadataRow])

  const handleRowClick = (rowIndex: number) => {
    setSelectedRow(selectedRow === rowIndex ? null : rowIndex)
  }

  const handleMetadataRowClick = (rowIndex: number) => {
    setSelectedMetadataRow(selectedMetadataRow === rowIndex ? null : rowIndex)
  }

  if (loading) {
    return (
      <div className="container">
        <h1>CUETools DB</h1>
        <p className="loading">Loading...</p>
      </div>
    )
  }

  if (error) {
    return (
      <div className="container">
        <h1>CUETools DB</h1>
        <p className="error">Error: {error}</p>
      </div>
    )
  }

  if (!data) {
    return (
      <div className="container">
        <h1>CUETools DB</h1>
        <p>No data available</p>
      </div>
    )
  }

  // Columns to hide in main table
  const hiddenColumns = ['CRC32', 'TOC', 'Track CRCs']
  const visibleColIndices = data.cols
    .map((col, index) => ({ col, index }))
    .filter(({ col }) => !hiddenColumns.includes(col.label))
    .map(({ index }) => index)

  // Columns to hide in metadata table
  const hiddenMetadataColumns = ['id', 'source', 'coverart', 'videos', 'tracklist', 'tracks']
  const visibleMetadataColIndices = metadata
    ? metadata.cols
        .map((col, index) => ({ col, index }))
        .filter(({ col }) => !hiddenMetadataColumns.includes(col.label.toLowerCase()))
        .map(({ index }) => index)
    : []

  // Pagination for metadata table
  const metadataTotalPages = metadata ? Math.ceil(metadata.rows.length / METADATA_PAGE_SIZE) : 0
  const metadataStartIndex = metadataPage * METADATA_PAGE_SIZE
  const metadataPageRows = metadata
    ? metadata.rows.slice(metadataStartIndex, metadataStartIndex + METADATA_PAGE_SIZE)
    : []

  return (
    <div className="container">
      <header className="page-header">
        <h1>CUETools DB</h1>
        <div className="view-selector">
          <span className="view-label">View:</span>
          <Select value={viewMode} onValueChange={(v: string) => setViewMode(v as ViewMode)}>
            <SelectTrigger className="view-select-trigger">
              <SelectValue />
            </SelectTrigger>
            <SelectContent position="popper" side="bottom" align="start">
              <SelectItem value="latest">Latest</SelectItem>
              <SelectItem value="popular">Popular</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </header>
      <div className="table-wrapper">
        <table>
          <thead>
            <tr>
              {visibleColIndices.map((colIndex) => (
                <th key={colIndex}>{data.cols[colIndex].label}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {data.rows.map((row, rowIndex) => (
              <tr
                key={rowIndex}
                onClick={() => handleRowClick(rowIndex)}
                className={selectedRow === rowIndex ? 'selected' : ''}
              >
                {visibleColIndices.map((colIndex) => (
                  <td key={colIndex}>{formatCellValue(row.c[colIndex].v)}</td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Links box */}
      {selectedEntryInfo && (
        <div className="links-box">
          <a
            href={selectedEntryInfo.mbUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="link-item mb-link"
          >
            <span className="link-label">MusicBrainz</span>
            <span className="link-value">{selectedEntryInfo.mbid || '...'}</span>
          </a>
          <a
            href={selectedEntryInfo.ctdbUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="link-item ctdb-link"
          >
            <span className="link-label">CTDB Lookup</span>
            <span className="link-value">{selectedEntryInfo.discId}</span>
          </a>
        </div>
      )}

      {/* Metadata table */}
      {selectedRow !== null && (
        <div className="metadata-section">
          {metadataLoading && <p className="loading">Loading metadata...</p>}
          {!metadataLoading && metadata && metadata.rows.length > 0 && (
            <>
              <div className="table-wrapper metadata-table">
                <table>
                  <thead>
                    <tr>
                      {visibleMetadataColIndices.map((colIndex) => (
                        <th key={colIndex}>{metadata.cols[colIndex].label}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {metadataPageRows.map((row, pageRowIndex) => {
                      const actualRowIndex = metadataStartIndex + pageRowIndex
                      return (
                        <tr
                          key={actualRowIndex}
                          onClick={() => handleMetadataRowClick(actualRowIndex)}
                          className={selectedMetadataRow === actualRowIndex ? 'selected' : ''}
                        >
                          {visibleMetadataColIndices.map((colIndex) => (
                            <td key={colIndex}>{formatCellValue(row.c[colIndex]?.v)}</td>
                          ))}
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
              {metadataTotalPages > 1 && (
                <div className="pagination">
                  <button
                    onClick={() => setMetadataPage(p => Math.max(0, p - 1))}
                    disabled={metadataPage === 0}
                  >
                    ← Prev
                  </button>
                  <span className="page-info">
                    {metadataPage + 1} / {metadataTotalPages}
                  </span>
                  <button
                    onClick={() => setMetadataPage(p => Math.min(metadataTotalPages - 1, p + 1))}
                    disabled={metadataPage >= metadataTotalPages - 1}
                  >
                    Next →
                  </button>
                </div>
              )}
            </>
          )}
          {!metadataLoading && (!metadata || metadata.rows.length === 0) && (
            <p className="no-metadata">No metadata found</p>
          )}
        </div>
      )}

      {/* Tracks table */}
      {tracks && tracks.length > 0 && (
        <div className="tracks-section">
          <h2>Tracks</h2>
          <div className="table-wrapper tracks-table">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Track</th>
                  <th>Start</th>
                  <th>Length</th>
                  <th>CRC</th>
                </tr>
              </thead>
              <tbody>
                {tracks.map((track) => (
                  <tr key={track.number} className={track.isDataTrack ? 'data-track' : ''}>
                    <td>{track.number}</td>
                    <td>
                      <span className={track.isDataTrack ? 'data-track-name' : ''}>
                        {track.name}
                      </span>
                      {track.artist && <span className="track-artist"> ({track.artist})</span>}
                    </td>
                    <td className="mono">{track.start}</td>
                    <td className="mono">{track.length}</td>
                    <td className="mono">{track.crc}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  )
}

export default App
