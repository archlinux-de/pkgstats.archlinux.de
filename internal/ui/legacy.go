package ui

import (
	"net/http"
	"regexp"
	"strings"
)

type legacyRoute struct {
	pattern *regexp.Regexp
	target  string // redirect target with {1}, {2} placeholders; empty means 410 Gone
}

var legacyRoutes = []legacyRoute{
	// Old webpack-built assets served under /img/ with optional content hashes
	{regexp.MustCompile(`^/img/(archicon|archlogo)(?:\.[a-f0-9]+)?\.svg$`), "/static/{1}.svg"},
	{regexp.MustCompile(`^/img/(fun_\w+)(?:\.[a-f0-9]+)?\.png$`), "/static/{1}.png"},

	// Old nginx rewrite
	{regexp.MustCompile(`^/package$`), "/packages"},

	// Old webpack JS/CSS bundles are gone
	{regexp.MustCompile(`^/js/`), ""},
	{regexp.MustCompile(`^/css/`), ""},

	// Old service worker workbox files
	{regexp.MustCompile(`^/workbox-[a-f0-9]+\.js$`), ""},

	// Catch-all for any other old /img/ paths
	{regexp.MustCompile(`^/img/`), ""},
}

func LegacyMiddleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		for _, route := range legacyRoutes {
			matches := route.pattern.FindStringSubmatch(r.URL.Path)
			if matches == nil {
				continue
			}

			w.Header().Set("Cache-Control", "public, max-age=86400")

			if route.target == "" {
				w.WriteHeader(http.StatusGone)
				return
			}

			target := route.target
			for i := 1; i < len(matches); i++ {
				target = strings.ReplaceAll(target, "{"+string(rune('0'+i))+"}", matches[i])
			}

			http.Redirect(w, r, target, http.StatusMovedPermanently)
			return
		}

		next.ServeHTTP(w, r)
	})
}

func handleLegacyPost(mux *http.ServeMux) {
	mux.Handle("POST /post", http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "text/plain; charset=utf-8")
		w.Header().Set("Cache-Control", "public, max-age=86400")
		w.WriteHeader(http.StatusGone)
		_, _ = w.Write([]byte("pkgstats v2 is no longer supported. Please update.\n"))
	}))
}
