package handlers

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"time"

	"github.com/cuetools/ctdbweb/internal/auth"
	"github.com/cuetools/ctdbweb/internal/database"
)

type AuthHandler struct {
	db         *database.DB
	authConfig auth.Config
}

func NewAuthHandler(db *database.DB, authConfig auth.Config) *AuthHandler {
	return &AuthHandler{
		db:         db,
		authConfig: authConfig,
	}
}

// LoginHandler redirects user to Google OAuth consent screen
func (h *AuthHandler) LoginHandler(w http.ResponseWriter, r *http.Request) {
	oauth2Config := h.authConfig.OAuth2Config()

	// Generate random state token (in production, store in session)
	state := fmt.Sprintf("%d", time.Now().UnixNano())

	// Redirect to Google
	url := oauth2Config.AuthCodeURL(state)
	http.Redirect(w, r, url, http.StatusTemporaryRedirect)
}

// CallbackHandler processes OAuth callback from Google
func (h *AuthHandler) CallbackHandler(w http.ResponseWriter, r *http.Request) {
	oauth2Config := h.authConfig.OAuth2Config()

	// Get authorization code
	code := r.URL.Query().Get("code")
	if code == "" {
		http.Error(w, "No authorization code", http.StatusBadRequest)
		return
	}

	// Debug logging
	log.Printf("OAuth callback received - Redirect URI: %s", oauth2Config.RedirectURL)
	log.Printf("OAuth callback - Code length: %d", len(code))

	// Exchange code for token
	token, err := oauth2Config.Exchange(context.Background(), code)
	if err != nil {
		log.Printf("Failed to exchange token: %v", err)
		log.Printf("OAuth config - Client ID: %s", oauth2Config.ClientID)
		log.Printf("OAuth config - Redirect URI: %s", oauth2Config.RedirectURL)
		http.Error(w, fmt.Sprintf("Failed to exchange token: %v. Check that redirect URI matches Google Console configuration.", err), http.StatusInternalServerError)
		return
	}

	// Get user info from Google
	client := oauth2Config.Client(context.Background(), token)
	resp, err := client.Get("https://www.googleapis.com/oauth2/v2/userinfo")
	if err != nil {
		log.Printf("Failed to get user info: %v", err)
		http.Error(w, "Failed to get user info", http.StatusInternalServerError)
		return
	}
	defer resp.Body.Close()

	var userInfo struct {
		Email string `json:"email"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&userInfo); err != nil {
		http.Error(w, "Failed to decode user info", http.StatusInternalServerError)
		return
	}

	// Check if user exists in database
	log.Printf("Looking up user in database: %s", userInfo.Email)
	user, err := database.GetUserByEmail(h.db.CTDB, userInfo.Email)
	if err != nil {
		log.Printf("Unauthorized login attempt: %s - Error: %v", userInfo.Email, err)
		http.Error(w, "Access denied: User not authorized", http.StatusForbidden)
		return
	}
	log.Printf("User found: %s (role: %s)", user.Email, user.Role)

	// Update last login timestamp
	_ = database.UpdateLastLogin(h.db.CTDB, user.Email)

	// Generate JWT
	jwtToken, err := auth.GenerateJWT(user.Email, user.Role, h.authConfig.JWTSecret)
	if err != nil {
		log.Printf("Failed to generate JWT: %v", err)
		http.Error(w, "Failed to create session", http.StatusInternalServerError)
		return
	}

	// Set httpOnly cookie
	http.SetCookie(w, &http.Cookie{
		Name:     "auth_token",
		Value:    jwtToken,
		Path:     "/",
		Domain:   h.authConfig.CookieDomain,
		MaxAge:   86400, // 24 hours
		HttpOnly: true,
		Secure:   true, // HTTPS only
		SameSite: http.SameSiteLaxMode,
	})

	// Redirect to React UI
	http.Redirect(w, r, "/ui/", http.StatusTemporaryRedirect)
}

// LogoutHandler clears the auth cookie
func (h *AuthHandler) LogoutHandler(w http.ResponseWriter, r *http.Request) {
	http.SetCookie(w, &http.Cookie{
		Name:     "auth_token",
		Value:    "",
		Path:     "/",
		Domain:   h.authConfig.CookieDomain,
		MaxAge:   -1, // Delete cookie
		HttpOnly: true,
		Secure:   true,
		SameSite: http.SameSiteLaxMode,
	})

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	fmt.Fprintf(w, `{"success":true}`)
}

// MeHandler returns current user info
func (h *AuthHandler) MeHandler(w http.ResponseWriter, r *http.Request) {
	claims, ok := auth.GetUserFromContext(r)
	if !ok {
		http.Error(w, `{"error":"Unauthorized"}`, http.StatusUnauthorized)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(http.StatusOK)
	json.NewEncoder(w).Encode(map[string]string{
		"email": claims.Email,
		"role":  claims.Role,
	})
}
