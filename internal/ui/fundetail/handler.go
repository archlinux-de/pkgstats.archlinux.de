package fundetail

import (
	"log/slog"
	"net/http"
	"sort"

	"pkgstatsd/internal/chartdata"
	"pkgstatsd/internal/packages"
	"pkgstatsd/internal/ui/fun"
	"pkgstatsd/internal/ui/layout"
)

const (
	top5Limit     = 5
	top5Filter    = "top5"
	presetCurrent = "current"
	presetHistory = "history"
)

type Handler struct {
	repo     packages.Repository
	manifest *layout.Manifest
}

func NewHandler(repo packages.Repository, manifest *layout.Manifest) *Handler {
	return &Handler{repo: repo, manifest: manifest}
}

func (h *Handler) HandleFunDetail(w http.ResponseWriter, r *http.Request) {
	categoryName := r.PathValue("category")
	preset := r.PathValue("preset")

	category := fun.FindCategory(categoryName)
	if category == nil {
		http.NotFound(w, r)
		return
	}

	if preset != presetCurrent && preset != presetHistory {
		http.NotFound(w, r)
		return
	}

	if preset == presetCurrent {
		h.handleCurrent(w, r, category)
	} else {
		h.handleHistory(w, r, category)
	}
}

func (h *Handler) handleCurrent(w http.ResponseWriter, r *http.Request, category *fun.Category) {
	currentMonth := layout.CurrentMonth()

	var pkgs []packages.PackagePopularity

	for _, name := range category.Packages {
		pkg, err := h.repo.FindByName(r.Context(), name, currentMonth, currentMonth)
		if err != nil {
			slog.Error("failed to fetch package", "error", err, "name", name)
			continue
		}

		pkgs = append(pkgs, *pkg)
	}

	sort.Slice(pkgs, func(i, j int) bool {
		return pkgs[i].Popularity > pkgs[j].Popularity
	})

	layout.Render(w, r,
		layout.Page{Title: category.Name + " statistics", Path: "/fun", Manifest: h.manifest},
		FunDetailCurrentContent(category.Name, pkgs),
	)
}

func (h *Handler) handleHistory(w http.ResponseWriter, r *http.Request, category *fun.Category) {
	filter := r.URL.Query().Get("filter")

	var allSeries []packages.PackagePopularity

	for _, name := range category.Packages {
		list, err := h.repo.FindSeriesByName(r.Context(), name, 0, layout.MaxEndMonth, layout.SeriesLimit, 0)
		if err != nil {
			slog.Error("failed to fetch package series", "error", err, "name", name)
			continue
		}

		allSeries = append(allSeries, list.PackagePopularities...)
	}

	data := chartdata.Build(allSeries)

	if filter == top5Filter && len(data.Datasets) > top5Limit {
		data.Datasets = data.Datasets[:top5Limit]
	}

	layout.Render(w, r,
		layout.Page{Title: category.Name + " statistics", Path: "/fun", Manifest: h.manifest},
		FunDetailHistoryContent(category.Name, data, filter),
	)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /fun/{category}/{preset}", h.HandleFunDetail)
}
