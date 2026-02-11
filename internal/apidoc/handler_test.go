package apidoc

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestHandleDocJSON(t *testing.T) {
	handler := NewHandler()
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
}
