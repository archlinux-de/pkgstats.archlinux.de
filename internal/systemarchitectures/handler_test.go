package systemarchitectures

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"pkgstats.archlinux.de/internal/web"
)

type mockQuerier struct {
	findByIdentifierFunc func(ctx context.Context, identifier string, startMonth, endMonth int) (*SystemArchitecturePopularity, error)
	findAllFunc          func(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*SystemArchitecturePopularityList, error)
	findSeriesFunc       func(ctx context.Context, identifier string, startMonth, endMonth, limit, offset int) (*SystemArchitecturePopularityList, error)
}

func (m *mockQuerier) FindByIdentifier(ctx context.Context, identifier string, startMonth, endMonth int) (*SystemArchitecturePopularity, error) {
	return m.findByIdentifierFunc(ctx, identifier, startMonth, endMonth)
}

func (m *mockQuerier) FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*SystemArchitecturePopularityList, error) {
	return m.findAllFunc(ctx, query, startMonth, endMonth, limit, offset)
}

func (m *mockQuerier) FindSeries(ctx context.Context, identifier string, startMonth, endMonth, limit, offset int) (*SystemArchitecturePopularityList, error) {
	return m.findSeriesFunc(ctx, identifier, startMonth, endMonth, limit, offset)
}

func newTestMux(q *mockQuerier) *http.ServeMux {
	handler := newHandlerFromQuerier(q)
	mux := http.NewServeMux()
	handler.RegisterRoutes(mux)
	return mux
}

func TestHandleGet(t *testing.T) {
	q := &mockQuerier{
		findByIdentifierFunc: func(_ context.Context, name string, _, _ int) (*SystemArchitecturePopularity, error) {
			return &SystemArchitecturePopularity{Name: name, Samples: 500, Count: 100, Popularity: 20, StartMonth: 202501, EndMonth: 202501}, nil
		},
	}

	mux := newTestMux(q)
	req := httptest.NewRequest(http.MethodGet, "/api/system-architectures/x86_64", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status %d, got %d", http.StatusOK, rr.Code)
	}

	var raw map[string]any
	if err := json.NewDecoder(rr.Body).Decode(&raw); err != nil {
		t.Fatalf("decode response: %v", err)
	}

	expectedKeys := []string{"name", "samples", "count", "popularity", "startMonth", "endMonth"}
	for _, key := range expectedKeys {
		if _, ok := raw[key]; !ok {
			t.Errorf("missing key %q in response", key)
		}
	}
	if raw["name"] != "x86_64" {
		t.Errorf("expected name x86_64, got %v", raw["name"])
	}
}

func TestHandleGet_RepositoryError(t *testing.T) {
	q := &mockQuerier{
		findByIdentifierFunc: func(_ context.Context, _ string, _, _ int) (*SystemArchitecturePopularity, error) {
			return nil, errors.New("database error")
		},
	}

	mux := newTestMux(q)
	req := httptest.NewRequest(http.MethodGet, "/api/system-architectures/x86_64", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusInternalServerError {
		t.Errorf("expected status %d, got %d", http.StatusInternalServerError, rr.Code)
	}
}

func TestHandleList_ResponseStructure(t *testing.T) {
	q := &mockQuerier{
		findAllFunc: func(_ context.Context, query string, _, _, limit, offset int) (*SystemArchitecturePopularityList, error) {
			return &SystemArchitecturePopularityList{
				SystemArchitecturePopularities: []SystemArchitecturePopularity{},
				Limit:                          limit,
				Offset:                         offset,
				Query:                          &query,
			}, nil
		},
	}

	mux := newTestMux(q)
	req := httptest.NewRequest(http.MethodGet, "/api/system-architectures", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	var raw map[string]any
	if err := json.NewDecoder(rr.Body).Decode(&raw); err != nil {
		t.Fatalf("decode response: %v", err)
	}

	expectedKeys := []string{"total", "count", "systemArchitecturePopularities", "limit", "offset", "query"}
	for _, key := range expectedKeys {
		if _, ok := raw[key]; !ok {
			t.Errorf("missing key %q in response", key)
		}
	}
	if len(raw) != len(expectedKeys) {
		t.Errorf("expected %d keys, got %d: %v", len(expectedKeys), len(raw), raw)
	}
}

func TestHandleList_PaginationValidCases(t *testing.T) {
	tests := []struct {
		name           string
		url            string
		expectedLimit  int
		expectedOffset int
	}{
		{"default", "/api/system-architectures", web.DefaultLimit, 0},
		{"limit=0", "/api/system-architectures?limit=0", web.MaxLimit, 0},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			var capturedLimit, capturedOffset int
			q := &mockQuerier{
				findAllFunc: func(_ context.Context, query string, _, _, limit, offset int) (*SystemArchitecturePopularityList, error) {
					capturedLimit = limit
					capturedOffset = offset
					return &SystemArchitecturePopularityList{
						SystemArchitecturePopularities: []SystemArchitecturePopularity{},
						Limit:                          limit,
						Offset:                         offset,
						Query:                          &query,
					}, nil
				},
			}

			mux := newTestMux(q)
			req := httptest.NewRequest(http.MethodGet, tt.url, nil)
			rr := httptest.NewRecorder()
			mux.ServeHTTP(rr, req)

			if rr.Code != http.StatusOK {
				t.Fatalf("expected status %d, got %d", http.StatusOK, rr.Code)
			}
			if capturedLimit != tt.expectedLimit {
				t.Errorf("expected limit %d, got %d", tt.expectedLimit, capturedLimit)
			}
			if capturedOffset != tt.expectedOffset {
				t.Errorf("expected offset %d, got %d", tt.expectedOffset, capturedOffset)
			}
		})
	}
}

