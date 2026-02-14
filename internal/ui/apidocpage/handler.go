package apidocpage

import (
	"net/http"

	"pkgstats.archlinux.de/internal/ui/layout"
)

type Handler struct {
	manifest *layout.Manifest
}

func NewHandler(manifest *layout.Manifest) *Handler {
	return &Handler{manifest: manifest}
}

func (h *Handler) HandleAPIDoc(w http.ResponseWriter, r *http.Request) {
	layout.Render(w, r,
		layout.Page{Title: "API documentation", Path: "/api/doc", Manifest: h.manifest, NoIndex: true},
		APIDocContent(),
	)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /api/doc", h.HandleAPIDoc)
}
