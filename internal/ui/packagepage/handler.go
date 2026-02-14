package packagepage

import (
	"log/slog"
	"net/http"
	"sort"
	"strconv"
	"strings"

	"pkgstats.archlinux.de/internal/packages"
	"pkgstats.archlinux.de/internal/ui/layout"
)

const (
	defaultLimit = 25
	maxLimit     = 250
	maxCompare   = 10
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
	compare := r.URL.Query().Get("compare")
	offset := parseIntParam(r, "offset", 0)
	limit := parseIntParam(r, "limit", defaultLimit)

	limit = min(limit, maxLimit)
	limit = max(limit, 1)
	offset = max(offset, 0)

	currentMonth := layout.CurrentMonth()

	list, err := h.repo.FindAll(r.Context(), query, currentMonth, currentMonth, limit, offset)
	if err != nil {
		layout.ServerError(w, "failed to fetch packages", err)
		return
	}

	selectedPackages := h.fetchComparePackages(r, compare, currentMonth)

	layout.Render(w, r,
		layout.Page{Title: "Package statistics", Path: "/packages", Manifest: h.manifest},
		PackagesContent(list, query, offset, limit, compare, selectedPackages),
	)
}

func (h *Handler) fetchComparePackages(r *http.Request, compare string, currentMonth int) []packages.PackagePopularity {
	if compare == "" {
		return nil
	}

	names := strings.Split(compare, ",")
	if len(names) > maxCompare {
		names = names[:maxCompare]
	}

	var result []packages.PackagePopularity
	for _, name := range names {
		name = strings.TrimSpace(name)
		if name == "" {
			continue
		}

		pkg, err := h.repo.FindByName(r.Context(), name, currentMonth, currentMonth)
		if err != nil {
			slog.Error("failed to fetch compare package", "error", err, "name", name)
			continue
		}

		result = append(result, *pkg)
	}

	sort.Slice(result, func(i, j int) bool {
		return result[i].Popularity > result[j].Popularity
	})

	return result
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
