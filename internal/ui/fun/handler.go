package fun

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

func (h *Handler) HandleFun(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Cache-Control", "public, max-age=300")
	component := layout.Base(
		layout.Page{Title: "Fun statistics", Path: "/fun", Manifest: h.manifest},
		FunContent(Categories),
	)
	_ = component.Render(r.Context(), w)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /fun", h.HandleFun)
}
