package ui

import (
	"fmt"
	"io/fs"
	"net/http"

	"pkgstatsd/internal/countries"
	"pkgstatsd/internal/packages"
	"pkgstatsd/internal/systemarchitectures"
	"pkgstatsd/internal/ui/apidocpage"
	"pkgstatsd/internal/ui/compare"
	"pkgstatsd/internal/ui/countrypage"
	"pkgstatsd/internal/ui/fun"
	"pkgstatsd/internal/ui/fundetail"
	"pkgstatsd/internal/ui/home"
	"pkgstatsd/internal/ui/layout"
	"pkgstatsd/internal/ui/legal"
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
