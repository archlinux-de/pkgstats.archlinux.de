package packagepage

import (
	"log/slog"
	"net/http"
	"strconv"
	"time"

	"pkgstats.archlinux.de/internal/packages"
	"pkgstats.archlinux.de/internal/ui/layout"
)

const (
	defaultLimit    = 25
	maxLimit        = 250
	monthMultiplier = 100
)

type Handler struct {
	repo     packages.Repository
	manifest *layout.Manifest
}

func NewHandler(repo packages.Repository, manifest *layout.Manifest) *Handler {
	return &Handler{repo: repo, manifest: manifest}
}

func (h *Handler) HandlePackages(w http.ResponseWriter, r *http.Request) {
	query := r.URL.Query().Get("query")
	offset := parseIntParam(r, "offset", 0)
	limit := parseIntParam(r, "limit", defaultLimit)

	limit = min(limit, maxLimit)
	limit = max(limit, 1)
	offset = max(offset, 0)

	now := time.Now()
	currentMonth := now.Year()*monthMultiplier + int(now.Month())

	list, err := h.repo.FindAll(r.Context(), query, currentMonth, currentMonth, limit, offset)
	if err != nil {
		slog.Error("failed to fetch packages", "error", err)
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Cache-Control", "public, max-age=300")
	component := layout.Base(layout.Page{Title: "Package statistics", Path: "/packages", Manifest: h.manifest}, PackagesContent(list, query, offset, limit))
	_ = component.Render(r.Context(), w)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /packages", h.HandlePackages)
}

func parseIntParam(r *http.Request, key string, defaultValue int) int {
	s := r.URL.Query().Get(key)
	if s == "" {
		return defaultValue
	}
	v, err := strconv.Atoi(s)
	if err != nil {
		return defaultValue
	}
	return v
}
