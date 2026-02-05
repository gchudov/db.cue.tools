import { useState, useEffect, useMemo, useRef, useCallback } from 'react'
import { tocs2mbid, tocs2mbtoc, tocs2cddbid, tocs2arid, buildTracks, type Track } from '@/lib/toc'
import type { Metadata } from '@/types/metadata'
import { Stats } from '@/components/Stats'
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
import { Filter, Menu, X, Home, BarChart3, Info, MessageSquare, Plug, Wrench, ExternalLink, Heart, RefreshCw, ScrollText } from 'lucide-react'
import { LoginButton } from '@/components/LoginButton'
import { UserMenu } from '@/components/UserMenu'
import { useSubmissionsWebSocket } from '@/hooks/useSubmissionsWebSocket'

type Page = 'home' | 'stats' | 'logs'

// Submission interface for /api/latest and /api/top
interface Submission {
  id: number
  artist: string
  title: string
  tocid: string
  first_audio: number
  audio_tracks: number
  track_count: number
  track_offsets: string
  sub_count: number
  crc32: number
  track_crcs?: number[]
  toc_formatted: string
  track_count_formatted: string
  track_crcs_formatted?: string
}

// Response format for /api/latest and /api/top
// Cursors are numbers for "latest" mode, strings ("subcount:id") for "top" mode
interface SubmissionsResponse {
  data: Submission[]
  cursors: { newest: number | string; oldest: number | string }
  has_more: boolean
}

// Clean JSON interface for recent submissions
interface RecentSubmission {
  id: number
  artist: string
  title: string
  tocid: string
  first_audio: number
  audio_tracks: number
  track_count: number
  track_offsets: string
  sub_count: number
  crc32: number
  time: string  // ISO 8601 timestamp
  agent?: string
  drivename?: string
  userid?: string
  ip?: string
  quality?: number | null
  barcode?: string
  toc_formatted: string
  track_count_string: string
}

// Convert country code to flag emoji
function countryToFlag(countryCode: string): string | null {
  if (!countryCode) return null
  const code = countryCode.toUpperCase().trim()
  // Special case: XE means worldwide
  if (code === 'XE') return '🇪🇺'
  if (code === 'XW') return '🌍'
  // Only convert valid 2-letter codes (A-Z only)
  if (code.length !== 2 || !/^[A-Z]{2}$/.test(code)) return null
  // Convert country code to regional indicator symbols
  // Regional Indicator Symbol A = U+1F1E6 = 127462, 'A' = 65, so offset = 127397
  const offset = 127397
  return String.fromCodePoint(
    code.charCodeAt(0) + offset,
    code.charCodeAt(1) + offset
  )
}

// Helper to format release data as flags with tooltips
interface ReleaseItem {
  country?: string
  date?: string
}

function formatReleaseValue(value: unknown): { flags: React.ReactNode; tooltip: string } {
  if (!Array.isArray(value)) {
    return { flags: '', tooltip: '' }
  }

  const releases = value as ReleaseItem[]
  const tooltipParts: string[] = []
  const flagElements: React.ReactNode[] = []

  releases.forEach((item, index) => {
    if (typeof item === 'object' && item !== null && ('country' in item || 'date' in item)) {
      const country = item.country || ''
      const date = item.date || ''
      const text = [country, date].filter(Boolean).join(': ')
      if (text) tooltipParts.push(text)
      
      const flag = countryToFlag(country)
      flagElements.push(
        <span key={index} className="release-flag" title={text}>
          {flag || country || '🌐'}
        </span>
      )
    }
  })

  return {
    flags: flagElements.length > 0 ? flagElements : tooltipParts.join(', '),
    tooltip: tooltipParts.join(', ')
  }
}

// Format timestamp as relative time (e.g., "2 hours ago")
function formatRelativeTime(date: Date): string {
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)

  if (diffMins < 1) return 'Just now'
  if (diffMins < 60) return `${diffMins}m ago`
  if (diffHours < 24) return `${diffHours}h ago`
  if (diffDays < 7) return `${diffDays}d ago`

  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  })
}

// Format timestamp as absolute date/time (e.g., "2026-02-04 14:23:45")
function formatAbsoluteTime(date: Date): string {
  return date.toLocaleString('en-US', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  })
}

// Format CRC32 as hexadecimal (e.g., 0xDEADBEEF)
function formatCRC32(crc32: number): string {
  // Convert signed int32 to unsigned and format as 8-digit hex
  return '0x' + (crc32 >>> 0).toString(16).toUpperCase().padStart(8, '0')
}

type ViewMode = 'latest' | 'popular'

const VIEW_ENDPOINTS: Record<ViewMode, string> = {
  latest: '/api/latest',
  popular: '/api/top',
}

