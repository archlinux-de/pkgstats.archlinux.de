package packagepage

import (
	"net/http"
	"sort"
	"strconv"
	"strings"

	"pkgstatsd/internal/packages"
	"pkgstatsd/internal/ui/layout"
	"pkgstatsd/internal/web"
)

const (
	defaultLimit = 25
	maxLimit     = 250
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

	currentMonth := web.GetLastCompleteMonth()

	var list *packages.PackagePopularityList
	if query != "" {
		var err error
		list, err = h.repo.FindAll(r.Context(), query, currentMonth, currentMonth, limit, offset)
		if err != nil {
			layout.ServerError(w, "failed to fetch packages", err)
			return
		}
	} else {
		list = &packages.PackagePopularityList{}
	}

	selectedPackages, err := h.fetchComparePackages(r, compare, currentMonth)
	if err != nil {
		layout.ServerError(w, "failed to fetch compare packages", err)
		return
	}

	layout.Render(w, r,
		layout.Page{Title: "Package statistics", Description: "Search and compare Arch Linux package popularity based on pkgstats submissions.", Path: "/packages", Manifest: h.manifest},
		PackagesContent(list, query, offset, limit, compare, selectedPackages),
	)
}

func (h *Handler) fetchComparePackages(r *http.Request, compare string, currentMonth int) ([]packages.PackagePopularity, error) {
	if compare == "" {
		return nil, nil
	}

	names := strings.Split(compare, ",")
	if len(names) > layout.MaxSelectPackages {
		names = names[:layout.MaxSelectPackages]
	}

	var result []packages.PackagePopularity
	for _, name := range names {
		name = strings.TrimSpace(name)
		if name == "" {
			continue
		}

		pkg, err := h.repo.FindByName(r.Context(), name, currentMonth, currentMonth)
		if err != nil {
			return nil, err
		}

		result = append(result, *pkg)
	}

	sort.Slice(result, func(i, j int) bool {
		return result[i].Popularity > result[j].Popularity
	})

	return result, nil
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
