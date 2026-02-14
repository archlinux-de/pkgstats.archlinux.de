package home

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

func (h *Handler) HandleHome(w http.ResponseWriter, r *http.Request) {
	if r.URL.Path != "/" {
		http.NotFound(w, r)
		return
	}

	layout.Render(w, r,
		layout.Page{Title: "Arch Linux package statistics", Path: "/", Manifest: h.manifest},
		HomeContent(),
	)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /", h.HandleHome)
}
