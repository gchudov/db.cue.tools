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

// Singleton WebSocket manager (shared across all hook instances)
class StatsWebSocketManager {
  private ws: WebSocket | null = null
  private reconnectTimeout: NodeJS.Timeout | null = null
  private reconnectAttempts = 0
  private subscribers = new Set<(stats: StatsData | null, connected: boolean, error: string | null) => void>()
  private latestStats: StatsData | null = null
  private isConnected = false
  private currentError: string | null = null
  private isDisconnecting = false

  subscribe(callback: (stats: StatsData | null, connected: boolean, error: string | null) => void) {
    this.subscribers.add(callback)

    // Immediately send current state to new subscriber
    callback(this.latestStats, this.isConnected, this.currentError)

    // Connect if this is the first subscriber
    if (this.subscribers.size === 1) {
      this.connect()
    }

    return () => {
      this.subscribers.delete(callback)

      // Disconnect if no more subscribers
      if (this.subscribers.size === 0) {
        this.disconnect()
      }
    }
  }

  private connect() {
    // Don't reconnect if already connected or connecting
    if (this.ws?.readyState === WebSocket.OPEN || this.ws?.readyState === WebSocket.CONNECTING) {
      return
    }

    // Clear any pending reconnection
    if (this.reconnectTimeout) {
      clearTimeout(this.reconnectTimeout)
      this.reconnectTimeout = null
    }

    // Determine WebSocket URL based on current location
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:'
    const host = window.location.host
    const wsUrl = `${protocol}//${host}/api/ws/stats`

    try {
      const ws = new WebSocket(wsUrl)

      ws.onopen = () => {
        // Check if this WebSocket is still the current one
        if (this.ws !== ws) {
          ws.close()
          return
        }

        // Check if we have any subscribers
        if (this.subscribers.size === 0) {
          ws.close()
          return
        }

        this.isConnected = true
        this.currentError = null
        this.reconnectAttempts = 0
        this.notifySubscribers()
      }

      ws.onmessage = (event) => {
        try {
          const message: StatsMessage = JSON.parse(event.data)
          if (message.type === 'stats_update') {
            this.latestStats = {
              unique_tocs: message.unique_tocs,
              submissions: message.submissions,
            }
            this.notifySubscribers()
          }
        } catch (err) {
          console.error('Failed to parse WebSocket message:', err)
        }
      }

      ws.onerror = (event) => {
        console.error('WebSocket error:', event)
        this.currentError = 'WebSocket connection error'
        this.notifySubscribers()
      }

      ws.onclose = () => {
        this.isConnected = false
        this.ws = null
        this.notifySubscribers()

        // Don't reconnect if we're intentionally disconnecting
        if (this.isDisconnecting) {
          this.isDisconnecting = false
          return
        }

        // Only reconnect if we still have subscribers
        if (this.subscribers.size > 0) {
          // Exponential backoff: 1s, 2s, 4s, 8s, 16s, max 30s
          const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000)
          this.reconnectAttempts++

          this.reconnectTimeout = setTimeout(() => {
            this.connect()
          }, delay)
        }
      }

      this.ws = ws
    } catch (err) {
      console.error('Failed to create WebSocket:', err)
      this.currentError = 'Failed to create WebSocket connection'
      this.notifySubscribers()
    }
  }

  private disconnect() {
    // Clear any pending reconnection
    if (this.reconnectTimeout) {
      clearTimeout(this.reconnectTimeout)
      this.reconnectTimeout = null
    }

    // Close the WebSocket if it exists and is not already closed
    if (this.ws) {
      const state = this.ws.readyState

      if (state === WebSocket.OPEN) {
        // Only close OPEN WebSockets - closing works reliably for these
        this.isDisconnecting = true
        this.ws.close()
        // onclose will be called, which will check isDisconnecting flag
      } else if (state === WebSocket.CONNECTING) {
        // Don't try to close CONNECTING WebSockets - browsers ignore it
        // Instead, set ws to null so onopen will detect it's orphaned and close it
      }

      this.ws = null
    }

    this.isConnected = false
  }

  private notifySubscribers() {
    this.subscribers.forEach(callback => {
      callback(this.latestStats, this.isConnected, this.currentError)
    })
  }
}

// Single shared instance
const manager = new StatsWebSocketManager()

export function useStatsWebSocket(): UseStatsWebSocketReturn {
  const [stats, setStats] = useState<StatsData | null>(null)
  const [isConnected, setIsConnected] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const unsubscribe = manager.subscribe((newStats, connected, err) => {
      setStats(newStats)
      setIsConnected(connected)
      setError(err)
    })

    return unsubscribe
  }, [])

  return { stats, isConnected, error }
}
