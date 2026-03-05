package compare

import (
	"net/http"
	"net/url"
	"strings"

	"pkgstatsd/internal/chartdata"
	"pkgstatsd/internal/packages"
	"pkgstatsd/internal/ui/layout"
	"pkgstatsd/internal/web"
)

const maxPackages = 10

type Handler struct {
	repo     packages.Repository
	manifest *layout.Manifest
}

func NewHandler(repo packages.Repository, manifest *layout.Manifest) *Handler {
	return &Handler{repo: repo, manifest: manifest}
}

func (h *Handler) HandleCompare(w http.ResponseWriter, r *http.Request) {
	namesParam := r.PathValue("names")
	if namesParam == "" {
		http.NotFound(w, r)
		return
	}

	names := strings.Split(namesParam, ",")
	if len(names) > maxPackages {
		names = names[:maxPackages]
	}

	var allSeries []packages.PackagePopularity
	for _, name := range names {
		name = strings.TrimSpace(name)
		if name == "" {
			continue
		}

		list, err := h.repo.FindSeriesByName(r.Context(), name, 0, web.GetLastCompleteMonth(), layout.SeriesLimit, 0)
		if err != nil {
			layout.ServerError(w, "failed to fetch package series", err)
			return
		}

		allSeries = append(allSeries, list.PackagePopularities...)
	}

	if len(allSeries) == 0 {
		http.NotFound(w, r)
		return
	}

	data := chartdata.Build(allSeries)

	escapedNames := make([]string, len(names))
	for i, n := range names {
		escapedNames[i] = url.PathEscape(n)
	}

	layout.Render(w, r,
		layout.Page{Title: "Compare packages", Description: "Compare the popularity of Arch Linux packages side by side.", Path: "/packages", Manifest: h.manifest, CanonicalPath: "/compare/packages/" + strings.Join(escapedNames, ",")},
		CompareContent(names, data),
	)
}

func (h *Handler) HandleLegacyCompare(w http.ResponseWriter, r *http.Request) {
	layout.Render(w, r,
		layout.Page{Title: "Compare packages", Path: "/packages", Manifest: h.manifest, CanonicalPath: "/compare/packages"},
		LegacyCompareContent(),
	)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /compare/packages", h.HandleLegacyCompare)
	mux.HandleFunc("GET /compare/packages/{names...}", h.HandleCompare)
}
