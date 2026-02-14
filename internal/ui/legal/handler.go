package legal

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

func (h *Handler) HandleImpressum(w http.ResponseWriter, r *http.Request) {
	layout.Render(w, r,
		layout.Page{Title: "Impressum", Path: "/impressum", Manifest: h.manifest, NoIndex: true},
		ImpressumContent(),
	)
}

func (h *Handler) HandlePrivacyPolicy(w http.ResponseWriter, r *http.Request) {
	layout.Render(w, r,
		layout.Page{Title: "Privacy policy", Path: "/privacy-policy", Manifest: h.manifest, NoIndex: true},
		PrivacyPolicyContent(),
	)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /impressum", h.HandleImpressum)
	mux.HandleFunc("GET /privacy-policy", h.HandlePrivacyPolicy)
}
