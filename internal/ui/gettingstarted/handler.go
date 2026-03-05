package gettingstarted

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

func (h *Handler) HandleGettingStarted(w http.ResponseWriter, r *http.Request) {
	layout.Render(w, r,
		layout.Page{Title: "Getting started", Description: "Install pkgstats and start contributing anonymous usage statistics to help improve Arch Linux.", Path: "/getting-started", Manifest: h.manifest},
		GettingStartedContent(),
	)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /getting-started", h.HandleGettingStarted)
}
