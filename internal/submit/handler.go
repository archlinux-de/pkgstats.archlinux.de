package submit

import (
	"fmt"
	"log/slog"
	"net/http"
	"net/netip"
	"time"

	"pkgstatsd/internal/web"
)

const defaultMaxMissing = 0.35

// Handler handles HTTP requests for the submit endpoint.
type Handler struct {
	repo             *Repository
	geoip            GeoIPLookup
	limiter          RateLimiter
	expectedPackages []string
	maxMissing       float64
}

// NewHandler creates a new Handler with the given dependencies.
func NewHandler(repo *Repository, geoip GeoIPLookup, limiter RateLimiter) *Handler {
	return &Handler{
		repo:             repo,
		geoip:            geoip,
		limiter:          limiter,
		expectedPackages: []string{"pkgstats", "pacman"},
		maxMissing:       defaultMaxMissing,
	}
}

// HandleSubmit handles POST /api/submit
func (h *Handler) HandleSubmit(w http.ResponseWriter, r *http.Request) {
	// Get client IP
	clientIP := getClientIP(r)

	// Check rate limit
	anonymizedIP := AnonymizeIP(clientIP)
	allowed, retryAfter, err := h.limiter.Allow(r.Context(), anonymizedIP)
	if err != nil {
		slog.Error("rate limit check failed", "error", err)
		web.InternalServerError(w, "internal server error")
		return
	}

	if !allowed {
		retrySeconds := max(1, int(time.Until(retryAfter).Seconds()))
		web.TooManyRequests(w,
			fmt.Sprintf("Rate limit exceeded. Retry after %s.", retryAfter.Format(time.RFC3339)),
			retrySeconds,
		)
		return
	}

	// Parse and validate request
	req, err := ParseRequest(r.Body)
	if err != nil {
		web.BadRequest(w, err.Error())
		return
	}

	// Validate expected packages
	if err := ValidateExpectedPackages(req.Pacman.Packages, h.expectedPackages, h.maxMissing); err != nil {
		web.BadRequest(w, err.Error())
		return
	}

	// Get country from GeoIP
	if clientIP.IsValid() {
		req.Country = h.geoip.GetCountryCode(clientIP)
	}

	// Filter and normalize mirror URL
	mirrorURL := FilterMirrorURL(req.Pacman.Mirror)

	// Save submission
	if err := h.repo.SaveSubmission(r.Context(), req, mirrorURL); err != nil {
		slog.Error("failed to save submission", "error", err)
		web.InternalServerError(w, "failed to save submission")
		return
	}

	// Return 204 No Content on success
	w.WriteHeader(http.StatusNoContent)
}

// RegisterRoutes registers the submit routes on the given mux.
func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("POST /api/submit", h.HandleSubmit)
}

// getClientIP extracts the client IP from the request.
// Checks X-Forwarded-For header first (for reverse proxy setups),
// then falls back to RemoteAddr.
func getClientIP(r *http.Request) netip.Addr {
	// Check X-Forwarded-For header (first IP is the original client)
	if xff := r.Header.Get("X-Forwarded-For"); xff != "" {
		// X-Forwarded-For can contain multiple IPs: "client, proxy1, proxy2"
		// We want the first one (original client)
		for i := range len(xff) {
			if xff[i] == ',' {
				xff = xff[:i]
				break
			}
		}
		if ip, err := netip.ParseAddr(xff); err == nil {
			return ip
		}
	}

	// Check X-Real-IP header
	if xri := r.Header.Get("X-Real-IP"); xri != "" {
		if ip, err := netip.ParseAddr(xri); err == nil {
			return ip
		}
	}

	// Fall back to RemoteAddr (host:port format)
	host := r.RemoteAddr
	// Strip port if present
	for i := len(host) - 1; i >= 0; i-- {
		if host[i] == ':' {
			host = host[:i]
			break
		}
		if host[i] == ']' {
			// IPv6 address in brackets, no port
			break
		}
	}

	// Handle IPv6 addresses in brackets
	if len(host) > 2 && host[0] == '[' && host[len(host)-1] == ']' {
		host = host[1 : len(host)-1]
	}

	ip, _ := netip.ParseAddr(host)
	return ip
}
