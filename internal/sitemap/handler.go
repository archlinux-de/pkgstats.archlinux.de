package sitemap

import (
	"encoding/xml"
	"net/http"
)

type URLSet struct {
	XMLName xml.Name `xml:"urlset"`
	XMLNS   string   `xml:"xmlns,attr"`
	URLs    []URL    `xml:"url"`
}

type URL struct {
	Loc string `xml:"loc"`
}

type Handler struct{}

func NewHandler() *Handler {
	return &Handler{}
}

func (h *Handler) HandleSitemap(w http.ResponseWriter, r *http.Request) {
	baseURL := getBaseURL(r)

	sitemap := URLSet{
		XMLNS: "http://www.sitemaps.org/schemas/sitemap/0.9",
		URLs: []URL{
			{Loc: baseURL + "/"},
			{Loc: baseURL + "/countries"},
			{Loc: baseURL + "/packages"},
			{Loc: baseURL + "/compare/system-architectures/current"},
			{Loc: baseURL + "/compare/operating-systems"},
			{Loc: baseURL + "/fun"},
		},
	}

	w.Header().Set("Content-Type", "application/xml; charset=UTF-8")

	_, _ = w.Write([]byte(xml.Header))

	encoder := xml.NewEncoder(w)
	encoder.Indent("", "  ")
	_ = encoder.Encode(sitemap)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /sitemap.xml", h.HandleSitemap)
}

// getBaseURL constructs the base URL from the request.
func getBaseURL(r *http.Request) string {
	scheme := "http"
	if r.TLS != nil {
		scheme = "https"
	}

	// Check X-Forwarded-Proto header (set by nginx reverse proxy)
	if proto := r.Header.Get("X-Forwarded-Proto"); proto == "https" {
		scheme = proto
	}

	return scheme + "://" + r.Host
}
