package ui

import (
	"fmt"
	"io/fs"
	"net/http"
	"strings"

	"pkgstatsd/internal/countries"
	"pkgstatsd/internal/operatingsystems"
	"pkgstatsd/internal/packages"
	"pkgstatsd/internal/systemarchitectures"
	"pkgstatsd/internal/ui/apidocpage"
	"pkgstatsd/internal/ui/compare"
	"pkgstatsd/internal/ui/countrypage"
	"pkgstatsd/internal/ui/fun"
	"pkgstatsd/internal/ui/fundetail"
	"pkgstatsd/internal/ui/gettingstarted"
	"pkgstatsd/internal/ui/home"
	"pkgstatsd/internal/ui/layout"
	"pkgstatsd/internal/ui/legal"
	uios "pkgstatsd/internal/ui/operatingsystems"
	"pkgstatsd/internal/ui/packagedetail"
	"pkgstatsd/internal/ui/packagepage"
	uisysarch "pkgstatsd/internal/ui/systemarchitectures"
)

const (
	assetsCacheMaxAge = 31536000 // 1 year
	staticCacheMaxAge = 86400    // 1 day
)

func RegisterRoutes(
	mux *http.ServeMux,
	manifest *layout.Manifest,
	pkgRepo packages.Repository,
	countriesRepo countries.Repository,
	systemArchRepo systemarchitectures.Repository,
	osRepo operatingsystems.Repository,
	assets, static, root fs.FS,
) {
	home.NewHandler(manifest).RegisterRoutes(mux)
	gettingstarted.NewHandler(manifest).RegisterRoutes(mux)
	packagepage.NewHandler(pkgRepo, manifest).RegisterRoutes(mux)
	packagedetail.NewHandler(pkgRepo, manifest).RegisterRoutes(mux)
	compare.NewHandler(pkgRepo, manifest).RegisterRoutes(mux)
	countrypage.NewHandler(countriesRepo, manifest).RegisterRoutes(mux)
	uisysarch.NewHandler(systemArchRepo, manifest).RegisterRoutes(mux)
	uios.NewHandler(osRepo, manifest).RegisterRoutes(mux)
	fun.NewHandler(manifest).RegisterRoutes(mux)
	fundetail.NewHandler(pkgRepo, manifest).RegisterRoutes(mux)
	apidocpage.NewHandler(manifest).RegisterRoutes(mux)
	legal.NewHandler(manifest).RegisterRoutes(mux)

	handleAssets(mux, assets)
	handleStatic(mux, static)
	handleFavicon(mux, root)
	handleManifest(mux, root)
	handleRobots(mux, root)
	handleServiceWorker(mux, root)
	handleLegacyPost(mux)
}

func handleFavicon(mux *http.ServeMux, root fs.FS) {
	mux.Handle("GET /favicon.ico", cacheHandler(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		http.ServeFileFS(w, r, root, "root/favicon.ico")
	}), staticCacheMaxAge))
}

func handleManifest(mux *http.ServeMux, root fs.FS) {
	mux.Handle("GET /manifest.webmanifest", cacheHandler(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/manifest+json")
		http.ServeFileFS(w, r, root, "root/manifest.webmanifest")
	}), staticCacheMaxAge))
}

func handleRobots(mux *http.ServeMux, root fs.FS) {
	mux.Handle("GET /robots.txt", cacheHandler(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		http.ServeFileFS(w, r, root, "root/robots.txt")
	}), staticCacheMaxAge))
}

func handleServiceWorker(mux *http.ServeMux, root fs.FS) {
	mux.Handle("GET /service-worker.js", cacheHandler(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		http.ServeFileFS(w, r, root, "root/service-worker.js")
	}), staticCacheMaxAge))
}

func handleAssets(mux *http.ServeMux, assets fs.FS) {
	sub, err := fs.Sub(assets, "dist/assets")
	if err != nil {
		panic(err)
	}
	fileServer := http.FileServer(http.FS(sub))
	mux.Handle("GET /assets/", http.StripPrefix("/assets/", cacheHandler(fileServer, assetsCacheMaxAge, "immutable")))
}

func handleStatic(mux *http.ServeMux, static fs.FS) {
	sub, err := fs.Sub(static, "static")
	if err != nil {
		panic(err)
	}
	fileServer := http.FileServer(http.FS(sub))
	mux.Handle("GET /static/", http.StripPrefix("/static/", cacheHandler(fileServer, staticCacheMaxAge)))
}

func cacheHandler(next http.Handler, maxAge int, directives ...string) http.Handler {
	value := fmt.Sprintf("public, max-age=%d", maxAge)
	if len(directives) > 0 {
		value += ", " + strings.Join(directives, ", ")
	}
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Cache-Control", value)
		next.ServeHTTP(w, r)
	})
}
