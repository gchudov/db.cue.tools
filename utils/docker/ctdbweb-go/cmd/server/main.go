package main

import (
	"fmt"
	"log"
	"net/http"
	"os"
	"time"

	"github.com/gorilla/mux"
	"github.com/cuetools/ctdbweb/internal/auth"
	"github.com/cuetools/ctdbweb/internal/database"
	"github.com/cuetools/ctdbweb/internal/handlers"
	"github.com/cuetools/ctdbweb/internal/websocket"
)

func main() {
	// Initialize database connections
	log.Println("Initializing database connections...")
	cfg := database.DefaultConfig()
	db, err := database.Initialize(cfg)
	if err != nil {
		log.Fatalf("Failed to initialize database: %v", err)
	}
	defer db.Close()
	log.Println("Database connections established")

	// Initialize WebSocket hub
	log.Println("Initializing WebSocket hub...")
	hub := websocket.NewHub()
	go hub.Run()

	// Initialize PostgreSQL listener
	log.Println("Initializing PostgreSQL listener...")
	listener, err := database.NewListener(cfg, hub, db)
	if err != nil {
		log.Fatalf("Failed to create listener: %v", err)
	}
	if err := listener.Start(); err != nil {
		log.Fatalf("Failed to start listener: %v", err)
	}
	defer listener.Close()

	// Initialize auth configuration
	authConfig := auth.LoadConfig()

	// Create handlers
	lookupHandler := handlers.NewLookupHandler(db)
	submitHandler := handlers.NewSubmitHandler(db)
	additionsHandler := handlers.NewSubmissionsHandler(db, "latest")
	topHandler := handlers.NewSubmissionsHandler(db, "top")
	statsHandler := handlers.NewStatsHandler(db)
	authHandler := handlers.NewAuthHandler(db, authConfig)
	recentHandler := handlers.NewRecentHandler(db)
	wsHandler := handlers.NewWebSocketHandler(hub)

	// Create router
	r := mux.NewRouter()

	// Health check endpoint
	r.HandleFunc("/health", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(http.StatusOK)
		fmt.Fprintf(w, `{"status":"ok","service":"ctdbweb-go"}`)
	}).Methods("GET")

	// Auth routes (public)
	authRoutes := r.PathPrefix("/api/auth").Subrouter()
	authRoutes.HandleFunc("/login", authHandler.LoginHandler).Methods("GET")
	authRoutes.HandleFunc("/callback", authHandler.CallbackHandler).Methods("GET")
	authRoutes.HandleFunc("/logout", authHandler.LogoutHandler).Methods("POST")

	// Protected auth routes
	authProtected := r.PathPrefix("/api/auth").Subrouter()
	authProtected.Use(auth.RequireAuth(authConfig.JWTSecret))
	authProtected.HandleFunc("/me", authHandler.MeHandler).Methods("GET")

	// Admin routes (protected with role check)
	adminRoutes := r.PathPrefix("/api").Subrouter()
	adminRoutes.Use(auth.RequireRole("admin", authConfig.JWTSecret))
	adminRoutes.Handle("/recent", recentHandler).Methods("GET")

	// API routes (currently public)
	api := r.PathPrefix("/api").Subrouter()
	api.Handle("/lookup", lookupHandler).Methods("GET")
	api.Handle("/submit", submitHandler).Methods("POST")
	api.Handle("/additions", additionsHandler).Methods("GET")
	api.Handle("/top", topHandler).Methods("GET")
	api.Handle("/stats", statsHandler).Methods("GET")
	api.Handle("/fetch", handlers.NewFetchHandler(db)).Methods("GET")

	// WebSocket endpoints
	api.HandleFunc("/ws/stats", wsHandler.ServeHTTP).Methods("GET")

	// Server configuration
	port := os.Getenv("PORT")
	if port == "" {
		port = "8080"
	}

	srv := &http.Server{
		Handler:      r,
		Addr:         ":" + port,
		WriteTimeout: 60 * time.Second,
		ReadTimeout:  30 * time.Second,
		IdleTimeout:  120 * time.Second,
	}

	log.Printf("Starting server on port %s...", port)
	if err := srv.ListenAndServe(); err != nil {
		log.Fatal(err)
	}
}
