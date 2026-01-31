package main

import (
	"log/slog"
	"net/http"
	"os"
	"time"

	"pkgstats.archlinux.de/internal/countries"
	"pkgstats.archlinux.de/internal/database"
	"pkgstats.archlinux.de/internal/mirrors"
	"pkgstats.archlinux.de/internal/packages"
	"pkgstats.archlinux.de/internal/systemarchitectures"
	"pkgstats.archlinux.de/internal/web"
)

const defaultCacheMaxAge = 5 * time.Minute

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
	countriesRepo := countries.NewSQLiteRepository(db)
	mirrorsRepo := mirrors.NewSQLiteRepository(db)
	systemArchRepo := systemarchitectures.NewSQLiteRepository(db)

	// Setup HTTP routes
	mux := http.NewServeMux()

	packages.NewHandler(packagesRepo).RegisterRoutes(mux)
	countries.NewHandler(countriesRepo).RegisterRoutes(mux)
	mirrors.NewHandler(mirrorsRepo).RegisterRoutes(mux)
	systemarchitectures.NewHandler(systemArchRepo).RegisterRoutes(mux)

	// Health check
	mux.HandleFunc("GET /", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "text/plain")
		_, _ = w.Write([]byte("pkgstatsd is running"))
	})

	// Apply middleware stack
	handler := web.Chain(mux,
		web.Recovery(),
		web.Logger(),
		web.CORS(),
		web.CacheControl(defaultCacheMaxAge),
	)

	// Create and start server
	server := web.NewServer(":"+cfg.Port, handler)
	return server.ListenAndServe()
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