func TestHandleList_PaginationInvalidCases(t *testing.T) {
	tests := []struct {
		name string
		url  string
	}{
		{"limit=max+1", fmt.Sprintf("/api/system-architectures?limit=%d", web.MaxLimit+1)},
		{"limit=-1", "/api/system-architectures?limit=-1"},
		{"offset=-1", "/api/system-architectures?offset=-1"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			q := &mockQuerier{
				findAllFunc: func(_ context.Context, query string, _, _, limit, offset int) (*SystemArchitecturePopularityList, error) {
					return &SystemArchitecturePopularityList{
						SystemArchitecturePopularities: []SystemArchitecturePopularity{},
						Limit:                          limit,
						Offset:                         offset,
						Query:                          &query,
					}, nil
				},
			}

			mux := newTestMux(q)
			req := httptest.NewRequest(http.MethodGet, tt.url, nil)
			rr := httptest.NewRecorder()
			mux.ServeHTTP(rr, req)

			if rr.Code != http.StatusBadRequest {
				t.Fatalf("expected status %d, got %d", http.StatusBadRequest, rr.Code)
			}
		})
	}
}

func currentMonth() int {
	now := time.Now()
	return now.Year()*100 + int(now.Month())
}

func TestHandleList_MonthZeroMeansCurrentMonth(t *testing.T) {
	var capturedStart, capturedEnd int
	q := &mockQuerier{
		findAllFunc: func(_ context.Context, query string, startMonth, endMonth, limit, offset int) (*SystemArchitecturePopularityList, error) {
			capturedStart = startMonth
			capturedEnd = endMonth
			return &SystemArchitecturePopularityList{
				SystemArchitecturePopularities: []SystemArchitecturePopularity{},
				Limit:                          limit,
				Offset:                         offset,
				Query:                          &query,
			}, nil
		},
	}

	mux := newTestMux(q)
	req := httptest.NewRequest(http.MethodGet, "/api/system-architectures?startMonth=0&endMonth=0", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	cm := currentMonth()
	if rr.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
	if capturedStart != 0 {
		t.Errorf("expected startMonth 0, got %d", capturedStart)
	}
	if capturedEnd != cm {
		t.Errorf("expected endMonth %d (current month), got %d", cm, capturedEnd)
	}
}

func TestHandleSeries_MonthZeroMeansCurrentMonth(t *testing.T) {
	var capturedStart, capturedEnd int
	q := &mockQuerier{
		findSeriesFunc: func(_ context.Context, _ string, startMonth, endMonth, _, _ int) (*SystemArchitecturePopularityList, error) {
			capturedStart = startMonth
			capturedEnd = endMonth
			return &SystemArchitecturePopularityList{
				SystemArchitecturePopularities: []SystemArchitecturePopularity{},
			}, nil
		},
	}

	mux := newTestMux(q)
	req := httptest.NewRequest(http.MethodGet, "/api/system-architectures/x86_64/series?startMonth=0&endMonth=0&limit=0", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	cm := currentMonth()
	if rr.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
	if capturedStart != 0 {
		t.Errorf("expected startMonth 0, got %d", capturedStart)
	}
	if capturedEnd != cm {
		t.Errorf("expected endMonth %d (current month), got %d", cm, capturedEnd)
	}
}

func TestHandleSeries(t *testing.T) {
	q := &mockQuerier{
		findSeriesFunc: func(_ context.Context, name string, _, _, limit, _ int) (*SystemArchitecturePopularityList, error) {
			return &SystemArchitecturePopularityList{
				Total:                          1,
				Count:                          1,
				SystemArchitecturePopularities: []SystemArchitecturePopularity{{Name: name, StartMonth: 202501, EndMonth: 202501}},
				Limit:                          limit,
			}, nil
		},
	}

	mux := newTestMux(q)
	req := httptest.NewRequest(http.MethodGet, "/api/system-architectures/x86_64/series", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
}

func TestCORSHeader(t *testing.T) {
	q := &mockQuerier{
		findAllFunc: func(_ context.Context, query string, _, _, limit, offset int) (*SystemArchitecturePopularityList, error) {
			return &SystemArchitecturePopularityList{
				SystemArchitecturePopularities: []SystemArchitecturePopularity{},
				Limit:                          limit,
				Offset:                         offset,
				Query:                          &query,
			}, nil
		},
	}

	mux := newTestMux(q)
	req := httptest.NewRequest(http.MethodGet, "/api/system-architectures", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if cors := rr.Header().Get("Access-Control-Allow-Origin"); cors != "*" {
		t.Errorf("expected CORS header *, got %s", cors)
	}
}
