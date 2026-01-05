import { useState, useEffect } from 'react'

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
    // Handle arrays of objects (e.g., Release: [{country, date}], Label: [{name, catno}])
    return value
      .map(item => {
        if (typeof item === 'object' && item !== null) {
          // For Label: show "name (catno)" or just "name"
          if ('name' in item) {
            const name = (item as { name?: string }).name || ''
            const catno = (item as { catno?: string }).catno
            return catno ? `${name} (${catno})` : name
          }
          // For Release: show "country: date" or just available info
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

function App() {
  const [data, setData] = useState<ApiResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [selectedRow, setSelectedRow] = useState<number | null>(null)
  const [metadata, setMetadata] = useState<ApiResponse | null>(null)
  const [metadataLoading, setMetadataLoading] = useState(false)

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
      return
    }

    // TOC is at column index 7
    const tocIndex = data.cols.findIndex(col => col.label === 'TOC')
    if (tocIndex === -1) return

    const toc = data.rows[selectedRow].c[tocIndex].v
    if (!toc) return

    setMetadataLoading(true)
    setMetadata(null)

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
      })
      .catch(() => {
        setMetadata(null)
        setMetadataLoading(false)
      })
  }, [selectedRow, data])

  const handleRowClick = (rowIndex: number) => {
    setSelectedRow(selectedRow === rowIndex ? null : rowIndex)
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
          <h2>Metadata</h2>
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
                    <tr key={rowIndex}>
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
    </div>
  )
}

export default App
