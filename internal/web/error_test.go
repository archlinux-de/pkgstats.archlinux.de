package web

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
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
}
