package methodology

import (
	"net/http"

	"pkgstatsd/internal/ui/layout"
)

type Handler struct {
	manifest *layout.Manifest
}

func NewHandler(manifest *layout.Manifest) *Handler {
	return &Handler{manifest: manifest}
}

func (h *Handler) HandleMethodology(w http.ResponseWriter, r *http.Request) {
	layout.Render(w, r,
		layout.Page{Title: "How popularity is measured", Description: "How pkgstats calculates package and system popularity statistics.", Path: "/methodology", Manifest: h.manifest, CanonicalPath: "/methodology"},
		MethodologyContent(),
	)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /methodology", h.HandleMethodology)
}