interface Filters {
  tocid: string
  artist: string
}

// Helper to read state from URL parameters
function getInitialStateFromUrl() {
  const params = new URLSearchParams(window.location.search)
  const page = params.get('page') as Page | null
  const view = params.get('view') as ViewMode | null
  const tocid = params.get('tocid') || ''
  const artist = params.get('artist') || ''

  return {
    page: page === 'stats' ? 'stats' : page === 'logs' ? 'logs' : 'home' as Page,
    viewMode: view === 'popular' ? 'popular' : 'latest' as ViewMode,
    filters: { tocid, artist },
  }
}

// Helper to update URL without triggering navigation
function updateUrl(page: Page, viewMode: ViewMode, filters: Filters) {
  const params = new URLSearchParams()
  if (page !== 'home') params.set('page', page)
  if (viewMode !== 'latest') params.set('view', viewMode)
  if (filters.tocid.trim()) params.set('tocid', filters.tocid.trim())
  if (filters.artist.trim()) params.set('artist', filters.artist.trim())
  
  const newUrl = params.toString() 
    ? `${window.location.pathname}?${params.toString()}`
    : window.location.pathname
  window.history.replaceState({}, '', newUrl)
}

function App() {
  // Initialize state from URL
  const initialState = useMemo(() => getInitialStateFromUrl(), [])
  
  const [viewMode, setViewMode] = useState<ViewMode>(initialState.viewMode)
  const [filters, setFilters] = useState<Filters>(initialState.filters)
  const [pendingFilters, setPendingFilters] = useState<Filters>(initialState.filters)
  const [filterOpen, setFilterOpen] = useState(false)
  const [data, setData] = useState<Submission[]>([])
  const [oldestCursor, setOldestCursor] = useState<number | string>(0)
  const [loading, setLoading] = useState(true)
  const [loadingMore, setLoadingMore] = useState(false)
  const [hasMore, setHasMore] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const loadMoreRef = useRef<HTMLDivElement>(null)
  const [selectedRow, setSelectedRow] = useState<number | null>(null)
  const [metadata, setMetadata] = useState<Metadata[] | null>(null)
  const [metadataLoading, setMetadataLoading] = useState(false)
  const [selectedMetadataRow, setSelectedMetadataRow] = useState<number | null>(null)
  const [selectedEntryInfo, setSelectedEntryInfo] = useState<{
    discId: string
    toc: string
    mbtoc: string
    mbid: string | null
    cddbid: string
    arid: string
    mbUrl: string
    ctdbUrl: string
  } | null>(null)
  const [lightboxImage, setLightboxImage] = useState<string | null>(null)
  const [menuOpen, setMenuOpen] = useState(false)
  const [currentPage, setCurrentPage] = useState<Page>(initialState.page)
  const [refreshKey, setRefreshKey] = useState(0)

  // Auth state
  const [user, setUser] = useState<{ email: string; role: string } | null>(null)
  const [authLoading, setAuthLoading] = useState(true)

  // Submissions log state (admin only)
  const [submissions, setSubmissions] = useState<RecentSubmission[] | null>(null)
  const [submissionsLoading, setSubmissionsLoading] = useState(false)
  const [submissionsOpen, setSubmissionsOpen] = useState(true)
  const [submissionsLoadingMore, setSubmissionsLoadingMore] = useState(false)
  const [submissionsHasMore, setSubmissionsHasMore] = useState(true)
  const [submissionsOldestCursor, setSubmissionsOldestCursor] = useState<number>(0)
  const submissionsLoadMoreRef = useRef<HTMLDivElement>(null)

  // Logs page state (admin only)
  const [logsData, setLogsData] = useState<RecentSubmission[] | null>(null)
  const [logsLoading, setLogsLoading] = useState(false)
  const [logsLoadingMore, setLogsLoadingMore] = useState(false)
  const [logsHasMore, setLogsHasMore] = useState(true)
  const [logsNewestCursor, setLogsNewestCursor] = useState<number>(0)
  const [logsOldestCursor, setLogsOldestCursor] = useState<number>(0)
  const logsLoadMoreRef = useRef<HTMLDivElement>(null)
  const logsNewestCursorRef = useRef<number>(0)
  const logsFetchingNewRef = useRef<boolean>(false)
  const logsUpdateCountRef = useRef<number>(0)

  // WebSocket for logs page real-time updates
  const { updateCount: logsUpdateCount, isConnected: logsWsConnected } = useSubmissionsWebSocket()

  // Check authentication on mount
  useEffect(() => {
    fetch('/api/auth/me')
      .then(res => {
        if (res.ok) return res.json()
        throw new Error('Not authenticated')
      })
      .then(data => setUser(data))
      .catch(() => setUser(null))
      .finally(() => setAuthLoading(false))
  }, [])

  // Sync state changes to URL
  useEffect(() => {
    updateUrl(currentPage, viewMode, filters)
  }, [currentPage, viewMode, filters])

  // Fetch initial data when view mode or filters change
  useEffect(() => {
    setLoading(true)
    setError(null)
    setSelectedRow(null)
    setSelectedEntryInfo(null)
    setMetadata(null)
    setHasMore(true)
    setData([])
    setOldestCursor(0)

    const params = new URLSearchParams({ limit: '50' })
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
      .then((json: SubmissionsResponse) => {
        setData(json.data)
        setOldestCursor(json.cursors.oldest)
        setHasMore(json.has_more)
        setLoading(false)
      })
      .catch(err => {
        setError(err.message)
        setLoading(false)
      })
  }, [viewMode, filters, refreshKey])

  // Load more data function
  const loadMore = useCallback(() => {
    // Check for valid cursor: non-zero number or non-empty string
    const hasCursor = typeof oldestCursor === 'string' ? oldestCursor !== '' : oldestCursor !== 0
    if (loadingMore || !hasMore || data.length === 0 || !hasCursor) return

    setLoadingMore(true)

    const params = new URLSearchParams({ limit: '50', before: String(oldestCursor) })
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
      .then((json: SubmissionsResponse) => {
        setData(prev => [...prev, ...json.data])
        setOldestCursor(json.cursors.oldest)
        setHasMore(json.has_more)
        setLoadingMore(false)
      })
      .catch(() => {
        setLoadingMore(false)
      })
  }, [loadingMore, hasMore, data.length, oldestCursor, filters, viewMode])

  // Intersection observer for infinite scroll
  useEffect(() => {
    // Only set up observer when on home page
    if (currentPage !== 'home') return

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
  }, [currentPage, loadMore, hasMore, loadingMore])

  // Memoize selected row data to avoid re-fetching when more rows are loaded
  const selectedRowData = useMemo(() => {
    if (selectedRow === null || data.length === 0) return null
    const submission = data[selectedRow]
    if (!submission) return null
    return {
      toc: submission.toc_formatted,
      discId: submission.tocid,
    }
  }, [selectedRow, data[selectedRow ?? -1]])

  // Fetch metadata when a row is selected
  useEffect(() => {
    if (!selectedRowData?.toc) {
      setMetadata(null)
      setSelectedMetadataRow(null)
      return
    }

    const toc = selectedRowData.toc

    setMetadataLoading(true)
    setMetadata(null)
    setSelectedMetadataRow(null)

    fetch(`/api/lookup?metadata=default&fuzzy=1&ctdb=0&toc=${encodeURIComponent(toc)}`)
      .then(response => {
        if (!response.ok) {
          throw new Error('Failed to fetch metadata')
        }
        return response.json()
      })
      .then((data: { metadata?: Metadata[] }) => {
        const metadataArray = data.metadata || []
        setMetadata(metadataArray)
        setMetadataLoading(false)
        // Auto-select first row if available
        if (metadataArray.length > 0) {
          setSelectedMetadataRow(0)
        }
      })
      .catch(() => {
        setMetadata(null)
        setMetadataLoading(false)
      })
  }, [selectedRowData?.toc])

  // Compute selected entry info (including async mbid) when row is selected
  useEffect(() => {
    if (!selectedRowData) {
      setSelectedEntryInfo(null)
      return
    }

    const { toc, discId } = selectedRowData
    const mbtoc = tocs2mbtoc(toc)
    const cddbid = tocs2cddbid(toc)
    const arid = tocs2arid(toc)

    // Set initial info with null mbid
    const info = {
      discId,
      toc,
      mbtoc,
      mbid: null as string | null,
      cddbid,
      arid,
      mbUrl: `https://musicbrainz.org/bare/cdlookup.html?toc=${encodeURIComponent(mbtoc)}`,
      ctdbUrl: `/lookup2.php?version=3&ctdb=1&metadata=extensive&fuzzy=1&toc=${encodeURIComponent(toc)}`,
    }
    setSelectedEntryInfo(info)

    // Compute mbid async and update
    tocs2mbid(toc)
      .then(mbid => setSelectedEntryInfo(prev => prev ? { ...prev, mbid } : null))
      .catch(() => {})
  }, [selectedRowData])

  // Fetch submissions when a row is selected (admin only)
  useEffect(() => {
    if (user?.role !== 'admin' || !selectedRowData?.discId) {
      setSubmissions(null)
      setSubmissionsHasMore(true)
      return
    }

    const discId = selectedRowData.discId

    setSubmissionsLoading(true)
    setSubmissions(null)
    setSubmissionsHasMore(true)
    setSubmissionsOldestCursor(0)

    fetch(`/api/recent?tocid=${encodeURIComponent(discId)}&limit=20`)
      .then(response => {
        if (!response.ok) {
          throw new Error('Failed to fetch submissions')
        }
        return response.json()
      })
      .then((json: { data: RecentSubmission[], cursors: { newest: number, oldest: number }, has_more: boolean }) => {
        setSubmissions(json.data)
        setSubmissionsOldestCursor(json.cursors.oldest)
        setSubmissionsHasMore(json.has_more)
        setSubmissionsLoading(false)
      })
      .catch(() => {
        setSubmissions(null)
        setSubmissionsLoading(false)
      })
  }, [user?.role, selectedRowData?.discId])

  // Load more submissions (fetch older entries using before cursor)
  const loadMoreSubmissions = useCallback(() => {
    if (submissionsLoadingMore || !submissionsHasMore || !submissions || !selectedRowData?.discId || submissionsOldestCursor === 0) return

    setSubmissionsLoadingMore(true)

    fetch(`/api/recent?tocid=${encodeURIComponent(selectedRowData.discId)}&limit=20&before=${submissionsOldestCursor}`)
      .then(response => {
        if (!response.ok) {
          throw new Error('Failed to fetch submissions')
        }
        return response.json()
      })
      .then((json: { data: RecentSubmission[], cursors: { newest: number, oldest: number }, has_more: boolean }) => {
        setSubmissions(prev => prev ? [...prev, ...json.data] : json.data)
        setSubmissionsOldestCursor(json.cursors.oldest)
        setSubmissionsHasMore(json.has_more)
        setSubmissionsLoadingMore(false)
      })
      .catch(() => {
        setSubmissionsLoadingMore(false)
      })
  }, [submissionsLoadingMore, submissionsHasMore, submissions, selectedRowData?.discId, submissionsOldestCursor])

  // Intersection observer for submissions infinite scroll
  useEffect(() => {
    if (!submissionsOpen) return

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting && submissionsHasMore && !submissionsLoadingMore) {
          loadMoreSubmissions()
        }
      },
      { threshold: 0.1 }
    )

    const currentRef = submissionsLoadMoreRef.current
    if (currentRef) {
      observer.observe(currentRef)
    }

    return () => {
      if (currentRef) {
        observer.unobserve(currentRef)
      }
    }
  }, [submissionsOpen, loadMoreSubmissions, submissionsHasMore, submissionsLoadingMore])

  // Fetch logs when logs page is accessed (admin only)
  useEffect(() => {
    if (currentPage !== 'logs' || user?.role !== 'admin') {
      setLogsData(null)
      setLogsHasMore(true)
      setLogsNewestCursor(0)
      setLogsOldestCursor(0)
      return
    }

    setLogsLoading(true)
    setLogsData(null)
    setLogsHasMore(true)

    // Fetch initial data with cursor information
    fetch(`/api/recent?limit=50`)
      .then(response => {
        if (!response.ok) {
          throw new Error('Failed to fetch logs')
        }
        return response.json()
      })
      .then((json: { data: RecentSubmission[], cursors: { newest: number, oldest: number }, has_more: boolean }) => {
        setLogsData(json.data)
        setLogsNewestCursor(json.cursors.newest)
        logsNewestCursorRef.current = json.cursors.newest
        setLogsOldestCursor(json.cursors.oldest)
        setLogsHasMore(json.has_more)
        setLogsLoading(false)
      })
      .catch(() => {
        setLogsData(null)
        setLogsLoading(false)
      })
  }, [currentPage, user?.role])

  // Load more logs (fetch older entries using before cursor)
  const loadMoreLogs = useCallback(() => {
    if (logsLoadingMore || !logsHasMore || !logsData || logsOldestCursor === 0) return

    setLogsLoadingMore(true)

    fetch(`/api/recent?limit=50&before=${logsOldestCursor}`)
      .then(response => {
        if (!response.ok) {
          throw new Error('Failed to fetch logs')
        }
        return response.json()
      })
      .then((json: { data: RecentSubmission[], cursors: { newest: number, oldest: number }, has_more: boolean }) => {
        setLogsData(prev => prev ? [...prev, ...json.data] : json.data)
        setLogsOldestCursor(json.cursors.oldest)
        setLogsHasMore(json.has_more)
        setLogsLoadingMore(false)
      })
      .catch(() => {
        setLogsLoadingMore(false)
      })
  }, [logsLoadingMore, logsHasMore, logsData, logsOldestCursor])

  // Intersection observer for logs infinite scroll
  useEffect(() => {
    if (currentPage !== 'logs') return

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting && logsHasMore && !logsLoadingMore) {
          loadMoreLogs()
        }
      },
      { threshold: 0.1 }
    )

    const currentRef = logsLoadMoreRef.current
    if (currentRef) {
      observer.observe(currentRef)
    }

    return () => {
      if (currentRef) {
        observer.unobserve(currentRef)
      }
    }
  }, [loadMoreLogs, logsHasMore, logsLoadingMore, currentPage])

  // Keep ref in sync with logsUpdateCount for access in async callbacks
  logsUpdateCountRef.current = logsUpdateCount

  // WebSocket-triggered fetch for new log entries (real-time updates)
  useEffect(() => {
    if (currentPage !== 'logs' || user?.role !== 'admin' || logsNewestCursorRef.current === 0 || logsUpdateCount === 0) {
      return
    }

    // Don't start a new fetch if one is already in progress
    if (logsFetchingNewRef.current) {
      return
    }

    const doFetch = (processingCount: number, iteration: number) => {
      const maxIterations = 5 // Prevent runaway loops; next WS notification will continue
      if (iteration >= maxIterations) return

      const currentCursor = logsNewestCursorRef.current
      if (currentCursor === 0) return

      logsFetchingNewRef.current = true

      fetch(`/api/recent?limit=50&cursor=${currentCursor}`)
        .then(response => {
          if (!response.ok) throw new Error('Failed to fetch new logs')
          return response.json()
        })
        .then((json: { data: RecentSubmission[], cursors: { newest: number, oldest: number }, has_more: boolean }) => {
          if (json.data.length > 0) {
            setLogsData(prev => prev ? [...json.data, ...prev] : json.data)
            setLogsNewestCursor(json.cursors.newest)
            logsNewestCursorRef.current = json.cursors.newest
          }
        })
        .catch(() => {
          // Silently fail - don't disrupt the UI
        })
        .finally(() => {
          logsFetchingNewRef.current = false
          // Check if more updates came in while we were fetching
          const latestCount = logsUpdateCountRef.current
          if (latestCount > processingCount) {
            doFetch(latestCount, iteration + 1)
          }
        })
    }

    doFetch(logsUpdateCount, 0)
  }, [currentPage, user?.role, logsUpdateCount])

  // Fallback polling when WebSocket disconnected
  useEffect(() => {
    if (currentPage !== 'logs' || user?.role !== 'admin' || logsNewestCursorRef.current === 0 || logsWsConnected) return

    const pollInterval = setInterval(() => {
      // Don't start a new fetch if one is already in progress
      if (logsFetchingNewRef.current) return

      const currentCursor = logsNewestCursorRef.current
      logsFetchingNewRef.current = true

      // Fetch entries newer than the current newest cursor
      fetch(`/api/recent?limit=50&cursor=${currentCursor}`)
        .then(response => {
          if (!response.ok) {
            throw new Error('Failed to fetch new logs')
          }
          return response.json()
        })
        .then((json: { data: RecentSubmission[], cursors: { newest: number, oldest: number }, has_more: boolean }) => {
          if (json.data.length > 0) {
            // Prepend new entries to the beginning of the list
            setLogsData(prev => prev ? [...json.data, ...prev] : json.data)
            setLogsNewestCursor(json.cursors.newest)
            logsNewestCursorRef.current = json.cursors.newest
          }
        })
        .catch(() => {
          // Silently fail - don't disrupt the UI
        })
        .finally(() => {
          logsFetchingNewRef.current = false
        })
    }, 5000) // Poll every 5 seconds when WebSocket unavailable

    return () => clearInterval(pollInterval)
  }, [currentPage, user?.role, logsWsConnected])

  // Build tracks data
  const tracks = useMemo<Track[] | null>(() => {
    if (selectedRow === null || data.length === 0) {
      return null
    }

    const submission = data[selectedRow]
    if (!submission) return null

    const tocString = submission.toc_formatted
    const crcsString = submission.track_crcs_formatted || null

    // Get track info and main artist directly from metadata
    let tracklist: Array<{ name?: string; artist?: string }> | null = null
    let mainArtist: string | null = null

    if (metadata && selectedMetadataRow !== null) {
      const selectedMetadata = metadata[selectedMetadataRow]
      tracklist = selectedMetadata.tracklist || null
      mainArtist = selectedMetadata.artistname
    }

    return buildTracks(tocString, crcsString, tracklist, mainArtist)
  }, [selectedRow, data, metadata, selectedMetadataRow])

  // Extract cover art from metadata
  interface CoverArtImage {
    uri: string
    uri150: string
  }

  const coverArt = useMemo<{ primary: CoverArtImage | null; secondary: CoverArtImage[] }>(() => {
    if (!metadata || selectedMetadataRow === null) return { primary: null, secondary: [] }

    const selectedMetadata = metadata[selectedMetadataRow]
    const coverartList = selectedMetadata.coverart

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
        uri: img.uri,
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

  if (data.length === 0 && !loading) {
    return (
      <div className="container">
        <h1>CUETools DB</h1>
        <p>No data available</p>
      </div>
    )
  }

  // Map source names to icon URLs
  const sourceIcons: Record<string, string> = {
    musicbrainz: 'https://s3.cuetools.net/icons/musicbrainz.png',
    cdstub: 'https://s3.cuetools.net/icons/cdstub.png',
    discogs: 'https://s3.cuetools.net/icons/discogs.png',
    freedb: 'https://s3.cuetools.net/icons/freedb.png',
  }

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
          <button 
            className={`menu-item ${currentPage === 'home' ? 'active' : ''}`} 
            onClick={() => { setCurrentPage('home'); setMenuOpen(false); }}
          >
            <Home className="size-5" />
            <span>Home</span>
          </button>
          <button
            className={`menu-item ${currentPage === 'stats' ? 'active' : ''}`}
            onClick={() => { setCurrentPage('stats'); setMenuOpen(false); }}
          >
            <BarChart3 className="size-5" />
            <span>Stats</span>
          </button>
          {user?.role === 'admin' && (
            <button
              className={`menu-item ${currentPage === 'logs' ? 'active' : ''}`}
              onClick={() => { setCurrentPage('logs'); setMenuOpen(false); }}
            >
              <ScrollText className="size-5" />
              <span>Logs</span>
            </button>
          )}
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
        <div className="header-left">
          <button className="menu-toggle-btn" onClick={() => setMenuOpen(true)}>
            <Menu className="size-6" />
          </button>
          <img src="http://s3.cuetools.net/ctdb64.png" alt="CUETools DB" className="header-logo" />
          <h1>CUETools DB</h1>
        </div>
        <div className="header-actions">
          {authLoading ? (
            <div className="text-sm text-muted-foreground">Loading...</div>
          ) : user ? (
            <UserMenu user={user} onLogout={() => setUser(null)} />
          ) : (
            <LoginButton />
          )}
        </div>
        {currentPage === 'home' && (
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
                      onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                    />
                  </div>
                  <div className="filter-field">
                    <Label htmlFor="filter-artist">Artist</Label>
                    <Input
                      id="filter-artist"
                      placeholder="e.g. Pink Floyd"
                      value={pendingFilters.artist}
                      onChange={(e) => setPendingFilters(p => ({ ...p, artist: e.target.value }))}
                      onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
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
            <Button
              variant="outline"
              size="sm"
              className="refresh-button"
              onClick={() => setRefreshKey(k => k + 1)}
              title="Refresh data"
            >
              <RefreshCw className="size-4" />
            </Button>
          </div>
        )}
      </header>

      {currentPage === 'stats' ? (
        <Stats />
      ) : currentPage === 'logs' ? (
        <div className="logs-page">
          {user?.role !== 'admin' ? (
            <div className="access-denied">
              <p>Access denied. Admin privileges required.</p>
            </div>
          ) : (
            <>
              {logsLoading && (
                <p className="loading">Loading logs...</p>
              )}

              {!logsLoading && (!logsData || logsData.length === 0) && (
                <p className="no-data">No logs found</p>
              )}

              {!logsLoading && logsData && logsData.length > 0 && (
                <>
                  {logsWsConnected && (
                    <div className="stats-totals" style={{ marginBottom: '1rem' }}>
                      <div className="totals-item">
                        <span className="live-indicator" title="Live updates via WebSocket">
                          <span className="live-dot"></span>
                          Live
                        </span>
                      </div>
                    </div>
                  )}
                  <div className="table-wrapper logs-table">
                  <table>
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>Agent</th>
                        <th>Drive</th>
                        <th>User</th>
                        <th>IP</th>
                        <th>Artist</th>
                        <th>Album</th>
                        <th>TOC ID</th>
                        <th>Tracks</th>
                        <th>CTDB ID</th>
                        <th>Submissions</th>
                        <th>CRC32</th>
                        <th>Quality</th>
                        <th>Barcode</th>
                      </tr>
                    </thead>
                    <tbody>
                      {logsData.map((submission, idx) => {
                        const date = new Date(submission.time)

                        return (
                          <tr key={idx}>
                            <td className="mono">
                              {formatAbsoluteTime(date)}
                            </td>
                            <td>{submission.agent || ''}</td>
                            <td>{submission.drivename || ''}</td>
                            <td>{submission.userid || ''}</td>
                            <td className="mono">{submission.ip || ''}</td>
                            <td>{submission.artist}</td>
                            <td>{submission.title}</td>
                            <td className="mono">{submission.tocid.substring(0, 8)}...</td>
                            <td>{submission.track_count_string}</td>
                            <td>{submission.id}</td>
                            <td>{submission.sub_count}</td>
                            <td className="mono">{formatCRC32(submission.crc32)}</td>
                            <td className="mono">{submission.quality !== null && submission.quality !== undefined ? submission.quality : '-'}</td>
                            <td>{submission.barcode || '-'}</td>
                          </tr>
                        )
                      })}
                    </tbody>
                  </table>
                  {/* Infinite scroll trigger */}
                  <div ref={logsLoadMoreRef} className="load-more-trigger">
                    {logsLoadingMore && <span className="loading-more">Loading more...</span>}
                    {!logsHasMore && logsData.length > 0 && <span className="no-more">No more logs</span>}
                  </div>
                </div>
                </>
              )}
            </>
          )}
        </div>
      ) : (
      <>
      <div className="table-wrapper main-table">
        <table>
          <thead>
            <tr>
              <th className="col-artist">Artist</th>
              <th className="col-album">Album</th>
              <th className="col-disc-id">Disc Id</th>
              <th className="col-tracks">Tracks</th>
              <th className="col-ctdb-id">CTDB Id</th>
              <th className="col-cf">Cf</th>
            </tr>
          </thead>
          <tbody>
            {data.map((submission, rowIndex) => (
              <tr
                key={submission.id}
                onClick={() => handleRowClick(rowIndex)}
                className={selectedRow === rowIndex ? 'selected' : ''}
              >
                <td className="col-artist">
                  <span className="filterable-cell">
                    <button
                      className="inline-filter-btn"
                      onClick={(e) => {
                        e.stopPropagation()
                        setArtistFilter(submission.artist || '')
                      }}
                      title="Filter by this Artist"
                    >
                      <Filter className="size-3" />
                    </button>
                    {submission.artist}
                  </span>
                </td>
                <td className="col-album">{submission.title}</td>
                <td className="col-disc-id">
                  <span className="filterable-cell">
                    <button
                      className="inline-filter-btn"
                      onClick={(e) => {
                        e.stopPropagation()
                        setTocidFilter(submission.tocid || '')
                      }}
                      title="Filter by this Disc ID"
                    >
                      <Filter className="size-3" />
                    </button>
                    {submission.tocid}
                  </span>
                </td>
                <td className="col-tracks">{submission.track_count_formatted}</td>
                <td className="col-ctdb-id">{submission.id}</td>
                <td className="col-cf">{submission.sub_count}</td>
              </tr>
            ))}
          </tbody>
        </table>
        {/* Infinite scroll trigger */}
        <div ref={loadMoreRef} className="load-more-trigger">
          {loadingMore && <span className="loading-more">Loading more...</span>}
          {!hasMore && data.length > 0 && <span className="no-more">No more entries</span>}
        </div>
      </div>

      {/* Metadata section with links */}
      {selectedRow !== null && (
        <div className="metadata-layout">
          {/* Links box */}
          {selectedEntryInfo && (
            <div className="links-box">
              <a
                href={selectedEntryInfo.ctdbUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="link-item ctdb-link"
                title="CTDB Lookup"
              >
                <img src="https://s3.cuetools.net/icons/cueripper.png" alt="CTDB" className="link-icon" />
                <span className="link-value">{selectedEntryInfo.discId}</span>
              </a>
              <a
                href={selectedEntryInfo.mbUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="link-item mb-link"
                title="MusicBrainz"
              >
                <img src="https://s3.cuetools.net/icons/musicbrainz.png" alt="MusicBrainz" className="link-icon" />
                <span className="link-value">{selectedEntryInfo.mbid || '...'}</span>
              </a>
              <div className="link-item freedb-link" title="FreeDB/CDDB">
                <img src="https://s3.cuetools.net/icons/freedb.png" alt="FreeDB" className="link-icon" />
                <span className="link-value">{selectedEntryInfo.cddbid}</span>
              </div>
              <div className="link-item ar-link" title="AccurateRip">
                <img src="https://s3.cuetools.net/icons/ar.png" alt="AccurateRip" className="link-icon" />
                <span className="link-value">{selectedEntryInfo.arid}</span>
              </div>
            </div>
          )}

          {/* Metadata table */}
          <div className="metadata-section">
            {metadataLoading && <p className="loading">Loading metadata...</p>}
            {!metadataLoading && metadata && metadata.length > 0 && (
              <div className="table-wrapper metadata-table">
                <table>
                  <thead>
                    <tr>
                      <th className="meta-col-source-icon"></th>
                      <th className="meta-col-date">Date</th>
                      <th className="meta-col-artist">Artist</th>
                      <th className="meta-col-album">Album</th>
                      <th className="meta-col-disc">Disc</th>
                      <th className="meta-col-release">Release</th>
                      <th className="meta-col-label">Label</th>
                      <th className="meta-col-barcode">Barcode</th>
                      <th className="meta-col-id">ID</th>
                      <th className="meta-col-rel">Rel</th>
                    </tr>
                  </thead>
                  <tbody>
                    {metadata.map((item, rowIndex) => {
                      const iconUrl = sourceIcons[item.source.toLowerCase()]

                      // Format disc info
                      const discInfo = (item.totaldiscs && item.totaldiscs !== 1) || (item.discnumber && item.discnumber !== 1)
                        ? `${item.discnumber || '?'}/${item.totaldiscs || '?'}${item.discname ? ': ' + item.discname : ''}`
                        : ''

                      return (
                        <tr
                          key={rowIndex}
                          onClick={() => handleMetadataRowClick(rowIndex)}
                          className={selectedMetadataRow === rowIndex ? 'selected' : ''}
                        >
                          <td className="meta-col-source-icon">
                            {iconUrl ? (
                              <img src={iconUrl} alt={item.source} className="source-icon" title={item.source} />
                            ) : (
                              <span title={item.source}>•</span>
                            )}
                          </td>
                          <td className="meta-col-date">{item.first_release_date_year || ''}</td>
                          <td className="meta-col-artist">{item.artistname}</td>
                          <td className="meta-col-album">{item.albumname}</td>
                          <td className="meta-col-disc">{discInfo}</td>
                          <td className="meta-col-release">
                            {item.release && item.release.length > 0 && (
                              <>
                                {item.release.map((rel, idx) => {
                                  const flag = countryToFlag(rel.country || '')
                                  const text = [rel.country, rel.date].filter(Boolean).join(': ')
                                  return (
                                    <span key={idx} className="release-flag" title={text}>
                                      {flag || rel.country || '🌐'}
                                    </span>
                                  )
                                })}
                              </>
                            )}
                          </td>
                          <td className="meta-col-label">
                            {item.label && item.label.length > 0 && (
                              item.label.map(l => l.catno ? `${l.name} (${l.catno})` : l.name).join(', ')
                            )}
                          </td>
                          <td className="meta-col-barcode">{item.barcode || ''}</td>
                          <td className="meta-col-id">{item.id}</td>
                          <td className="meta-col-rel">{item.relevance ?? ''}</td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>
            )}
            {!metadataLoading && (!metadata || metadata.length === 0) && (
              <p className="no-metadata">No metadata found</p>
            )}
          </div>
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

      {/* Submissions log (admin only) */}
      {user?.role === 'admin' && selectedRow !== null && (
        <div className="submissions-section">
          <button
            className="submissions-header"
            onClick={() => setSubmissionsOpen(!submissionsOpen)}
          >
            <span className="submissions-title">
              {submissionsOpen ? '▼' : '▶'} Recent Submissions
              {submissions && ` (${submissions.length})`}
            </span>
          </button>

          {submissionsOpen && (
            <div className="submissions-content">
              {submissionsLoading && (
                <p className="loading">Loading submissions...</p>
              )}

              {!submissionsLoading && (!submissions || submissions.length === 0) && (
                <p className="no-submissions">No submissions found</p>
              )}

              {!submissionsLoading && submissions && submissions.length > 0 && (
                <div className="table-wrapper submissions-table">
                  <table>
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>Agent</th>
                        <th>Drive</th>
                        <th>User</th>
                        <th>Artist</th>
                        <th>Album</th>
                        <th>Q</th>
                      </tr>
                    </thead>
                    <tbody>
                      {submissions.map((submission, idx) => {
                        const date = new Date(submission.time)

                        return (
                          <tr key={idx}>
                            <td className="mono" title={date.toLocaleString()}>
                              {formatRelativeTime(date)}
                            </td>
                            <td>{submission.agent || ''}</td>
                            <td>{submission.drivename || ''}</td>
                            <td>{submission.userid || ''}</td>
                            <td>{submission.artist}</td>
                            <td>{submission.title}</td>
                            <td className="mono">{submission.quality !== null && submission.quality !== undefined ? submission.quality : '-'}</td>
                          </tr>
                        )
                      })}
                    </tbody>
                  </table>
                  {/* Infinite scroll trigger */}
                  <div ref={submissionsLoadMoreRef} className="load-more-trigger">
                    {submissionsLoadingMore && <span className="loading-more">Loading more...</span>}
                    {!submissionsHasMore && submissions.length > 0 && <span className="no-more">No more submissions</span>}
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      )}
      </>
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
