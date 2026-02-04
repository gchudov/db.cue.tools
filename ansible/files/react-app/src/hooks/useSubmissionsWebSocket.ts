import { useEffect, useState } from 'react'

interface SubmissionsMessage {
  type: 'submissions_update'
  timestamp: number
}

interface UseSubmissionsWebSocketReturn {
  updateCount: number  // Increments on each new submission notification
  isConnected: boolean
  error: string | null
}

// Singleton WebSocket manager (shared across all hook instances)
class SubmissionsWebSocketManager {
  private ws: WebSocket | null = null
  private reconnectTimeout: NodeJS.Timeout | null = null
  private reconnectAttempts = 0
  private subscribers = new Set<(updateCount: number, connected: boolean, error: string | null) => void>()
  private updateCount = 0
  private isConnected = false
  private currentError: string | null = null
  private isDisconnecting = false

  subscribe(callback: (updateCount: number, connected: boolean, error: string | null) => void) {
    this.subscribers.add(callback)

    // Immediately send current state to new subscriber
    callback(this.updateCount, this.isConnected, this.currentError)

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
          // Handle newline-delimited JSON (multiple messages in one frame)
          const lines = event.data.trim().split('\n')
          for (const line of lines) {
            if (!line.trim()) continue

            try {
              const message: SubmissionsMessage = JSON.parse(line)
              if (message.type === 'submissions_update') {
                this.updateCount++
                this.notifySubscribers()
              }
            } catch (parseErr) {
              console.error('Failed to parse WebSocket message line:', parseErr)
            }
          }
        } catch (err) {
          console.error('Failed to process WebSocket message:', err)
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
      callback(this.updateCount, this.isConnected, this.currentError)
    })
  }
}

// Single shared instance
const manager = new SubmissionsWebSocketManager()

export function useSubmissionsWebSocket(): UseSubmissionsWebSocketReturn {
  const [updateCount, setUpdateCount] = useState(0)
  const [isConnected, setIsConnected] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const unsubscribe = manager.subscribe((count, connected, err) => {
      setUpdateCount(count)
      setIsConnected(connected)
      setError(err)
    })

    return unsubscribe
  }, [])

  return { updateCount, isConnected, error }
}
