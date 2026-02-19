package home

import (
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"pkgstats.archlinux.de/internal/ui/layout"
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
