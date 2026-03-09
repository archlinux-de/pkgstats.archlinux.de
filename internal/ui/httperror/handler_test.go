package httperror

import (
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"pkgstatsd/internal/ui/layout"
	"pkgstatsd/internal/web"
)

func TestErrorPageCacheControl(t *testing.T) {
	manifest := &layout.Manifest{}

	// A handler that always returns 404
	notFound := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		http.NotFound(w, r)
	})

	// Apply middleware in the same order as main.go:
	// httperror.Middleware wraps the handler, CacheControl is outer
	handler := web.CacheControl(5 * time.Minute)(Middleware(manifest)(notFound))

	req := httptest.NewRequest(http.MethodGet, "/nonexistent", nil)
	req.Header.Set("Accept", "text/html")
	rr := httptest.NewRecorder()
	handler.ServeHTTP(rr, req)

	if rr.Code != http.StatusNotFound {
		t.Fatalf("expected status 404, got %d", rr.Code)
	}

	if cc := rr.Header().Get("Cache-Control"); cc != "no-store" {
		t.Errorf("expected Cache-Control %q, got %q", "no-store", cc)
	}
}
