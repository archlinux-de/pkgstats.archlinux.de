package sitemap

import (
	"encoding/xml"
	"log/slog"
	"net/http"
	"net/url"
	"strings"
	"time"

	"pkgstatsd/internal/countries"
	"pkgstatsd/internal/packages"
	"pkgstatsd/internal/ui/fun"
	"pkgstatsd/internal/ui/layout"
	"pkgstatsd/internal/web"
)

const (
	packageLimit = 5000
	countryLimit = 300
)

type URLSet struct {
	XMLName xml.Name `xml:"urlset"`
	XMLNS   string   `xml:"xmlns,attr"`
	URLs    []URL    `xml:"url"`
}

type URL struct {
	Loc     string `xml:"loc"`
	LastMod string `xml:"lastmod,omitempty"`
}

type Handler struct {
	packageRepo packages.Repository
	countryRepo countries.Repository
}

func NewHandler(packageRepo packages.Repository, countryRepo countries.Repository) *Handler {
	return &Handler{packageRepo: packageRepo, countryRepo: countryRepo}
}

// lastDayOfMonth returns the last day of the month encoded as YYYYMM in "2006-01-02" format.
func lastDayOfMonth(yearMonth int) string {
	year, month := web.SplitYearMonth(yearMonth)
	return time.Date(year, month+1, 0, 0, 0, 0, 0, time.UTC).Format("2006-01-02")
}

func (h *Handler) HandleSitemap(w http.ResponseWriter, r *http.Request) {
	baseURL := layout.GetBaseURL(r)
	currentMonth := web.GetLastCompleteMonth()
	lastMod := lastDayOfMonth(currentMonth)

	urls := []URL{
		{Loc: baseURL + "/", LastMod: lastMod},
		{Loc: baseURL + "/getting-started"},
		{Loc: baseURL + "/countries", LastMod: lastMod},
		{Loc: baseURL + "/packages", LastMod: lastMod},
		{Loc: baseURL + "/compare/system-architectures/current", LastMod: lastMod},
		{Loc: baseURL + "/compare/operating-systems", LastMod: lastMod},
		{Loc: baseURL + "/fun", LastMod: lastMod},
	}

	for _, category := range fun.Categories {
		urls = append(urls, URL{Loc: baseURL + "/fun/" + url.PathEscape(category.Name) + "/current", LastMod: lastMod})
		urls = append(urls, URL{Loc: baseURL + "/fun/" + url.PathEscape(category.Name) + "/history", LastMod: lastMod})
	}

	countryList, err := h.countryRepo.FindAll(r.Context(), "", currentMonth, currentMonth, countryLimit, 0)
	if err != nil {
		if web.IsClientDisconnect(err) {
			return
		}
		slog.Error("failed to fetch countries for sitemap", "error", err)
	} else {
		for _, country := range countryList.CountryPopularities {
			urls = append(urls, URL{Loc: baseURL + "/countries/" + strings.ToLower(country.Code), LastMod: lastMod})
		}
	}

	packageList, err := h.packageRepo.FindAll(r.Context(), "", currentMonth, currentMonth, packageLimit, 0)
	if err != nil {
		if web.IsClientDisconnect(err) {
			return
		}
		slog.Error("failed to fetch packages for sitemap", "error", err)
	} else {
		for _, pkg := range packageList.PackagePopularities {
			urls = append(urls, URL{Loc: baseURL + "/packages/" + url.PathEscape(pkg.Name), LastMod: lastMod})
		}
	}

	sitemap := URLSet{
		XMLNS: "http://www.sitemaps.org/schemas/sitemap/0.9",
		URLs:  urls,
	}

	w.Header().Set("Cache-Control", "public, max-age=86400")
	w.Header().Set("Content-Type", "application/xml; charset=UTF-8")

	_, _ = w.Write([]byte(xml.Header))

	encoder := xml.NewEncoder(w)
	encoder.Indent("", "  ")
	_ = encoder.Encode(sitemap)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /sitemap.xml", h.HandleSitemap)
}
