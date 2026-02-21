package packagedetail

import (
	"net/http"

	"pkgstatsd/internal/chartdata"
	"pkgstatsd/internal/packages"
	"pkgstatsd/internal/ui/layout"
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

	list, err := h.repo.FindSeriesByName(r.Context(), name, 0, layout.MaxEndMonth, layout.SeriesLimit, 0)
	if err != nil {
		layout.ServerError(w, "failed to fetch package series", err)
		return
	}

	if list.Total == 0 {
		http.NotFound(w, r)
		return
	}

	data := chartdata.Build(list.PackagePopularities)

	layout.Render(w, r,
		layout.Page{Title: name + " - Package statistics", Path: "/packages", Manifest: h.manifest},
		PackageDetailContent(name, data),
	)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /packages/{name}", h.HandlePackageDetail)
}
