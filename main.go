package main

import (
	"context"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"pkgstats.archlinux.de/internal/database"
	"pkgstats.archlinux.de/internal/packages"
)

const (
	readTimeout     = 10 * time.Second
	writeTimeout    = 30 * time.Second
	idleTimeout     = 60 * time.Second
	shutdownTimeout = 10 * time.Second
)

func main() {
	if err := run(); err != nil {
		slog.Error("fatal error", "error", err)
		os.Exit(1)
	}
}

func run() error {
	// Load configuration from environment
	cfg := loadConfig()

	// Setup logger
	logger := setupLogger(cfg.Environment)
	slog.SetDefault(logger)

	// Initialize database
	db, err := database.New(cfg.Database)
	if err != nil {
		return err
	}
	defer func() { _ = db.Close() }()

	// Setup repositories
	packagesRepo := packages.NewSQLiteRepository(db)

	// Setup HTTP server
	mux := http.NewServeMux()

	// Register routes
	packagesHandler := packages.NewHandler(packagesRepo)
	packagesHandler.RegisterRoutes(mux)

	// Health check (temporary, for initial testing)
	mux.HandleFunc("GET /", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "text/plain")
		_, _ = w.Write([]byte("pkgstatsd is running"))
	})

	server := &http.Server{
		Addr:         ":" + cfg.Port,
		Handler:      mux,
		ReadTimeout:  readTimeout,
		WriteTimeout: writeTimeout,
		IdleTimeout:  idleTimeout,
	}

	// Graceful shutdown setup
	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	// Start server in goroutine
	errChan := make(chan error, 1)
	go func() {
		slog.Info("starting server", "port", cfg.Port, "environment", cfg.Environment)
		if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			errChan <- err
		}
	}()

	// Wait for shutdown signal or error
	select {
	case err := <-errChan:
		return err
	case <-ctx.Done():
	}

	slog.Info("shutting down server")

	// Give outstanding requests time to complete
	shutdownCtx, cancel := context.WithTimeout(context.Background(), shutdownTimeout)
	defer cancel()

	if err := server.Shutdown(shutdownCtx); err != nil {
		return err
	}

	slog.Info("server stopped")
	return nil
}

type config struct {
	Database      string
	GeoIPDatabase string
	Port          string
	Environment   string
}

func loadConfig() config {
	return config{
		Database:      getEnv("DATABASE", "./pkgstats.db"),
		GeoIPDatabase: getEnv("GEOIP_DATABASE", "/usr/share/GeoIP/GeoLite2-Country.mmdb"),
		Port:          getEnv("PORT", "8080"),
		Environment:   getEnv("ENVIRONMENT", "production"),
	}
}

func getEnv(key, defaultValue string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return defaultValue
}

func setupLogger(environment string) *slog.Logger {
	var handler slog.Handler
	if environment == "development" {
		handler = slog.NewTextHandler(os.Stdout, &slog.HandlerOptions{
			Level: slog.LevelDebug,
		})
	} else {
		handler = slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{
			Level: slog.LevelInfo,
		})
	}
	return slog.New(handler)
}
