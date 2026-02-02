package handlers

import (
	"log"
	"net/http"

	"github.com/cuetools/ctdbweb/internal/websocket"
	ws "github.com/gorilla/websocket"
)

// WebSocketHandler handles WebSocket connections for stats updates
type WebSocketHandler struct {
	hub      *websocket.Hub
	upgrader ws.Upgrader
}

// NewWebSocketHandler creates a new WebSocket handler
func NewWebSocketHandler(hub *websocket.Hub) *WebSocketHandler {
	return &WebSocketHandler{
		hub: hub,
		upgrader: ws.Upgrader{
			ReadBufferSize:  1024,
			WriteBufferSize: 1024,
			CheckOrigin: func(r *http.Request) bool {
				origin := r.Header.Get("Origin")
				// Allow db.cue.tools, dev.db.cue.tools, and localhost for development
				allowed := map[string]bool{
					"https://db.cue.tools":     true,
					"https://dev.db.cue.tools": true,
					"http://localhost:5173":    true,
					"http://localhost:3000":    true,
				}
				return allowed[origin]
			},
		},
	}
}

// ServeHTTP handles WebSocket upgrade requests
func (h *WebSocketHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	// Upgrade HTTP connection to WebSocket
	conn, err := h.upgrader.Upgrade(w, r, nil)
	if err != nil {
		log.Printf("WebSocket upgrade failed: %v", err)
		return
	}

	// Create client and register with hub
	client := websocket.NewClient(h.hub, conn)
	h.hub.Register(client)

	// Start client goroutines
	go client.WritePump()
	go client.ReadPump()

	log.Printf("WebSocket client connected from %s", r.RemoteAddr)
}
