package home

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

func (h *Handler) HandleHome(w http.ResponseWriter, r *http.Request) {
	if r.URL.Path != "/" {
		http.NotFound(w, r)
		return
	}

	baseURL := layout.GetBaseURL(r)
	layout.Render(w, r,
		layout.Page{
			Title:       "Arch Linux package statistics",
			Description: "How popular is your favorite Arch Linux package? Browse usage statistics collected from voluntary pkgstats submissions.",
			Path:        "/",
			Manifest:    h.manifest,
			JsonLD: map[string]any{
				"website-schema": webSiteSchema(baseURL),
				"dataset-schema": datasetSchema(baseURL),
			},
		},
		HomeContent(),
	)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /", h.HandleHome)
}
