package submit

import (
	"fmt"
	"net/http"
	"net/netip"
	"time"

	"pkgstatsd/internal/web"
)

const (
	defaultMaxMissing  = 0.35
	maxRequestBodySize = 5 << 20 // 5 MB
)

type Handler struct {
	repo             *Repository
	geoip            GeoIPLookup
	limiter          RateLimiter
	expectedPackages []string
	maxMissing       float64
}

func NewHandler(repo *Repository, geoip GeoIPLookup, limiter RateLimiter, expectedPackages []string) *Handler {
	return &Handler{
		repo:             repo,
		geoip:            geoip,
		limiter:          limiter,
		expectedPackages: expectedPackages,
		maxMissing:       defaultMaxMissing,
	}
}

func (h *Handler) HandleSubmit(w http.ResponseWriter, r *http.Request) {
	clientIP := getClientIP(r)

	anonymizedIP := AnonymizeIP(clientIP)
	allowed, retryAfter, err := h.limiter.Allow(r.Context(), anonymizedIP)
	if err != nil {
		web.ServerError(w, "rate limit check failed", err)
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

	r.Body = http.MaxBytesReader(w, r.Body, maxRequestBodySize)
	req, err := ParseRequest(r.Body)
	if err != nil {
		web.BadRequest(w, err.Error())
		return
	}

	if err := ValidateExpectedPackages(req.Pacman.Packages, h.expectedPackages, h.maxMissing); err != nil {
		web.BadRequest(w, err.Error())
		return
	}

	if clientIP.IsValid() {
		req.Country = h.geoip.GetCountryCode(clientIP)
	}

	mirrorURL := FilterMirrorURL(req.Pacman.Mirror)

	if err := h.repo.SaveSubmission(r.Context(), req, mirrorURL); err != nil {
		web.ServerError(w, "failed to save submission", err)
		return
	}

	w.Header().Set("Cache-Control", "no-store")
	w.WriteHeader(http.StatusNoContent)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("POST /api/submit", h.HandleSubmit)
}

// getClientIP extracts the client IP, checking X-Real-IP (set by nginx)
// before falling back to RemoteAddr.
func getClientIP(r *http.Request) netip.Addr {
	if xri := r.Header.Get("X-Real-IP"); xri != "" {
		if ip, err := netip.ParseAddr(xri); err == nil {
			return ip
		}
	}

	host := r.RemoteAddr
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

	if len(host) > 2 && host[0] == '[' && host[len(host)-1] == ']' {
		host = host[1 : len(host)-1]
	}

	ip, _ := netip.ParseAddr(host)
	return ip
}
