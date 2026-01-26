# Multi-stage Dockerfile for Go backend

# Development stage with Air hot-reload
FROM golang:1.23-alpine AS development

# Install Air for hot-reload (pin to v1.61.1 for Go 1.23 compatibility)
RUN go install github.com/air-verse/air@v1.61.1

WORKDIR /app

# Copy go.mod and go.sum for dependency caching
COPY go.mod go.sum ./
RUN go mod download

# Copy source code (can be overridden by volume mount in docker-compose)
COPY . .

# Expose port
EXPOSE 8080

# Run Air for hot-reload
CMD ["air", "-c", ".air.toml"]

# Build stage
FROM golang:1.23-alpine AS builder

WORKDIR /app

# Copy dependency files
COPY go.mod go.sum ./
RUN go mod download

# Copy source code
COPY . .

# Build the application
RUN CGO_ENABLED=0 GOOS=linux go build -a -installsuffix cgo -ldflags="-s -w" -o server ./cmd/server

# Production stage
FROM alpine:latest AS production

# Install ca-certificates for HTTPS requests
RUN apk --no-cache add ca-certificates

WORKDIR /root/

# Copy the binary from builder
COPY --from=builder /app/server .

# Expose port
EXPOSE 8080

# Run the server
CMD ["./server"]
