package web

import (
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"
)

func TestChain(t *testing.T) {
	var order []string
	m1 := func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			order = append(order, "m1_in")
			next.ServeHTTP(w, r)
			order = append(order, "m1_out")
		})
	}
	m2 := func(next http.Handler) http.Handler {
		return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			order = append(order, "m2_in")
			next.ServeHTTP(w, r)
			order = append(order, "m2_out")
		})
	}

	h := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		order = append(order, "handler")
	})

	chained := Chain(h, m1, m2)
	chained.ServeHTTP(nil, nil)

	expected := []string{"m1_in", "m2_in", "handler", "m2_out", "m1_out"}
	if strings.Join(order, ",") != strings.Join(expected, ",") {
		t.Errorf("got order %v, want %v", order, expected)
	}
}

func TestRecovery(t *testing.T) {
	h := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		panic("test panic")
	})

	recovered := Recovery()(h)
	req := httptest.NewRequest(http.MethodGet, "/", nil)
	rr := httptest.NewRecorder()

	recovered.ServeHTTP(rr, req)

	if rr.Code != http.StatusInternalServerError {
		t.Errorf("expected 500, got %d", rr.Code)
	}
	if !strings.Contains(rr.Body.String(), "internal server error") {
		t.Error("expected error body")
	}
}

func TestCORS(t *testing.T) {
	h := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	})

	cors := CORS()(h)

	t.Run("GET", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/", nil)
		rr := httptest.NewRecorder()
		cors.ServeHTTP(rr, req)

		if rr.Header().Get("Access-Control-Allow-Origin") != "*" {
			t.Error("missing CORS origin header")
		}
	})

	t.Run("OPTIONS", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodOptions, "/", nil)
		rr := httptest.NewRecorder()
		cors.ServeHTTP(rr, req)

		// With simplified CORS, OPTIONS is just passed through to the handler
		if rr.Header().Get("Access-Control-Allow-Origin") != "*" {
			t.Error("missing CORS origin header on OPTIONS")
		}
	})
}

func TestSecureHeaders(t *testing.T) {
	h := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	})

	sh := SecureHeaders()(h)
	req := httptest.NewRequest(http.MethodGet, "/", nil)
	rr := httptest.NewRecorder()
	sh.ServeHTTP(rr, req)

	tests := []struct {
		header string
		want   string
	}{
		{"Content-Security-Policy", "default-src 'self'"},
		{"Content-Security-Policy", "img-src 'self' data:"},
		{"Content-Security-Policy", "frame-ancestors 'none'"},
		{"X-Content-Type-Options", "nosniff"},
		{"Referrer-Policy", "strict-origin-when-cross-origin"},
	}
	for _, tt := range tests {
		if !strings.Contains(rr.Header().Get(tt.header), tt.want) {
			t.Errorf("%s: want %q in %q", tt.header, tt.want, rr.Header().Get(tt.header))
		}
	}
}

func TestCacheControl(t *testing.T) {
	h := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
	})

	cc := CacheControl(5 * time.Minute)(h)

	t.Run("GET", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/", nil)
		rr := httptest.NewRecorder()
		cc.ServeHTTP(rr, req)

		val := rr.Header().Get("Cache-Control")
		if val != "public, max-age=300" {
			t.Errorf("unexpected Cache-Control: %s", val)
		}
	})

	t.Run("POST", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodPost, "/", nil)
		rr := httptest.NewRecorder()
		cc.ServeHTTP(rr, req)

		if rr.Header().Get("Cache-Control") != "" {
			t.Error("Cache-Control should only be set for GET")
		}
	})
}
