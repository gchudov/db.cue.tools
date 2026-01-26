package database

import (
	"database/sql"
	"fmt"
	"os"
	"sync"
	"time"

	_ "github.com/lib/pq" // PostgreSQL driver
)

// DB holds all database connections
type DB struct {
	CTDB        *sql.DB
	MusicBrainz *sql.DB
	Discogs     *sql.DB
	FreeDB      *sql.DB
}

var (
	instance *DB
	once     sync.Once
)

// Config holds database connection configuration
type Config struct {
	Host string
	Port string

	// Database names
	CTDBName        string
	MusicBrainzName string
	DiscogsName     string
	FreeDBName      string

	// Users
	CTDBUser        string
	MusicBrainzUser string
	DiscogsUser     string
	FreeDBUser      string

	// Connection pool settings
	MaxOpenConns    int
	MaxIdleConns    int
	ConnMaxLifetime time.Duration
}

// DefaultConfig returns default database configuration
func DefaultConfig() Config {
	host := os.Getenv("POSTGRES_HOST")
	if host == "" {
		host = "pgbouncer"
	}

	port := os.Getenv("POSTGRES_PORT")
	if port == "" {
		port = "6432"
	}

	return Config{
		Host: host,
		Port: port,

		CTDBName:        "ctdb",
		MusicBrainzName: "musicbrainz",
		DiscogsName:     "discogs",
		FreeDBName:      "freedb",

		CTDBUser:        "ctdb_user",
		MusicBrainzUser: "musicbrainz",
		DiscogsUser:     "discogs",
		FreeDBUser:      "freedb_user",

		MaxOpenConns:    25,
		MaxIdleConns:    5,
		ConnMaxLifetime: 5 * time.Minute,
	}
}

// Initialize creates and configures all database connections
func Initialize(cfg Config) (*DB, error) {
	var err error

	once.Do(func() {
		instance = &DB{}

		// Connect to CTDB
		instance.CTDB, err = connect(cfg, cfg.CTDBName, cfg.CTDBUser)
		if err != nil {
			err = fmt.Errorf("failed to connect to CTDB: %w", err)
			return
		}

		// Connect to MusicBrainz
		instance.MusicBrainz, err = connect(cfg, cfg.MusicBrainzName, cfg.MusicBrainzUser)
		if err != nil {
			err = fmt.Errorf("failed to connect to MusicBrainz: %w", err)
			return
		}

		// Connect to Discogs
		instance.Discogs, err = connect(cfg, cfg.DiscogsName, cfg.DiscogsUser)
		if err != nil {
			err = fmt.Errorf("failed to connect to Discogs: %w", err)
			return
		}

		// Connect to FreeDB
		instance.FreeDB, err = connect(cfg, cfg.FreeDBName, cfg.FreeDBUser)
		if err != nil {
			err = fmt.Errorf("failed to connect to FreeDB: %w", err)
			return
		}
	})

	if err != nil {
		return nil, err
	}

	return instance, nil
}

// connect creates a connection to a PostgreSQL database
func connect(cfg Config, dbname, user string) (*sql.DB, error) {
	// Build connection string
	// Use Unix socket if host starts with '/'
	var connStr string
	if len(cfg.Host) > 0 && cfg.Host[0] == '/' {
		// Unix socket
		connStr = fmt.Sprintf("host=%s port=%s dbname=%s user=%s sslmode=disable",
			cfg.Host, cfg.Port, dbname, user)
	} else {
		// TCP/IP
		connStr = fmt.Sprintf("host=%s port=%s dbname=%s user=%s sslmode=disable",
			cfg.Host, cfg.Port, dbname, user)
	}

	db, err := sql.Open("postgres", connStr)
	if err != nil {
		return nil, fmt.Errorf("failed to open connection: %w", err)
	}

	// Configure connection pool
	db.SetMaxOpenConns(cfg.MaxOpenConns)
	db.SetMaxIdleConns(cfg.MaxIdleConns)
	db.SetConnMaxLifetime(cfg.ConnMaxLifetime)

	// Test connection
	if err := db.Ping(); err != nil {
		return nil, fmt.Errorf("failed to ping database: %w", err)
	}

	return db, nil
}

// GetDB returns the singleton database instance
func GetDB() *DB {
	return instance
}

// Close closes all database connections
func (db *DB) Close() error {
	var errs []error

	if db.CTDB != nil {
		if err := db.CTDB.Close(); err != nil {
			errs = append(errs, fmt.Errorf("CTDB: %w", err))
		}
	}

	if db.MusicBrainz != nil {
		if err := db.MusicBrainz.Close(); err != nil {
			errs = append(errs, fmt.Errorf("MusicBrainz: %w", err))
		}
	}

	if db.Discogs != nil {
		if err := db.Discogs.Close(); err != nil {
			errs = append(errs, fmt.Errorf("Discogs: %w", err))
		}
	}

	if db.FreeDB != nil {
		if err := db.FreeDB.Close(); err != nil {
			errs = append(errs, fmt.Errorf("FreeDB: %w", err))
		}
	}

	if len(errs) > 0 {
		return fmt.Errorf("errors closing databases: %v", errs)
	}

	return nil
}
