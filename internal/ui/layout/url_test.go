package layout

import (
	"crypto/tls"
	"net/http"
	"net/http/httptest"
	"testing"
)

const testHost = "example.com"

func TestGetBaseURL(t *testing.T) {
	r := httptest.NewRequest(http.MethodGet, "/", nil)
	r.Host = testHost

	if got := GetBaseURL(r); got != "http://example.com" {
		t.Errorf("expected http://example.com, got %s", got)
	}
}

func TestGetBaseURL_TLS(t *testing.T) {
	r := httptest.NewRequest(http.MethodGet, "/", nil)
	r.Host = testHost
	r.TLS = &tls.ConnectionState{}

	if got := GetBaseURL(r); got != "https://example.com" {
		t.Errorf("expected https://example.com, got %s", got)
	}
}

func TestGetBaseURL_XForwardedProto(t *testing.T) {
	r := httptest.NewRequest(http.MethodGet, "/", nil)
	r.Host = testHost
	r.Header.Set("X-Forwarded-Proto", "https")

	if got := GetBaseURL(r); got != "https://example.com" {
		t.Errorf("expected https://example.com, got %s", got)
	}
}
