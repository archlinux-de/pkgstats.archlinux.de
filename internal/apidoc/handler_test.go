package apidoc

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestHandleDocJSON(t *testing.T) {
	handler := NewHandler(true)
	mux := http.NewServeMux()
	handler.RegisterRoutes(mux)

	req := httptest.NewRequest(http.MethodGet, "/api/doc.json", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status 200, got %d", rr.Code)
	}

	if ct := rr.Header().Get("Content-Type"); ct != "application/json" {
		t.Fatalf("expected Content-Type application/json, got %q", ct)
	}

	var doc map[string]any
	if err := json.Unmarshal(rr.Body.Bytes(), &doc); err != nil {
		t.Fatalf("response is not valid JSON: %v", err)
	}

	if doc["openapi"] != "3.0.0" {
		t.Errorf("expected openapi 3.0.0, got %v", doc["openapi"])
	}

	info, ok := doc["info"].(map[string]any)
	if !ok {
		t.Fatal("expected info to be an object")
	}

	if info["title"] != "pkgstats API documentation" {
		t.Errorf("expected title 'pkgstats API documentation', got %v", info["title"])
	}

	paths, ok := doc["paths"].(map[string]any)
	if !ok {
		t.Fatal("expected paths to be an object")
	}

	expectedPaths := []string{
		"/api/packages",
		"/api/packages/{name}",
		"/api/packages/{name}/series",
		"/api/countries",
		"/api/countries/{code}",
		"/api/countries/{code}/series",
		"/api/mirrors",
		"/api/mirrors/{url}",
		"/api/mirrors/{url}/series",
		"/api/system-architectures",
		"/api/system-architectures/{name}",
		"/api/system-architectures/{name}/series",
		"/api/operating-systems",
		"/api/operating-systems/{id}",
		"/api/operating-systems/{id}/series",
		"/api/operating-system-architectures",
		"/api/operating-system-architectures/{name}",
		"/api/operating-system-architectures/{name}/series",
		"/api/submit",
	}

	for _, p := range expectedPaths {
		if _, found := paths[p]; !found {
			t.Errorf("expected path %q in development spec", p)
		}
	}
}

func TestHandleDocJSONProduction(t *testing.T) {
	handler := NewHandler(false)
	mux := http.NewServeMux()
	handler.RegisterRoutes(mux)

	req := httptest.NewRequest(http.MethodGet, "/api/doc.json", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	var doc map[string]any
	if err := json.Unmarshal(rr.Body.Bytes(), &doc); err != nil {
		t.Fatalf("response is not valid JSON: %v", err)
	}

	paths, ok := doc["paths"].(map[string]any)
	if !ok {
		t.Fatal("expected paths to be an object")
	}

	publicPaths := []string{
		"/api/packages",
		"/api/packages/{name}",
		"/api/packages/{name}/series",
	}
	for _, p := range publicPaths {
		if _, found := paths[p]; !found {
			t.Errorf("expected public path %q in production spec", p)
		}
	}

	internalPaths := []string{
		"/api/countries",
		"/api/mirrors",
		"/api/system-architectures",
		"/api/operating-systems",
		"/api/operating-system-architectures",
		"/api/submit",
	}
	for _, p := range internalPaths {
		if _, found := paths[p]; found {
			t.Errorf("internal path %q should not appear in production spec", p)
		}
	}
}
