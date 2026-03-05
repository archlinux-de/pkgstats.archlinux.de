package fundetail

import (
	"net/http"
	"net/url"
	"sort"
	"strings"

	"pkgstatsd/internal/chartdata"
	"pkgstatsd/internal/packages"
	"pkgstatsd/internal/ui/fun"
	"pkgstatsd/internal/ui/layout"
	"pkgstatsd/internal/web"
)

const (
	tableLimit    = 15
	chartLimit    = 10
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

func (h *Handler) fetchSortedPackages(r *http.Request, category *fun.Category, currentMonth int) ([]packages.PackagePopularity, error) {
	var pkgs []packages.PackagePopularity

	for _, name := range category.Packages {
		pkg, err := h.repo.FindByName(r.Context(), name, currentMonth, currentMonth)
		if err != nil {
			return nil, err
		}

		pkgs = append(pkgs, *pkg)
	}

	sort.Slice(pkgs, func(i, j int) bool {
		return pkgs[i].Popularity > pkgs[j].Popularity
	})

	return pkgs, nil
}

func (h *Handler) handleCurrent(w http.ResponseWriter, r *http.Request, category *fun.Category) {
	currentMonth := web.GetLastCompleteMonth()

	pkgs, err := h.fetchSortedPackages(r, category, currentMonth)
	if err != nil {
		layout.ServerError(w, "failed to fetch package", err)
		return
	}

	var topPkgs, otherPkgs []packages.PackagePopularity
	if len(pkgs) > tableLimit {
		topPkgs = pkgs[:tableLimit]
		otherPkgs = pkgs[tableLimit:]
	} else {
		topPkgs = pkgs
	}

	layout.Render(w, r,
		layout.Page{Title: category.Name + " statistics", Description: "Current popularity ranking of " + category.Name + " on Arch Linux.", Path: "/fun", Manifest: h.manifest, CanonicalPath: "/fun/" + category.Name + "/current"},
		FunDetailCurrentContent(category.Name, topPkgs, otherPkgs, compareURL(pkgs)),
	)
}

func (h *Handler) handleHistory(w http.ResponseWriter, r *http.Request, category *fun.Category) {
	currentMonth := web.GetLastCompleteMonth()

	var allSeries []packages.PackagePopularity

	for _, name := range category.Packages {
		list, err := h.repo.FindSeriesByName(r.Context(), name, 0, currentMonth, layout.SeriesLimit, 0)
		if err != nil {
			layout.ServerError(w, "failed to fetch package series", err)
			return
		}

		allSeries = append(allSeries, list.PackagePopularities...)
	}

	data := chartdata.Build(allSeries)

	// Build compare URL from all datasets (sorted by latest popularity) before truncating for the chart
	cmpURL := compareURLFromDatasets(data.Datasets)

	if len(data.Datasets) > chartLimit {
		data.Datasets = data.Datasets[:chartLimit]
	}

	layout.Render(w, r,
		layout.Page{Title: category.Name + " statistics", Description: "Popularity of " + category.Name + " on Arch Linux over time.", Path: "/fun", Manifest: h.manifest, CanonicalPath: "/fun/" + category.Name + "/history"},
		FunDetailHistoryContent(category.Name, data, cmpURL),
	)
}

func compareURL(pkgs []packages.PackagePopularity) string {
	names := make([]string, len(pkgs))
	for i, pkg := range pkgs {
		names[i] = url.QueryEscape(pkg.Name)
	}
	return "/packages?compare=" + strings.Join(names, ",")
}

func compareURLFromDatasets(datasets []chartdata.Dataset) string {
	names := make([]string, len(datasets))
	for i, ds := range datasets {
		names[i] = url.QueryEscape(ds.Label)
	}
	return "/packages?compare=" + strings.Join(names, ",")
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /fun/{category}/{preset}", h.HandleFunDetail)
}
