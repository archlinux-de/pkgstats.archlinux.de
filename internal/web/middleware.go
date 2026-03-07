package web

import (
	"log/slog"
	"net/http"
	"runtime/debug"
	"strconv"
	"strings"
	"time"
)

type Middleware func(http.Handler) http.Handler

// Chain applies middlewares so the first in the list is outermost.
func Chain(h http.Handler, middlewares ...Middleware) http.Handler {
	for i := len(middlewares) - 1; i >= 0; i-- {
		h = middlewares[i](h)
	}
	return h
}

func Recovery() Middleware {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			defer func() {
				if err := recover(); err != nil {
					slog.Error("panic recovered",
						"error", err,
						"stack", string(debug.Stack()),
					)
					InternalServerError(w, "internal server error")
				}
			}()
			next.ServeHTTP(w, r)
		})
	}
}

func SecureHeaders() Middleware {
	csp := strings.Join([]string{
		"default-src 'self'",
		"script-src 'self'",
		"style-src 'self' 'unsafe-inline'",
		"img-src 'self' data:",
		"object-src 'none'",
		"base-uri 'self'",
		"frame-ancestors 'none'",
	}, "; ")
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.Header().Set("Content-Security-Policy", csp)
			w.Header().Set("X-Content-Type-Options", "nosniff")
			w.Header().Set("Referrer-Policy", "strict-origin-when-cross-origin")
			next.ServeHTTP(w, r)
		})
	}
}

func CORS() Middleware {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.Header().Set("Access-Control-Allow-Origin", "*")
			next.ServeHTTP(w, r)
		})
	}
}

// CacheControl sets Cache-Control with the given max-age for both
// browser and shared (proxy/CDN) caches.
func CacheControl(maxAge time.Duration) Middleware {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			if r.Method == http.MethodGet {
				w.Header().Set("Cache-Control", "public, max-age="+formatSeconds(maxAge))
			}
			next.ServeHTTP(w, r)
		})
	}
}

func formatSeconds(d time.Duration) string {
	return strconv.Itoa(int(d.Seconds()))
}

// setAPICacheControl overrides Cache-Control for API responses, setting
// s-maxage until the first day of next month (data only changes monthly).
func setAPICacheControl(w http.ResponseWriter, maxAge time.Duration) {
	sMaxAge := secondsUntilNextMonth()
	w.Header().Set("Cache-Control",
		"public, max-age="+formatSeconds(maxAge)+
			", s-maxage="+strconv.Itoa(sMaxAge)+
			", stale-while-revalidate=86400")
}

func secondsUntilNextMonth() int {
	now := time.Now()
	firstOfNextMonth := time.Date(now.Year(), now.Month()+1, 1, 0, 0, 0, 0, now.Location())
	seconds := int(time.Until(firstOfNextMonth).Seconds())
	if seconds < 0 {
		return 0
	}
	return seconds
}
