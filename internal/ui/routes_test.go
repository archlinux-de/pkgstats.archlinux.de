package ui

import (
	"io/fs"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"testing/fstest"

	"pkgstatsd/internal/ui/layout"
)

func TestRegisterRoutes_Methodology(t *testing.T) {
	manifest, err := layout.NewManifest([]byte(`{}`))
	if err != nil {
		t.Fatal(err)
	}

	mux := http.NewServeMux()
	RegisterRoutes(
		mux,
		manifest,
		nil, nil, nil, nil,
		fstest.MapFS{"dist/assets/main.css": {Data: []byte("body {}")}},
		fstest.MapFS{"static/archicon.svg": {Data: []byte("<svg/>")}},
		fstest.MapFS{},
		false,
	)

	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, httptest.NewRequest(http.MethodGet, "/methodology", nil))

	if rr.Code != http.StatusOK {
		t.Fatalf("status = %d, want %d", rr.Code, http.StatusOK)
	}

	body := rr.Body.String()
	for _, text := range []string{
		"<title>How popularity is measured</title>",
		`rel="canonical" href="http://example.com/methodology"`,
	} {
		if !strings.Contains(body, text) {
			t.Errorf("response does not contain %q", text)
		}
	}
}

func TestRootFiles(t *testing.T) {
	root := fstest.MapFS{
		"root/favicon.ico":          &fstest.MapFile{Data: []byte("icon")},
		"root/manifest.webmanifest": &fstest.MapFile{Data: []byte(`{"name":"test"}`)},
		"root/robots.txt":           &fstest.MapFile{Data: []byte("User-agent: *")},
		"root/service-worker.js":    &fstest.MapFile{Data: []byte("// sw")},
	}

	tests := []struct {
		name        string
		path        string
		handler     func(*http.ServeMux, fs.FS)
		contentType string
		body        string
	}{
		{
			name:        "favicon",
			path:        "/favicon.ico",
			handler:     handleFavicon,
			contentType: "image/vnd.microsoft.icon",
			body:        "icon",
		},
		{
			name:        "manifest",
			path:        "/manifest.webmanifest",
			handler:     handleManifest,
			contentType: "application/manifest+json",
			body:        `{"name":"test"}`,
		},
		{
			name:        "robots",
			path:        "/robots.txt",
			handler:     handleRobots,
			contentType: "text/plain; charset=utf-8",
			body:        "User-agent: *",
		},
		{
			name:        "service-worker",
			path:        "/service-worker.js",
			handler:     handleServiceWorker,
			contentType: "text/javascript; charset=utf-8",
			body:        "// sw",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			mux := http.NewServeMux()
			tt.handler(mux, root)

			req := httptest.NewRequest(http.MethodGet, tt.path, nil)
			rr := httptest.NewRecorder()
			mux.ServeHTTP(rr, req)

			if rr.Code != http.StatusOK {
				t.Errorf("expected status 200, got %d", rr.Code)
			}

			ct := rr.Header().Get("Content-Type")
			if ct != tt.contentType {
				t.Errorf("expected Content-Type %q, got %q", tt.contentType, ct)
			}

			cc := rr.Header().Get("Cache-Control")
			if cc == "" {
				t.Error("expected Cache-Control header to be set")
			}

			if body := rr.Body.String(); body != tt.body {
				t.Errorf("expected body %q, got %q", tt.body, body)
			}
		})
	}
}
