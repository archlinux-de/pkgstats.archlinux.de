package fun

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

func (h *Handler) HandleFun(w http.ResponseWriter, r *http.Request) {
	layout.Render(w, r,
		layout.Page{Title: "Fun statistics", Description: "Which browser, editor, or desktop environment is most popular on Arch Linux? Find out here.", Path: "/fun", Manifest: h.manifest},
		FunContent(Categories),
	)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /fun", h.HandleFun)
}
