package web

import (
	"log/slog"
	"net/http"
	"runtime/debug"
	"strconv"
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

func CORS() Middleware {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.Header().Set("Access-Control-Allow-Origin", "*")
			next.ServeHTTP(w, r)
		})
	}
}

// CacheControl sets s-maxage until the first day of next month,
// matching the monthly data refresh cycle. Handlers can override.
func CacheControl(maxAge time.Duration) Middleware {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			if r.Method == http.MethodGet {
				sMaxAge := secondsUntilNextMonth()
				w.Header().Set("Cache-Control",
					"public, max-age="+formatSeconds(maxAge)+
						", s-maxage="+strconv.Itoa(sMaxAge))
			}
			next.ServeHTTP(w, r)
		})
	}
}

func formatSeconds(d time.Duration) string {
	return strconv.Itoa(int(d.Seconds()))
}

// secondsUntilNextMonth returns the number of seconds from now until the
// first day of the next month (matching the PHP Month::create(1) behavior).
func secondsUntilNextMonth() int {
	now := time.Now()
	firstOfNextMonth := time.Date(now.Year(), now.Month()+1, 1, 0, 0, 0, 0, now.Location())
	seconds := int(time.Until(firstOfNextMonth).Seconds())
	if seconds < 0 {
		return 0
	}
	return seconds
}
