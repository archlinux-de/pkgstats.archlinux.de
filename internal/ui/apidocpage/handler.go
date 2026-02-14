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
	w.Header().Set("Cache-Control", "public, max-age=300")
	component := layout.Base(
		layout.Page{Title: "API documentation", Path: "/api/doc", Manifest: h.manifest, NoIndex: true},
		APIDocContent(),
	)
	_ = component.Render(r.Context(), w)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /api/doc", h.HandleAPIDoc)
}
