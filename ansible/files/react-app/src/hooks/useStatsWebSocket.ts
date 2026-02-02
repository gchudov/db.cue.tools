import { useEffect, useState, useRef } from 'react'

interface StatsData {
  unique_tocs: number
  submissions: number
}

interface StatsMessage {
  type: 'stats_update'
  unique_tocs: number
  submissions: number
  timestamp: number
}

interface UseStatsWebSocketReturn {
  stats: StatsData | null
  isConnected: boolean
  error: string | null
}

export function useStatsWebSocket(): UseStatsWebSocketReturn {
  const [stats, setStats] = useState<StatsData | null>(null)
  const [isConnected, setIsConnected] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const wsRef = useRef<WebSocket | null>(null)
  const reconnectTimeoutRef = useRef<NodeJS.Timeout | null>(null)
  const reconnectAttemptsRef = useRef(0)

  const connect = () => {
    // Don't reconnect if already connected
    if (wsRef.current?.readyState === WebSocket.OPEN) {
      return
    }

    // Clear any pending reconnection
    if (reconnectTimeoutRef.current) {
      clearTimeout(reconnectTimeoutRef.current)
      reconnectTimeoutRef.current = null
    }

    // Determine WebSocket URL based on current location
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:'
    const host = window.location.host
    const wsUrl = `${protocol}//${host}/api/ws/stats`

    console.log('Connecting to WebSocket:', wsUrl)

    try {
      const ws = new WebSocket(wsUrl)

      ws.onopen = () => {
        console.log('WebSocket connected')
        setIsConnected(true)
        setError(null)
        reconnectAttemptsRef.current = 0
      }

      ws.onmessage = (event) => {
        try {
          const message: StatsMessage = JSON.parse(event.data)
          if (message.type === 'stats_update') {
            setStats({
              unique_tocs: message.unique_tocs,
              submissions: message.submissions,
            })
          }
        } catch (err) {
          console.error('Failed to parse WebSocket message:', err)
        }
      }

      ws.onerror = (event) => {
        console.error('WebSocket error:', event)
        setError('WebSocket connection error')
      }

      ws.onclose = () => {
        console.log('WebSocket disconnected')
        setIsConnected(false)
        wsRef.current = null

        // Exponential backoff: 1s, 2s, 4s, 8s, 16s, max 30s
        const delay = Math.min(1000 * Math.pow(2, reconnectAttemptsRef.current), 30000)
        reconnectAttemptsRef.current++

        console.log(`Reconnecting in ${delay}ms...`)
        reconnectTimeoutRef.current = setTimeout(() => {
          connect()
        }, delay)
      }

      wsRef.current = ws
    } catch (err) {
      console.error('Failed to create WebSocket:', err)
      setError('Failed to create WebSocket connection')
    }
  }

  useEffect(() => {
    connect()

    return () => {
      // Cleanup on unmount
      if (reconnectTimeoutRef.current) {
        clearTimeout(reconnectTimeoutRef.current)
      }
      if (wsRef.current) {
        wsRef.current.close()
      }
    }
  }, [])

  return { stats, isConnected, error }
}
