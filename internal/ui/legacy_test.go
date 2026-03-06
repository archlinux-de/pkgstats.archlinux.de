package ui

import (
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestLegacyMiddleware(t *testing.T) {
	next := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	})
	handler := LegacyMiddleware(next)

	tests := []struct {
		name       string
		path       string
		wantStatus int
		wantTarget string
	}{
		{"archicon without hash", "/img/archicon.svg", http.StatusMovedPermanently, "/static/archicon.svg"},
		{"archicon with hash", "/img/archicon.a1b2c3.svg", http.StatusMovedPermanently, "/static/archicon.svg"},
		{"archlogo without hash", "/img/archlogo.svg", http.StatusMovedPermanently, "/static/archlogo.svg"},
		{"archlogo with hash", "/img/archlogo.deadbeef.svg", http.StatusMovedPermanently, "/static/archlogo.svg"},
		{"fun image without hash", "/img/fun_barChart_darkMode.png", http.StatusMovedPermanently, "/static/fun_barChart_darkMode.png"},
		{"fun image with hash", "/img/fun_barChart_darkMode.abc123.png", http.StatusMovedPermanently, "/static/fun_barChart_darkMode.png"},
		{"package to packages", "/package", http.StatusMovedPermanently, "/packages"},
		{"old workbox file", "/workbox-08bdcb2c.js", http.StatusGone, ""},
		{"unknown img path", "/img/something-else.png", http.StatusGone, ""},
		{"old js bundle", "/js/app.abc123.js", http.StatusGone, ""},
		{"old css bundle", "/css/app.abc123.css", http.StatusGone, ""},
		{"unrelated path passes through", "/api/packages", http.StatusOK, ""},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			req := httptest.NewRequest(http.MethodGet, tt.path, nil)
			rr := httptest.NewRecorder()
			handler.ServeHTTP(rr, req)

			if rr.Code != tt.wantStatus {
				t.Errorf("status = %d, want %d", rr.Code, tt.wantStatus)
			}

			if tt.wantTarget != "" {
				loc := rr.Header().Get("Location")
				if loc != tt.wantTarget {
					t.Errorf("Location = %q, want %q", loc, tt.wantTarget)
				}
			}

			if tt.wantStatus != http.StatusOK {
				cc := rr.Header().Get("Cache-Control")
				if cc == "" {
					t.Error("expected Cache-Control header to be set")
				}
			}
		})
	}
}

func TestLegacyPostEndpoint(t *testing.T) {
	mux := http.NewServeMux()
	handleLegacyPost(mux)

	req := httptest.NewRequest(http.MethodPost, "/post", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusGone {
		t.Errorf("status = %d, want %d", rr.Code, http.StatusGone)
	}

	ct := rr.Header().Get("Content-Type")
	if ct != "text/plain; charset=utf-8" {
		t.Errorf("Content-Type = %q, want %q", ct, "text/plain; charset=utf-8")
	}

	want := "pkgstats v2 is no longer supported. Please update.\n"
	if body := rr.Body.String(); body != want {
		t.Errorf("body = %q, want %q", body, want)
	}
}
