package compare

import (
	"log/slog"
	"net/http"
	"strings"

	"pkgstats.archlinux.de/internal/chartdata"
	"pkgstats.archlinux.de/internal/packages"
	"pkgstats.archlinux.de/internal/ui/layout"
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

		list, err := h.repo.FindSeriesByName(r.Context(), name, 0, layout.MaxEndMonth, layout.SeriesLimit, 0)
		if err != nil {
			slog.Error("failed to fetch package series", "error", err, "name", name)
			continue
		}

		allSeries = append(allSeries, list.PackagePopularities...)
	}

	if len(allSeries) == 0 {
		http.NotFound(w, r)
		return
	}

	data := chartdata.Build(allSeries)

	layout.Render(w, r,
		layout.Page{Title: "Compare packages", Path: "/packages", Manifest: h.manifest},
		CompareContent(names, data),
	)
}

func (h *Handler) HandleLegacyCompare(w http.ResponseWriter, r *http.Request) {
	layout.Render(w, r,
		layout.Page{Title: "Compare packages", Path: "/packages", Manifest: h.manifest},
		LegacyCompareContent(),
	)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /compare/packages", h.HandleLegacyCompare)
	mux.HandleFunc("GET /compare/packages/{names...}", h.HandleCompare)
}
