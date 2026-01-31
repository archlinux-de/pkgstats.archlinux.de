package sitemap

import (
	"encoding/xml"
	"net/http"
)

// URLSet represents a sitemap urlset element.
type URLSet struct {
	XMLName xml.Name `xml:"urlset"`
	XMLNS   string   `xml:"xmlns,attr"`
	URLs    []URL    `xml:"url"`
}

// URL represents a sitemap url element.
type URL struct {
	Loc string `xml:"loc"`
}

// Handler handles HTTP requests for the sitemap.
type Handler struct{}

// NewHandler creates a new Handler.
func NewHandler() *Handler {
	return &Handler{}
}

// HandleSitemap handles GET /sitemap.xml
func (h *Handler) HandleSitemap(w http.ResponseWriter, r *http.Request) {
	baseURL := getBaseURL(r)

	sitemap := URLSet{
		XMLNS: "http://www.sitemaps.org/schemas/sitemap/0.9",
		URLs: []URL{
			{Loc: baseURL + "/"},
			{Loc: baseURL + "/fun"},
			{Loc: baseURL + "/packages"},
		},
	}

	w.Header().Set("Content-Type", "application/xml; charset=UTF-8")

	// Write XML declaration
	_, _ = w.Write([]byte(xml.Header))

	// Encode the sitemap
	encoder := xml.NewEncoder(w)
	encoder.Indent("", "  ")
	_ = encoder.Encode(sitemap)
}

// RegisterRoutes registers the sitemap routes on the given mux.
func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /sitemap.xml", h.HandleSitemap)
}

// getBaseURL constructs the base URL from the request.
func getBaseURL(r *http.Request) string {
	scheme := "http"
	if r.TLS != nil {
		scheme = "https"
	}

	// Check X-Forwarded-Proto header
	if proto := r.Header.Get("X-Forwarded-Proto"); proto != "" {
		scheme = proto
	}

	return scheme + "://" + r.Host
}
