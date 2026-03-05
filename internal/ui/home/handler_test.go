package home

import (
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"pkgstatsd/internal/ui/layout"
)

func TestHandleHome(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	handler := NewHandler(manifest)

	req := httptest.NewRequest(http.MethodGet, "/", nil)
	rr := httptest.NewRecorder()

	handler.HandleHome(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status 200, got %d", rr.Code)
	}

	body := rr.Body.String()
	if !strings.Contains(body, "Arch Linux package statistics") {
		t.Error("expected body to contain title")
	}
	if !strings.Contains(body, `name="description"`) {
		t.Error("expected body to contain meta description")
	}
	if !strings.Contains(body, `rel="canonical"`) {
		t.Error("expected body to contain canonical link")
	}
	if !strings.Contains(body, `og:title`) {
		t.Error("expected body to contain og:title meta tag")
	}
	if !strings.Contains(body, `application/ld+json`) {
		t.Error("expected body to contain JSON-LD script")
	}
	if !strings.Contains(body, `SearchAction`) {
		t.Error("expected body to contain SearchAction schema")
	}
	if !strings.Contains(body, `"@type":"Dataset"`) {
		t.Error("expected body to contain Dataset schema")
	}
}

func TestHandleHome_NotFound(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	handler := NewHandler(manifest)

	req := httptest.NewRequest(http.MethodGet, "/not-home", nil)
	rr := httptest.NewRecorder()

	handler.HandleHome(rr, req)

	if rr.Code != http.StatusNotFound {
		t.Errorf("expected status 404, got %d", rr.Code)
	}
}
