package web

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"net/http"
	"net/http/httptest"
	"syscall"
	"testing"
)

func TestErrorResponses(t *testing.T) {
	t.Run("NotFound", func(t *testing.T) {
		rr := httptest.NewRecorder()
		NotFound(rr, "missing item")
		if rr.Code != http.StatusNotFound {
			t.Errorf("got %d", rr.Code)
		}
		var pd ProblemDetails
		_ = json.Unmarshal(rr.Body.Bytes(), &pd)
		if pd.Detail != "missing item" {
			t.Errorf("got detail %q", pd.Detail)
		}
		if cc := rr.Header().Get("Cache-Control"); cc != "no-store" {
			t.Errorf("expected Cache-Control %q, got %q", "no-store", cc)
		}
	})

	t.Run("BadRequest", func(t *testing.T) {
		rr := httptest.NewRecorder()
		BadRequest(rr, "invalid param")
		if rr.Code != http.StatusBadRequest {
			t.Errorf("got %d", rr.Code)
		}
	})

	t.Run("InternalServerError", func(t *testing.T) {
		rr := httptest.NewRecorder()
		InternalServerError(rr, "something went wrong")
		if rr.Code != http.StatusInternalServerError {
			t.Errorf("got %d", rr.Code)
		}
	})

	t.Run("TooManyRequests", func(t *testing.T) {
		rr := httptest.NewRecorder()
		TooManyRequests(rr, "rate limit", 60)
		if rr.Code != http.StatusTooManyRequests {
			t.Errorf("got %d", rr.Code)
		}
		if rr.Header().Get("Retry-After") != "60" {
			t.Errorf("got Retry-After %s", rr.Header().Get("Retry-After"))
		}
	})

	t.Run("ServerError writes 500 for real errors", func(t *testing.T) {
		rr := httptest.NewRecorder()
		ServerError(rr, "db failed", errors.New("connection refused"))
		if rr.Code != http.StatusInternalServerError {
			t.Errorf("got %d, want %d", rr.Code, http.StatusInternalServerError)
		}
		if ct := rr.Header().Get("Content-Type"); ct != "application/problem+json" {
			t.Errorf("got Content-Type %q", ct)
		}
		if cc := rr.Header().Get("Cache-Control"); cc != "no-store" {
			t.Errorf("got Cache-Control %q", cc)
		}
	})

	t.Run("ServerError is no-op for context.Canceled", func(t *testing.T) {
		rr := httptest.NewRecorder()
		ServerError(rr, "should not log", context.Canceled)
		if rr.Code != http.StatusOK {
			t.Errorf("got %d, want %d (no response written)", rr.Code, http.StatusOK)
		}
		if rr.Body.Len() != 0 {
			t.Errorf("expected empty body, got %q", rr.Body.String())
		}
	})

	t.Run("ServerError is no-op for wrapped context.Canceled", func(t *testing.T) {
		rr := httptest.NewRecorder()
		ServerError(rr, "should not log", fmt.Errorf("query: %w", context.Canceled))
		if rr.Code != http.StatusOK {
			t.Errorf("got %d, want %d (no response written)", rr.Code, http.StatusOK)
		}
	})
}

func TestIsClientDisconnect(t *testing.T) {
	tests := []struct {
		name string
		err  error
		want bool
	}{
		{"context.Canceled", context.Canceled, true},
		{"wrapped context.Canceled", fmt.Errorf("scan: %w", context.Canceled), true},
		{"ECONNRESET", syscall.ECONNRESET, true},
		{"wrapped ECONNRESET", fmt.Errorf("write: %w", syscall.ECONNRESET), true},
		{"EPIPE", syscall.EPIPE, true},
		{"wrapped EPIPE", fmt.Errorf("write: %w", syscall.EPIPE), true},
		{"generic error", errors.New("timeout"), false},
		{"nil", nil, false},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := IsClientDisconnect(tt.err); got != tt.want {
				t.Errorf("IsClientDisconnect(%v) = %v, want %v", tt.err, got, tt.want)
			}
		})
	}
}
