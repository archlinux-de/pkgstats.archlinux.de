package packagedetail

import (
	"log/slog"
	"net/http"

	"pkgstats.archlinux.de/internal/chartdata"
	"pkgstats.archlinux.de/internal/packages"
	"pkgstats.archlinux.de/internal/ui/layout"
)

const (
	seriesLimit = 10000
	maxEndMonth = 999912
)

type Handler struct {
	repo     packages.Repository
	manifest *layout.Manifest
}

func NewHandler(repo packages.Repository, manifest *layout.Manifest) *Handler {
	return &Handler{repo: repo, manifest: manifest}
}

func (h *Handler) HandlePackageDetail(w http.ResponseWriter, r *http.Request) {
	name := r.PathValue("name")
	if name == "" {
		http.NotFound(w, r)
		return
	}

	list, err := h.repo.FindSeriesByName(r.Context(), name, 0, maxEndMonth, seriesLimit, 0)
	if err != nil {
		slog.Error("failed to fetch package series", "error", err)
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	if list.Total == 0 {
		http.NotFound(w, r)
		return
	}

	data := chartdata.Build(list.PackagePopularities)

	w.Header().Set("Cache-Control", "public, max-age=300")
	component := layout.Base(
		layout.Page{Title: name + " - Package statistics", Path: "/packages", Manifest: h.manifest},
		PackageDetailContent(name, data),
	)
	_ = component.Render(r.Context(), w)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /packages/{name}", h.HandlePackageDetail)
}
