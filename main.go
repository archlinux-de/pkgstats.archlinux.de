package main

import (
	"log/slog"
	"net/http"
	"os"
	"time"

	"pkgstatsd/internal/apidoc"
	"pkgstatsd/internal/countries"
	"pkgstatsd/internal/database"
	"pkgstatsd/internal/mirrors"
	"pkgstatsd/internal/operatingsystems"
	"pkgstatsd/internal/osarchitectures"
	"pkgstatsd/internal/packages"
	"pkgstatsd/internal/sitemap"
	"pkgstatsd/internal/submit"
	"pkgstatsd/internal/systemarchitectures"
	"pkgstatsd/internal/ui"
	"pkgstatsd/internal/ui/errorpage"
	uilayout "pkgstatsd/internal/ui/layout"
	"pkgstatsd/internal/web"
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

	// Setup repositories (for UI pages that need direct repo access)
	packagesRepo := packages.NewSQLiteRepository(db)
	countriesRepo := countries.NewSQLiteRepository(db)
	systemArchRepo := systemarchitectures.NewSQLiteRepository(db)
	submitRepo := submit.NewRepository(db)

	// Setup GeoIP lookup
	var geoip submit.GeoIPLookup
	geoip, err = submit.NewMaxMindGeoIP(cfg.GeoIPDatabase)
	if err != nil {
		slog.Warn("geoip database not available, country detection disabled", "path", cfg.GeoIPDatabase, "error", err)
		geoip = submit.NoopGeoIP{}
	} else {
		defer func() { _ = geoip.Close() }()
	}

	// Setup rate limiter
	var rateLimiter submit.RateLimiter
	if cfg.Environment == "development" || cfg.Environment == "test" {
		rateLimiter = submit.NewInMemoryRateLimiter()
	} else {
		rateLimiter = submit.NewSQLiteRateLimiter(db)
	}

	// Parse Vite manifest
	manifest, err := uilayout.NewManifest(embedManifest)
	if err != nil {
		return err
	}

	// Setup HTTP routes
	mux := http.NewServeMux()

	packages.NewHandler(packagesRepo).RegisterRoutes(mux)
	countries.NewHandler(db).RegisterRoutes(mux)
	mirrors.NewHandler(db).RegisterRoutes(mux)
	systemarchitectures.NewHandler(db).RegisterRoutes(mux)
	operatingsystems.NewHandler(db).RegisterRoutes(mux)
	osarchitectures.NewHandler(db).RegisterRoutes(mux)
	submit.NewHandler(submitRepo, geoip, rateLimiter).RegisterRoutes(mux)
	sitemap.NewHandler().RegisterRoutes(mux)
	apidoc.NewHandler().RegisterRoutes(mux)
	ui.RegisterRoutes(mux, manifest, packagesRepo, countriesRepo, systemArchRepo, embedAssets, embedStatic)

	// Apply middleware stack
	handler := web.Chain(mux,
		web.Recovery(),
		web.CORS(),
		errorpage.Middleware(manifest),
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
		// @TODO add error handling
		Database:      getEnv("DATABASE", ""),
		GeoIPDatabase: getEnv("GEOIP_DATABASE", ""),
		Port:          getEnv("PORT", "8282"),
		Environment:   getEnv("ENVIRONMENT", ""),
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
