import { useState, useEffect, useMemo } from 'react'

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

interface Track {
  number: number
  name: string
  artist: string | null
  start: string
  length: string
  startSector: number
  endSector: number
  crc: string
  isDataTrack: boolean
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

// Convert sectors to time string (MM:SS.FF)
function sectorsToTime(sectors: number): string {
  const totalSeconds = sectors / 75
  const minutes = Math.floor(totalSeconds / 60)
  const seconds = Math.floor(totalSeconds % 60)
  const frames = sectors % 75
  return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}.${frames.toString().padStart(2, '0')}`
}

// Build tracks from TOC, CRCs, and tracklist
function buildTracks(
  tocString: string,
  crcsString: string | null,
  tracklist: Array<{ name?: string; artist?: string }> | null,
  mainArtist: string | null
): Track[] {
  const toc = tocString.split(':')
  const crcs = crcsString ? crcsString.split(' ') : []
  const tracks: Track[] = []
  const ntracks = toc.length - 1

  let crcIndex = 0
  for (let i = 0; i < ntracks; i++) {
    const isDataTrack = toc[i].startsWith('-')
    const startOffset = 150 + Math.abs(Number(toc[i]))
    let endOffset = 149 + Math.abs(Number(toc[i + 1]))
    if (toc[i + 1].startsWith('-')) {
      endOffset -= 11400
    }

    const trackInfo = tracklist?.[i]
    const trackArtist = trackInfo?.artist
    const showArtist = trackArtist && trackArtist !== mainArtist ? trackArtist : null

    tracks.push({
      number: i + 1,
      name: trackInfo?.name || (isDataTrack ? '[data track]' : ''),
      artist: showArtist,
      start: sectorsToTime(startOffset),
      length: sectorsToTime(endOffset + 1 - startOffset),
      startSector: startOffset,
      endSector: endOffset,
      crc: !isDataTrack && crcIndex < crcs.length ? crcs[crcIndex] : '',
      isDataTrack,
    })

    if (!isDataTrack) {
      crcIndex++
    }
  }

  return tracks
}

function App() {
  const [data, setData] = useState<ApiResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [selectedRow, setSelectedRow] = useState<number | null>(null)
  const [metadata, setMetadata] = useState<ApiResponse | null>(null)
  const [metadataLoading, setMetadataLoading] = useState(false)
  const [selectedMetadataRow, setSelectedMetadataRow] = useState<number | null>(null)

  useEffect(() => {
    fetch('/index.php?json=1&start=0')
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
  }, [])

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

  // Build tracks data
  const tracks = useMemo(() => {
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
  const hiddenMetadataColumns = ['id', 'source', 'coverart', 'videos', 'tracklist']
  const visibleMetadataColIndices = metadata
    ? metadata.cols
        .map((col, index) => ({ col, index }))
        .filter(({ col }) => !hiddenMetadataColumns.includes(col.label.toLowerCase()))
        .map(({ index }) => index)
    : []

  return (
    <div className="container">
      <h1>CUETools DB</h1>
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

      {/* Metadata table */}
      {selectedRow !== null && (
        <div className="metadata-section">
          {metadataLoading && <p className="loading">Loading metadata...</p>}
          {!metadataLoading && metadata && metadata.rows.length > 0 && (
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
                  {metadata.rows.map((row, rowIndex) => (
                    <tr
                      key={rowIndex}
                      onClick={() => handleMetadataRowClick(rowIndex)}
                      className={selectedMetadataRow === rowIndex ? 'selected' : ''}
                    >
                      {visibleMetadataColIndices.map((colIndex) => (
                        <td key={colIndex}>{formatCellValue(row.c[colIndex]?.v)}</td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
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
