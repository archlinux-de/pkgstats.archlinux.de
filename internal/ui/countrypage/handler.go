package countrypage

import (
	"log/slog"
	"net/http"
	"time"

	"pkgstats.archlinux.de/internal/countries"
	"pkgstats.archlinux.de/internal/ui/layout"
)

const (
	monthMultiplier = 100
	allCountries    = 300
)

type Handler struct {
	repo     countries.Repository
	manifest *layout.Manifest
}

func NewHandler(repo countries.Repository, manifest *layout.Manifest) *Handler {
	return &Handler{repo: repo, manifest: manifest}
}

func (h *Handler) HandleCountries(w http.ResponseWriter, r *http.Request) {
	now := time.Now()
	currentMonth := now.Year()*monthMultiplier + int(now.Month())

	list, err := h.repo.FindAll(r.Context(), "", currentMonth, currentMonth, allCountries, 0)
	if err != nil {
		slog.Error("failed to fetch countries", "error", err)
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Cache-Control", "public, max-age=300")
	component := layout.Base(
		layout.Page{Title: "Country statistics", Path: "/countries", Manifest: h.manifest},
		CountriesContent(list.CountryPopularities),
	)
	_ = component.Render(r.Context(), w)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /countries", h.HandleCountries)
}
