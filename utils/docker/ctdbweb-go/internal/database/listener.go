package database

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"log"
	"time"

	"github.com/lib/pq"
)

// Hub interface to avoid circular dependency
type Hub interface {
	Broadcast(message []byte)
}

// Listener manages PostgreSQL LISTEN/NOTIFY connection
type Listener struct {
	listener *pq.Listener
	hub      Hub
	db       *sql.DB

	// Rate limiting
	lastBroadcast time.Time
	minInterval   time.Duration
}

// NewListener creates a new PostgreSQL listener
func NewListener(cfg Config, hub Hub, db *DB) (*Listener, error) {
	// Connection string for pq.Listener (separate from sql.DB pool)
	connStr := fmt.Sprintf("host=%s port=%s user=%s dbname=%s sslmode=disable",
		cfg.Host, cfg.Port, cfg.CTDBUser, cfg.CTDBName)

	// Create listener with automatic reconnection
	listener := pq.NewListener(connStr, 10*time.Second, time.Minute, func(ev pq.ListenerEventType, err error) {
		if err != nil {
			log.Printf("PostgreSQL listener error: %v", err)
		}
		switch ev {
		case pq.ListenerEventConnected:
			log.Println("PostgreSQL listener connected")
		case pq.ListenerEventDisconnected:
			log.Println("PostgreSQL listener disconnected")
		case pq.ListenerEventReconnected:
			log.Println("PostgreSQL listener reconnected")
		case pq.ListenerEventConnectionAttemptFailed:
			log.Println("PostgreSQL listener connection attempt failed")
		}
	})

	return &Listener{
		listener:      listener,
		hub:           hub,
		db:            db.CTDB,
		lastBroadcast: time.Time{},
		minInterval:   1 * time.Second, // Max 1 broadcast per second
	}, nil
}

// Start begins listening for PostgreSQL notifications
func (l *Listener) Start() error {
	if err := l.listener.Listen("stats_update"); err != nil {
		return fmt.Errorf("failed to listen on stats_update channel: %w", err)
	}

	log.Println("Started listening on stats_update channel")

	// Start notification handler in goroutine
	go l.handleNotifications()

	return nil
}

// handleNotifications processes incoming PostgreSQL notifications
func (l *Listener) handleNotifications() {
	for {
		select {
		case notification := <-l.listener.Notify:
			if notification != nil {
				l.handleNotification(notification)
			}
		case <-time.After(90 * time.Second):
			// Send periodic ping to keep connection alive
			go func() {
				if err := l.listener.Ping(); err != nil {
					log.Printf("PostgreSQL listener ping failed: %v", err)
				}
			}()
		}
	}
}

// handleNotification processes a single notification
func (l *Listener) handleNotification(notification *pq.Notification) {
	// Rate limiting: Skip if we broadcast recently
	now := time.Now()
	if now.Sub(l.lastBroadcast) < l.minInterval {
		log.Printf("Skipping broadcast (rate limited)")
		return
	}

	// Query fresh stats using existing getStatsTotals function
	stats, err := getStatsTotals(l.db)
	if err != nil {
		log.Printf("Failed to query stats for broadcast: %v", err)
		return
	}

	// stats is map[string]int from getStatsTotals
	statsMap, ok := stats.(map[string]int)
	if !ok {
		log.Printf("Failed to cast stats to map[string]int")
		return
	}

	// Marshal to JSON
	message, err := json.Marshal(map[string]interface{}{
		"type":        "stats_update",
		"unique_tocs": statsMap["unique_tocs"],
		"submissions": statsMap["submissions"],
		"timestamp":   now.Unix(),
	})
	if err != nil {
		log.Printf("Failed to marshal stats: %v", err)
		return
	}

	// Broadcast to all WebSocket clients
	l.hub.Broadcast(message)
	l.lastBroadcast = now

	log.Printf("Stats broadcast: unique_tocs=%d, submissions=%d", statsMap["unique_tocs"], statsMap["submissions"])
}

// Close stops the listener
func (l *Listener) Close() error {
	return l.listener.Close()
}
