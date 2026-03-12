package apidoc

import (
	"net/http"

	"pkgstatsd/internal/apidoc"
	"pkgstatsd/internal/ui/layout"
)

type Handler struct {
	manifest *layout.Manifest
	spec     *apidoc.OpenAPISpec
}

func NewHandler(manifest *layout.Manifest, spec *apidoc.OpenAPISpec) *Handler {
	return &Handler{manifest: manifest, spec: spec}
}

func (h *Handler) HandleAPIDoc(w http.ResponseWriter, r *http.Request) {
	layout.Render(w, r,
		layout.Page{Title: "API documentation", Path: "/api/doc", Manifest: h.manifest, NoIndex: true},
		APIDocContent(h.spec),
	)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /api/doc", h.HandleAPIDoc)
}
