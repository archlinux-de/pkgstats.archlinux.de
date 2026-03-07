package layout

import (
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/a-h/templ"
)

func TestRender_LinkPreloadHeader(t *testing.T) {
	manifest := &Manifest{CSS: []string{"/assets/main-abc123.css"}}

	req := httptest.NewRequest(http.MethodGet, "/", nil)
	rr := httptest.NewRecorder()

	Render(rr, req, Page{Manifest: manifest, NoIndex: true}, templ.NopComponent)

	links := rr.Header().Values("Link")
	if len(links) != 1 {
		t.Fatalf("expected 1 Link header, got %d: %v", len(links), links)
	}

	expected := "</assets/main-abc123.css>; rel=preload; as=style"
	if links[0] != expected {
		t.Errorf("Link header = %q, want %q", links[0], expected)
	}
}

func TestRender_NoLinkHeaderWithoutCSS(t *testing.T) {
	manifest, _ := NewManifest([]byte(`{}`))

	req := httptest.NewRequest(http.MethodGet, "/", nil)
	rr := httptest.NewRecorder()

	Render(rr, req, Page{Manifest: manifest, NoIndex: true}, templ.NopComponent)

	if links := rr.Header().Values("Link"); len(links) != 0 {
		t.Errorf("expected no Link headers, got %v", links)
	}
}
