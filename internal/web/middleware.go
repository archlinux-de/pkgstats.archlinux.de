package web

import (
	"log/slog"
	"net/http"
	"runtime/debug"
	"strconv"
	"time"
)

// Middleware is a function that wraps an http.Handler.
type Middleware func(http.Handler) http.Handler

// Chain applies middlewares in order (first middleware is outermost).
func Chain(h http.Handler, middlewares ...Middleware) http.Handler {
	for i := len(middlewares) - 1; i >= 0; i-- {
		h = middlewares[i](h)
	}
	return h
}

// Logger returns a middleware that logs HTTP requests.
func Logger() Middleware {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			start := time.Now()
			wrapped := &responseWriter{ResponseWriter: w, status: http.StatusOK}

			next.ServeHTTP(wrapped, r)

			slog.Info("http request",
				"method", r.Method,
				"path", r.URL.Path,
				"status", wrapped.status,
				"duration", time.Since(start),
				"remote", r.RemoteAddr,
			)
		})
	}
}

// Recovery returns a middleware that recovers from panics.
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

// CORS returns a middleware that adds CORS headers.
func CORS() Middleware {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.Header().Set("Access-Control-Allow-Origin", "*")
			w.Header().Set("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
			w.Header().Set("Access-Control-Allow-Headers", "Content-Type")

			if r.Method == http.MethodOptions {
				w.WriteHeader(http.StatusNoContent)
				return
			}

			next.ServeHTTP(w, r)
		})
	}
}

// CacheControl returns a middleware that adds cache control headers for GET requests.
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

// responseWriter wraps http.ResponseWriter to capture the status code.
type responseWriter struct {
	http.ResponseWriter
	status int
}

func (rw *responseWriter) WriteHeader(code int) {
	rw.status = code
	rw.ResponseWriter.WriteHeader(code)
}
