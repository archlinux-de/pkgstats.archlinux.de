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
	w.Header().Set("Cache-Control", "public, max-age=300")
	component := layout.Base(layout.Page{Title: "Impressum", Path: "/impressum", Manifest: h.manifest, NoIndex: true}, ImpressumContent())
	_ = component.Render(r.Context(), w)
}

func (h *Handler) HandlePrivacyPolicy(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Cache-Control", "public, max-age=300")
	component := layout.Base(layout.Page{Title: "Privacy policy", Path: "/privacy-policy", Manifest: h.manifest, NoIndex: true}, PrivacyPolicyContent())
	_ = component.Render(r.Context(), w)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /impressum", h.HandleImpressum)
	mux.HandleFunc("GET /privacy-policy", h.HandlePrivacyPolicy)
}
