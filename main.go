package main

import (
	"context"
	"log/slog"
	"net/http"
	"os"
	"time"

	"pkgstatsd/internal/anomalydetection"
	"pkgstatsd/internal/apidoc"
	"pkgstatsd/internal/config"
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
	"pkgstatsd/internal/ui/httperror"
	uilayout "pkgstatsd/internal/ui/layout"
	"pkgstatsd/internal/web"
)

const defaultCacheMaxAge = 5 * time.Minute

func main() {
	cfg, err := config.Load()
	if err != nil {
		slog.Error("failed to load config", "error", err)
		os.Exit(1)
	}

	if len(os.Args) > 1 && os.Args[1] == "detect-anomalies" {
		os.Exit(anomalydetection.Run(os.Args[2:], cfg))
	}

	if err := run(cfg); err != nil {
		slog.Error("fatal error", "error", err)
		os.Exit(1)
	}
}

func run(cfg config.Config) error {
	// Setup logger
	logger := setupLogger(cfg.IsDevelopment())
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
	osRepo := operatingsystems.NewSQLiteRepository(db)
	osArchRepo := osarchitectures.NewSQLiteRepository(db)
	submitRepo := submit.NewRepository(db)

	// Setup GeoIP lookup
	geoip, err := submit.NewMaxMindGeoIP(cfg.GeoIPDatabase)
	if err != nil {
		return err
	}
	defer func() { _ = geoip.Close() }()

	// Setup rate limiter
	var rateLimiter submit.RateLimiter
	if cfg.IsDevelopment() {
		rateLimiter = submit.NewInMemoryRateLimiter()
	} else {
		rateLimiter = submit.NewSQLiteRateLimiter(db)
	}

	// Parse Vite manifest
	manifest, err := uilayout.NewManifest(embedManifest)
	if err != nil {
		return err
	}

	// Warm up caches
	ctx := context.Background()
	for _, repo := range []interface{ WarmupCache(context.Context) error }{
		packagesRepo, countriesRepo, mirrorsRepo, systemArchRepo, osRepo,
	} {
		if err := repo.WarmupCache(ctx); err != nil {
			slog.Warn("failed to warm up cache", "error", err)
		}
	}

	// Setup HTTP routes
	mux := http.NewServeMux()

	packages.NewHandler(packagesRepo).RegisterRoutes(mux)
	countries.NewHandler(countriesRepo).RegisterRoutes(mux)
	mirrors.NewHandler(mirrorsRepo).RegisterRoutes(mux)
	systemarchitectures.NewHandler(systemArchRepo).RegisterRoutes(mux)
	operatingsystems.NewHandler(osRepo).RegisterRoutes(mux)
	osarchitectures.NewHandler(osArchRepo).RegisterRoutes(mux)
	submit.NewHandler(submitRepo, geoip, rateLimiter, cfg.ExpectedPackages).RegisterRoutes(mux)
	sitemap.NewHandler(packagesRepo).RegisterRoutes(mux)
	apidoc.NewHandler(cfg.IsDevelopment()).RegisterRoutes(mux)
	ui.RegisterRoutes(mux, manifest, packagesRepo, countriesRepo, systemArchRepo, osRepo, embedAssets, embedStatic, embedRoot)

	// Apply middleware stack
	handler := web.Chain(mux,
		web.Recovery(),
		web.SecureHeaders(),
		web.CORS(),
		ui.LegacyMiddleware,
		httperror.Middleware(manifest),
		web.CacheControl(defaultCacheMaxAge),
	)

	// Create and start server
	server := web.NewServer(":"+cfg.Port, handler)
	return server.ListenAndServe()
}

func setupLogger(isDevelopment bool) *slog.Logger {
	var handler slog.Handler
	if isDevelopment {
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
