package country

import (
	"net/http"
	"strings"

	"pkgstatsd/internal/chartdata"
	"pkgstatsd/internal/countries"
	"pkgstatsd/internal/ui/layout"
	"pkgstatsd/internal/web"
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
	currentMonth := web.GetLastCompleteMonth()

	list, err := h.repo.FindAll(r.Context(), "", currentMonth, currentMonth, allCountries, 0)
	if err != nil {
		layout.ServerError(w, "failed to fetch countries", err)
		return
	}

	layout.Render(w, r,
		layout.Page{Title: "Country statistics", Description: "Geographic distribution of Arch Linux pkgstats submissions by country.", Path: "/countries", Manifest: h.manifest},
		CountriesContent(list.CountryPopularities),
	)
}

func (h *Handler) HandleCountryDetail(w http.ResponseWriter, r *http.Request) {
	code := strings.ToUpper(r.PathValue("code"))
	if code == "" {
		http.NotFound(w, r)
		return
	}

	list, err := h.repo.FindSeriesByCode(r.Context(), code, 0, web.GetLastCompleteMonth(), layout.SeriesLimit, 0)
	if err != nil {
		layout.ServerError(w, "failed to fetch country series", err)
		return
	}

	if list.Total == 0 {
		http.NotFound(w, r)
		return
	}

	data := chartdata.Build(list.CountryPopularities)

	layout.Render(w, r,
		layout.Page{Title: code + " - Country statistics", Description: "Popularity of Arch Linux in " + code + " over time.", Path: "/countries", Manifest: h.manifest, CanonicalPath: "/countries/" + strings.ToLower(code)},
		CountryDetailContent(code, data),
	)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /countries", h.HandleCountries)
	mux.HandleFunc("GET /countries/{code}", h.HandleCountryDetail)
}
