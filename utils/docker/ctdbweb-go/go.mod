module github.com/cuetools/ctdbweb

go 1.23

require (
	github.com/golang-jwt/jwt/v5 v5.2.1
	github.com/gorilla/mux v1.8.1
	github.com/gorilla/websocket v1.5.3
	github.com/lib/pq v1.10.9
	golang.org/x/oauth2 v0.24.0
)

require cloud.google.com/go/compute/metadata v0.3.0 // indirect
