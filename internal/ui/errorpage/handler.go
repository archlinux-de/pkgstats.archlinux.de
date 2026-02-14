package errorpage

import (
	"net/http"
	"strings"

	"github.com/a-h/templ"

	"pkgstats.archlinux.de/internal/ui/layout"
)

// Middleware returns an HTTP middleware that intercepts error responses (4xx/5xx)
// for HTML-accepting requests and renders styled error pages instead of plain text.
func Middleware(manifest *layout.Manifest) func(http.Handler) http.Handler {
	return func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			if !acceptsHTML(r) {
				next.ServeHTTP(w, r)
				return
			}

			ew := &errorWriter{ResponseWriter: w}
			next.ServeHTTP(ew, r)

			if ew.intercepted {
				renderErrorPage(w, r, manifest, ew.status)
			}
		})
	}
}

// errorWriter intercepts WriteHeader/Write for error status codes.
// For non-error responses, all calls pass through to the underlying ResponseWriter.
type errorWriter struct {
	http.ResponseWriter
	status      int
	intercepted bool
}

func (ew *errorWriter) WriteHeader(code int) {
	ew.status = code

	if code >= http.StatusBadRequest {
		ew.intercepted = true
		return
	}

	ew.ResponseWriter.WriteHeader(code)
}

func (ew *errorWriter) Write(b []byte) (int, error) {
	if ew.intercepted {
		return len(b), nil
	}

	return ew.ResponseWriter.Write(b)
}

func renderErrorPage(w http.ResponseWriter, r *http.Request, manifest *layout.Manifest, status int) {
	page := layout.Page{Title: http.StatusText(status), Path: "", Manifest: manifest, NoIndex: true}

	var content templ.Component
	if status == http.StatusNotFound {
		page.Title = "File not found"
		content = NotFoundContent(r.URL.Path)
	} else {
		content = ErrorContent(http.StatusText(status))
	}

	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.WriteHeader(status)
	_ = layout.Base(page, content).Render(r.Context(), w)
}

func acceptsHTML(r *http.Request) bool {
	accept := r.Header.Get("Accept")
	return accept == "" || strings.Contains(accept, "text/html") || strings.Contains(accept, "*/*")
}
