package countrypage

import (
	"net/http"

	"pkgstats.archlinux.de/internal/countries"
	"pkgstats.archlinux.de/internal/ui/layout"
)

const allCountries = 300

type Handler struct {
	repo     countries.Repository
	manifest *layout.Manifest
}

func NewHandler(repo countries.Repository, manifest *layout.Manifest) *Handler {
	return &Handler{repo: repo, manifest: manifest}
}

func (h *Handler) HandleCountries(w http.ResponseWriter, r *http.Request) {
	currentMonth := layout.CurrentMonth()

	list, err := h.repo.FindAll(r.Context(), "", currentMonth, currentMonth, allCountries, 0)
	if err != nil {
		layout.ServerError(w, "failed to fetch countries", err)
		return
	}

	layout.Render(w, r,
		layout.Page{Title: "Country statistics", Path: "/countries", Manifest: h.manifest},
		CountriesContent(list.CountryPopularities),
	)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /countries", h.HandleCountries)
}
