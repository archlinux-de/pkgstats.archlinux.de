package ui

import (
	"fmt"
	"io/fs"
	"net/http"

	"pkgstats.archlinux.de/internal/countries"
	"pkgstats.archlinux.de/internal/packages"
	"pkgstats.archlinux.de/internal/systemarchitectures"
	"pkgstats.archlinux.de/internal/ui/apidocpage"
	"pkgstats.archlinux.de/internal/ui/compare"
	"pkgstats.archlinux.de/internal/ui/countrypage"
	"pkgstats.archlinux.de/internal/ui/fun"
	"pkgstats.archlinux.de/internal/ui/fundetail"
	"pkgstats.archlinux.de/internal/ui/home"
	"pkgstats.archlinux.de/internal/ui/layout"
	"pkgstats.archlinux.de/internal/ui/legal"
	"pkgstats.archlinux.de/internal/ui/packagedetail"
	"pkgstats.archlinux.de/internal/ui/packagepage"
	uisysarch "pkgstats.archlinux.de/internal/ui/systemarchitectures"
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
	assets, static fs.FS,
) {
	home.NewHandler(manifest).RegisterRoutes(mux)
	packagepage.NewHandler(pkgRepo, manifest).RegisterRoutes(mux)
	packagedetail.NewHandler(pkgRepo, manifest).RegisterRoutes(mux)
	compare.NewHandler(pkgRepo, manifest).RegisterRoutes(mux)
	countrypage.NewHandler(countriesRepo, manifest).RegisterRoutes(mux)
	uisysarch.NewHandler(systemArchRepo, manifest).RegisterRoutes(mux)
	fun.NewHandler(manifest).RegisterRoutes(mux)
	fundetail.NewHandler(pkgRepo, manifest).RegisterRoutes(mux)
	apidocpage.NewHandler(manifest).RegisterRoutes(mux)
	legal.NewHandler(manifest).RegisterRoutes(mux)

	handleAssets(mux, assets)
	handleStatic(mux, static)
}

func handleAssets(mux *http.ServeMux, assets fs.FS) {
	sub, _ := fs.Sub(assets, "dist/assets")
	fileServer := http.FileServer(http.FS(sub))
	mux.Handle("GET /assets/", http.StripPrefix("/assets/", cacheHandler(fileServer, assetsCacheMaxAge)))
}

func handleStatic(mux *http.ServeMux, static fs.FS) {
	sub, _ := fs.Sub(static, "static")
	fileServer := http.FileServer(http.FS(sub))
	mux.Handle("GET /static/", http.StripPrefix("/static/", cacheHandler(fileServer, staticCacheMaxAge)))
}

func cacheHandler(next http.Handler, maxAge int) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Cache-Control", fmt.Sprintf("public, max-age=%d", maxAge))
		next.ServeHTTP(w, r)
	})
}
