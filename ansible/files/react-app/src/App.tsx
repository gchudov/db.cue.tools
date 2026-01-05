import { useState, useEffect, useMemo, useRef, useCallback } from 'react'
import { tocs2mbid, tocs2mbtoc, buildTracks, type Track } from '@/lib/toc'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Dialog, DialogContent } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Filter, Menu, X, Home, BarChart3, Info, MessageSquare, Plug, Wrench, ExternalLink, Heart } from 'lucide-react'

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

type ViewMode = 'latest' | 'popular'

const VIEW_ENDPOINTS: Record<ViewMode, string> = {
  latest: '/index.php',
  popular: '/top.php',
}

interface Filters {
  tocid: string
  artist: string
}

function App() {
  const [viewMode, setViewMode] = useState<ViewMode>('latest')
  const [filters, setFilters] = useState<Filters>({ tocid: '', artist: '' })
  const [pendingFilters, setPendingFilters] = useState<Filters>({ tocid: '', artist: '' })
  const [filterOpen, setFilterOpen] = useState(false)
  const [data, setData] = useState<ApiResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [loadingMore, setLoadingMore] = useState(false)
  const [hasMore, setHasMore] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const loadMoreRef = useRef<HTMLDivElement>(null)
  const [selectedRow, setSelectedRow] = useState<number | null>(null)
  const [metadata, setMetadata] = useState<ApiResponse | null>(null)
  const [metadataLoading, setMetadataLoading] = useState(false)
  const [selectedMetadataRow, setSelectedMetadataRow] = useState<number | null>(null)
  const [selectedEntryInfo, setSelectedEntryInfo] = useState<{
    discId: string
    toc: string
    mbtoc: string
    mbid: string | null
    mbUrl: string
    ctdbUrl: string
  } | null>(null)
  const [lightboxImage, setLightboxImage] = useState<string | null>(null)
  const [menuOpen, setMenuOpen] = useState(false)

  // Fetch initial data when view mode or filters change
  useEffect(() => {
    setLoading(true)
    setError(null)
    setSelectedRow(null)
    setSelectedEntryInfo(null)
    setMetadata(null)
    setHasMore(true)

    const params = new URLSearchParams({ json: '1', start: '0' })
    if (filters.tocid.trim()) {
      params.set('tocid', filters.tocid.trim())
    }
    if (filters.artist.trim()) {
      params.set('artist', filters.artist.trim())
    }

    fetch(`${VIEW_ENDPOINTS[viewMode]}?${params.toString()}`)
      .then(response => {
        if (!response.ok) {
          throw new Error('Failed to fetch data')
        }
        return response.json()
      })
      .then((json: ApiResponse) => {
        setData(json)
        setLoading(false)
        if (json.rows.length < 10) {
          setHasMore(false)
        }
      })
      .catch(err => {
        setError(err.message)
        setLoading(false)
      })
  }, [viewMode, filters])

  // Load more data function
  const loadMore = useCallback(() => {
    if (loadingMore || !hasMore || !data) return

    setLoadingMore(true)
    const nextStart = data.rows.length

    const params = new URLSearchParams({ json: '1', start: String(nextStart) })
    if (filters.tocid.trim()) {
      params.set('tocid', filters.tocid.trim())
    }
    if (filters.artist.trim()) {
      params.set('artist', filters.artist.trim())
    }

    fetch(`${VIEW_ENDPOINTS[viewMode]}?${params.toString()}`)
      .then(response => {
        if (!response.ok) {
          throw new Error('Failed to fetch data')
        }
        return response.json()
      })
      .then((json: ApiResponse) => {
        setData(prev => prev ? {
          ...prev,
          rows: [...prev.rows, ...json.rows]
        } : json)
        setLoadingMore(false)
        if (json.rows.length < 10) {
          setHasMore(false)
        }
      })
      .catch(() => {
        setLoadingMore(false)
      })
  }, [loadingMore, hasMore, data, filters, viewMode])

  // Intersection observer for infinite scroll
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting && hasMore && !loadingMore) {
          loadMore()
        }
      },
      { threshold: 0.1 }
    )

    const currentRef = loadMoreRef.current
    if (currentRef) {
      observer.observe(currentRef)
    }

    return () => {
      if (currentRef) {
        observer.unobserve(currentRef)
      }
    }
  }, [loadMore, hasMore, loadingMore])

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

  // Extract cover art from metadata
  interface CoverArtImage {
    uri: string
    uri150: string
  }

  const coverArt = useMemo<{ primary: CoverArtImage | null; secondary: CoverArtImage[] }>(() => {
    if (!metadata || selectedMetadataRow === null) return { primary: null, secondary: [] }

    const coverartIndex = metadata.cols.findIndex(col => col.label.toLowerCase() === 'coverart')
    if (coverartIndex === -1) return { primary: null, secondary: [] }

    const coverartList = metadata.rows[selectedMetadataRow]?.c[coverartIndex]?.v as Array<{
      uri?: string
      uri150?: string
      primary?: boolean
    }> | null

    if (!coverartList || coverartList.length === 0) return { primary: null, secondary: [] }

    // Filter out duplicates and invalid entries
    const seen = new Set<string>()
    const validImages: Array<{ uri: string; uri150: string; isPrimary: boolean }> = []
    
    for (const img of coverartList) {
      if (!img.uri150) continue
      // Skip Amazon images (as in ctdbCoverart)
      if (img.uri?.includes('images.amazon.com')) continue
      if (seen.has(img.uri150)) continue
      seen.add(img.uri150)
      validImages.push({
        uri: img.uri || img.uri150,
        uri150: img.uri150,
        isPrimary: img.primary || false,
      })
    }

    // Sort: primary first, then others
    validImages.sort((a, b) => (b.isPrimary ? 1 : 0) - (a.isPrimary ? 1 : 0))

    const primary = validImages[0] ? { uri: validImages[0].uri, uri150: validImages[0].uri150 } : null
    const secondary = validImages.slice(1).map(img => ({ uri: img.uri, uri150: img.uri150 }))

    return { primary, secondary }
  }, [metadata, selectedMetadataRow])

  const handleRowClick = (rowIndex: number) => {
    setSelectedRow(selectedRow === rowIndex ? null : rowIndex)
  }

  const handleMetadataRowClick = (rowIndex: number) => {
    setSelectedMetadataRow(selectedMetadataRow === rowIndex ? null : rowIndex)
  }

  const applyFilters = () => {
    setFilters(pendingFilters)
    setFilterOpen(false)
  }

  const clearFilters = () => {
    const empty = { tocid: '', artist: '' }
    setPendingFilters(empty)
    setFilters(empty)
    setFilterOpen(false)
  }

  const hasActiveFilters = filters.tocid.trim() !== '' || filters.artist.trim() !== ''

  const setTocidFilter = (tocid: string) => {
    const newFilters = { ...filters, tocid }
    setFilters(newFilters)
    setPendingFilters(newFilters)
  }

  const setArtistFilter = (artist: string) => {
    const newFilters = { ...filters, artist }
    setFilters(newFilters)
    setPendingFilters(newFilters)
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
  
  const discIdColIndex = data.cols.findIndex(col => col.label === 'Disc Id')
  const artistColIndex = data.cols.findIndex(col => col.label === 'Artist')

  // Columns to hide in metadata table
  const hiddenMetadataColumns = ['id', 'source', 'coverart', 'videos', 'tracklist', 'tracks']
  const visibleMetadataColIndices = metadata
    ? metadata.cols
        .map((col, index) => ({ col, index }))
        .filter(({ col }) => !hiddenMetadataColumns.includes(col.label.toLowerCase()))
        .map(({ index }) => index)
    : []

  return (
    <div className="container">
      {/* Slide-out menu */}
      <div className={`menu-overlay ${menuOpen ? 'open' : ''}`} onClick={() => setMenuOpen(false)} />
      <nav className={`side-menu ${menuOpen ? 'open' : ''}`}>
        <div className="side-menu-header">
          <span>Menu</span>
          <button className="menu-close-btn" onClick={() => setMenuOpen(false)}>
            <X className="size-5" />
          </button>
        </div>
        <div className="side-menu-items">
          <button className="menu-item active" onClick={() => setMenuOpen(false)}>
            <Home className="size-5" />
            <span>Home</span>
          </button>
          <a className="menu-item" href="/stats.php">
            <BarChart3 className="size-5" />
            <span>Stats</span>
          </a>
          <a className="menu-item" href="http://cue.tools/wiki/CUETools_Database" target="_blank" rel="noopener noreferrer">
            <Info className="size-5" />
            <span>About</span>
            <ExternalLink className="size-4 external-icon" />
          </a>
          <a className="menu-item" href="http://www.hydrogenaudio.org/forums/index.php?showtopic=79882" target="_blank" rel="noopener noreferrer">
            <MessageSquare className="size-5" />
            <span>Forum</span>
            <ExternalLink className="size-4 external-icon" />
          </a>
          <a className="menu-item" href="http://cue.tools/wiki/CTDB_EAC_Plugin" target="_blank" rel="noopener noreferrer">
            <Plug className="size-5" />
            <span>EAC Plugin</span>
            <ExternalLink className="size-4 external-icon" />
          </a>
          <a className="menu-item" href="http://cue.tools/wiki/CUETools" target="_blank" rel="noopener noreferrer">
            <Wrench className="size-5" />
            <span>CUETools</span>
            <ExternalLink className="size-4 external-icon" />
          </a>
          <div className="menu-divider" />
          <a className="menu-item sponsor" href="https://github.com/sponsors/gchudov" target="_blank" rel="noopener noreferrer">
            <Heart className="size-5" />
            <span>Sponsor</span>
            <ExternalLink className="size-4 external-icon" />
          </a>
        </div>
      </nav>

      <header className="page-header">
        <button className="menu-toggle-btn" onClick={() => setMenuOpen(true)}>
          <Menu className="size-6" />
        </button>
        <h1>CUETools DB</h1>
        <div className="header-controls">
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
          <Popover open={filterOpen} onOpenChange={setFilterOpen}>
            <PopoverTrigger asChild>
              <Button
                variant="outline"
                size="sm"
                className={`filter-button ${hasActiveFilters ? 'active' : ''}`}
              >
                <Filter className="size-4" />
                Filter
                {hasActiveFilters && <span className="filter-badge" />}
              </Button>
            </PopoverTrigger>
            <PopoverContent className="filter-popover" align="end" side="bottom">
              <div className="filter-form">
                <div className="filter-field">
                  <Label htmlFor="filter-tocid">TOCID</Label>
                  <Input
                    id="filter-tocid"
                    placeholder="e.g. ABC123..."
                    value={pendingFilters.tocid}
                    onChange={(e) => setPendingFilters(p => ({ ...p, tocid: e.target.value }))}
                  />
                </div>
                <div className="filter-field">
                  <Label htmlFor="filter-artist">Artist</Label>
                  <Input
                    id="filter-artist"
                    placeholder="e.g. Pink Floyd"
                    value={pendingFilters.artist}
                    onChange={(e) => setPendingFilters(p => ({ ...p, artist: e.target.value }))}
                  />
                </div>
                <div className="filter-actions">
                  <Button variant="ghost" size="sm" onClick={clearFilters}>
                    Clear
                  </Button>
                  <Button size="sm" onClick={applyFilters}>
                    Apply
                  </Button>
                </div>
              </div>
            </PopoverContent>
          </Popover>
        </div>
      </header>
      <div className="table-wrapper main-table">
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
                  <td key={colIndex}>
                    {colIndex === discIdColIndex ? (
                      <span className="filterable-cell">
                        <button
                          className="inline-filter-btn"
                          onClick={(e) => {
                            e.stopPropagation()
                            setTocidFilter(String(row.c[colIndex].v || ''))
                          }}
                          title="Filter by this Disc ID"
                        >
                          <Filter className="size-3" />
                        </button>
                        {formatCellValue(row.c[colIndex].v)}
                      </span>
                    ) : colIndex === artistColIndex ? (
                      <span className="filterable-cell">
                        <button
                          className="inline-filter-btn"
                          onClick={(e) => {
                            e.stopPropagation()
                            setArtistFilter(String(row.c[colIndex].v || ''))
                          }}
                          title="Filter by this Artist"
                        >
                          <Filter className="size-3" />
                        </button>
                        {formatCellValue(row.c[colIndex].v)}
                      </span>
                    ) : (
                      formatCellValue(row.c[colIndex].v)
                    )}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
        {/* Infinite scroll trigger */}
        <div ref={loadMoreRef} className="load-more-trigger">
          {loadingMore && <span className="loading-more">Loading more...</span>}
          {!hasMore && data.rows.length > 0 && <span className="no-more">No more entries</span>}
        </div>
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
            </>
          )}
          {!metadataLoading && (!metadata || metadata.rows.length === 0) && (
            <p className="no-metadata">No metadata found</p>
          )}
        </div>
      )}

      {/* Tracks table with cover art */}
      {tracks && tracks.length > 0 && (
        <div className="tracks-section">
          <div className="tracks-layout">
            {coverArt.primary ? (
              <div className="cover-art">
                <button
                  type="button"
                  className="cover-art-primary"
                  onClick={() => setLightboxImage(coverArt.primary!.uri)}
                >
                  <img src={coverArt.primary.uri150} alt="Cover art" />
                </button>
                {coverArt.secondary.length > 0 && (
                  <div className="cover-art-secondary">
                    {coverArt.secondary.map((img, idx) => (
                      <button
                        key={idx}
                        type="button"
                        onClick={() => setLightboxImage(img.uri)}
                      >
                        <img src={img.uri150} alt={`Cover art ${idx + 2}`} />
                      </button>
                    ))}
                  </div>
                )}
              </div>
            ) : (
              <div className="cover-art-placeholder" />
            )}
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
        </div>
      )}

      {/* Cover art lightbox */}
      <Dialog modal={false} open={!!lightboxImage} onOpenChange={(open: boolean) => !open && setLightboxImage(null)}>
        <DialogContent
          className="lightbox-dialog"
          onInteractOutside={() => setLightboxImage(null)}
        >
          {lightboxImage && (
            <img src={lightboxImage} alt="Cover art full size" className="lightbox-image" />
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}

export default App
