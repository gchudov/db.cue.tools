package database

import (
	"database/sql"
	"fmt"
	"time"
)

type User struct {
	Email     string
	Role      string
	CreatedAt time.Time
	LastLogin *time.Time
}

// GetUserByEmail retrieves a user by email address
func GetUserByEmail(db *sql.DB, email string) (*User, error) {
	var user User
	err := db.QueryRow(`
		SELECT email, role, created_at, last_login
		FROM users
		WHERE email = $1
	`, email).Scan(&user.Email, &user.Role, &user.CreatedAt, &user.LastLogin)

	if err == sql.ErrNoRows {
		return nil, fmt.Errorf("user not found")
	}
	if err != nil {
		return nil, fmt.Errorf("database error: %w", err)
	}

	return &user, nil
}

// UpdateLastLogin updates the last login timestamp
func UpdateLastLogin(db *sql.DB, email string) error {
	_, err := db.Exec(`
		UPDATE users
		SET last_login = NOW()
		WHERE email = $1
	`, email)
	return err
}
