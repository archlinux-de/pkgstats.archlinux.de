package systemarchitectures

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"net/http"
	"net/http/httptest"
	"testing"
)

type mockRepository struct {
	findByNameFunc       func(ctx context.Context, name string, startMonth, endMonth int) (*SystemArchitecturePopularity, error)
	findAllFunc          func(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*SystemArchitecturePopularityList, error)
	findSeriesByNameFunc func(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*SystemArchitecturePopularityList, error)
}

func (m *mockRepository) FindByName(ctx context.Context, name string, startMonth, endMonth int) (*SystemArchitecturePopularity, error) {
	return m.findByNameFunc(ctx, name, startMonth, endMonth)
}

func (m *mockRepository) FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*SystemArchitecturePopularityList, error) {
	return m.findAllFunc(ctx, query, startMonth, endMonth, limit, offset)
}

func (m *mockRepository) FindSeriesByName(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*SystemArchitecturePopularityList, error) {
	return m.findSeriesByNameFunc(ctx, name, startMonth, endMonth, limit, offset)
}

func newTestMux(repo Repository) *http.ServeMux {
	handler := NewHandler(repo)
	mux := http.NewServeMux()
	handler.RegisterRoutes(mux)
	return mux
}

func TestHandleGet(t *testing.T) {
	repo := &mockRepository{
		findByNameFunc: func(_ context.Context, name string, _, _ int) (*SystemArchitecturePopularity, error) {
			return &SystemArchitecturePopularity{Name: name, Samples: 500, Count: 100, Popularity: 20, StartMonth: 202501, EndMonth: 202501}, nil
		},
	}

	mux := newTestMux(repo)
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
	repo := &mockRepository{
		findByNameFunc: func(_ context.Context, _ string, _, _ int) (*SystemArchitecturePopularity, error) {
			return nil, errors.New("database error")
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/system-architectures/x86_64", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusInternalServerError {
		t.Errorf("expected status %d, got %d", http.StatusInternalServerError, rr.Code)
	}
}

func TestHandleList_ResponseStructure(t *testing.T) {
	repo := &mockRepository{
		findAllFunc: func(_ context.Context, query string, _, _, limit, offset int) (*SystemArchitecturePopularityList, error) {
			return &SystemArchitecturePopularityList{
				SystemArchitecturePopularities: []SystemArchitecturePopularity{},
				Limit:                          limit,
				Offset:                         offset,
				Query:                          &query,
			}, nil
		},
	}

	mux := newTestMux(repo)
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

func TestHandleList_PaginationEdgeCases(t *testing.T) {
	tests := []struct {
		name           string
		url            string
		expectedLimit  int
		expectedOffset int
	}{
		{"default", "/api/system-architectures", defaultLimit, 0},
		{"limit=0", "/api/system-architectures?limit=0", maxLimit, 0},
		{"limit=max+1", fmt.Sprintf("/api/system-architectures?limit=%d", maxLimit+1), maxLimit, 0},
		{"limit=-1", "/api/system-architectures?limit=-1", 1, 0},
		{"offset=-1", "/api/system-architectures?offset=-1", defaultLimit, 0},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			var capturedLimit, capturedOffset int
			repo := &mockRepository{
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

			mux := newTestMux(repo)
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

func TestHandleList_MonthZeroMeansNoFilter(t *testing.T) {
	var capturedStart, capturedEnd int
	repo := &mockRepository{
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

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/system-architectures?startMonth=0&endMonth=0", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
	if capturedStart != 0 {
		t.Errorf("expected startMonth 0, got %d", capturedStart)
	}
	if capturedEnd != 999912 {
		t.Errorf("expected endMonth 999912 (no upper bound), got %d", capturedEnd)
	}
}

func TestHandleSeries_MonthZeroMeansNoFilter(t *testing.T) {
	var capturedStart, capturedEnd int
	repo := &mockRepository{
		findSeriesByNameFunc: func(_ context.Context, _ string, startMonth, endMonth, limit, _ int) (*SystemArchitecturePopularityList, error) {
			capturedStart = startMonth
			capturedEnd = endMonth
			return &SystemArchitecturePopularityList{
				SystemArchitecturePopularities: []SystemArchitecturePopularity{},
				Limit:                          limit,
			}, nil
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/system-architectures/x86_64/series?startMonth=0&endMonth=0&limit=0", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
	if capturedStart != 0 {
		t.Errorf("expected startMonth 0, got %d", capturedStart)
	}
	if capturedEnd != 999912 {
		t.Errorf("expected endMonth 999912 (no upper bound), got %d", capturedEnd)
	}
}

func TestHandleSeries(t *testing.T) {
	repo := &mockRepository{
		findSeriesByNameFunc: func(_ context.Context, name string, _, _, limit, _ int) (*SystemArchitecturePopularityList, error) {
			return &SystemArchitecturePopularityList{
				Total:                          1,
				Count:                          1,
				SystemArchitecturePopularities: []SystemArchitecturePopularity{{Name: name, StartMonth: 202501, EndMonth: 202501}},
				Limit:                          limit,
			}, nil
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/system-architectures/x86_64/series", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
}

func TestCORSHeader(t *testing.T) {
	repo := &mockRepository{
		findAllFunc: func(_ context.Context, query string, _, _, limit, offset int) (*SystemArchitecturePopularityList, error) {
			return &SystemArchitecturePopularityList{
				SystemArchitecturePopularities: []SystemArchitecturePopularity{},
				Limit:                          limit,
				Offset:                         offset,
				Query:                          &query,
			}, nil
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/system-architectures", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if cors := rr.Header().Get("Access-Control-Allow-Origin"); cors != "*" {
		t.Errorf("expected CORS header *, got %s", cors)
	}
}
