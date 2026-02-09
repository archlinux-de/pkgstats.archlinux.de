package countries

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
	findByCodeFunc       func(ctx context.Context, code string, startMonth, endMonth int) (*CountryPopularity, error)
	findAllFunc          func(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*CountryPopularityList, error)
	findSeriesByCodeFunc func(ctx context.Context, code string, startMonth, endMonth, limit, offset int) (*CountryPopularityList, error)
}

func (m *mockRepository) FindByCode(ctx context.Context, code string, startMonth, endMonth int) (*CountryPopularity, error) {
	return m.findByCodeFunc(ctx, code, startMonth, endMonth)
}

func (m *mockRepository) FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*CountryPopularityList, error) {
	return m.findAllFunc(ctx, query, startMonth, endMonth, limit, offset)
}

func (m *mockRepository) FindSeriesByCode(ctx context.Context, code string, startMonth, endMonth, limit, offset int) (*CountryPopularityList, error) {
	return m.findSeriesByCodeFunc(ctx, code, startMonth, endMonth, limit, offset)
}

func newTestMux(repo Repository) *http.ServeMux {
	handler := NewHandler(repo)
	mux := http.NewServeMux()
	handler.RegisterRoutes(mux)
	return mux
}

func TestHandleGet(t *testing.T) {
	repo := &mockRepository{
		findByCodeFunc: func(_ context.Context, code string, _, _ int) (*CountryPopularity, error) {
			return &CountryPopularity{Code: code, Samples: 500, Count: 100, Popularity: 20, StartMonth: 202501, EndMonth: 202501}, nil
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/countries/DE", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status %d, got %d", http.StatusOK, rr.Code)
	}

	var raw map[string]any
	if err := json.NewDecoder(rr.Body).Decode(&raw); err != nil {
		t.Fatalf("decode response: %v", err)
	}

	// Verify response structure matches PHP
	expectedKeys := []string{"code", "samples", "count", "popularity", "startMonth", "endMonth"}
	for _, key := range expectedKeys {
		if _, ok := raw[key]; !ok {
			t.Errorf("missing key %q in response", key)
		}
	}
	if raw["code"] != "DE" {
		t.Errorf("expected code DE, got %v", raw["code"])
	}
}

func TestHandleGet_RepositoryError(t *testing.T) {
	repo := &mockRepository{
		findByCodeFunc: func(_ context.Context, _ string, _, _ int) (*CountryPopularity, error) {
			return nil, errors.New("database error")
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/countries/DE", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusInternalServerError {
		t.Errorf("expected status %d, got %d", http.StatusInternalServerError, rr.Code)
	}
}

func TestHandleList_ResponseStructure(t *testing.T) {
	repo := &mockRepository{
		findAllFunc: func(_ context.Context, query string, _, _, limit, offset int) (*CountryPopularityList, error) {
			return &CountryPopularityList{
				CountryPopularities: []CountryPopularity{},
				Limit:               limit,
				Offset:              offset,
				Query:               &query,
			}, nil
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/countries", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	var raw map[string]any
	if err := json.NewDecoder(rr.Body).Decode(&raw); err != nil {
		t.Fatalf("decode response: %v", err)
	}

	expectedKeys := []string{"total", "count", "countryPopularities", "limit", "offset", "query"}
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
		{"default", "/api/countries", defaultLimit, 0},
		{"limit=0", "/api/countries?limit=0", maxLimit, 0},
		{"limit=max+1", fmt.Sprintf("/api/countries?limit=%d", maxLimit+1), maxLimit, 0},
		{"limit=-1", "/api/countries?limit=-1", 1, 0},
		{"offset=-1", "/api/countries?offset=-1", defaultLimit, 0},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			var capturedLimit, capturedOffset int
			repo := &mockRepository{
				findAllFunc: func(_ context.Context, query string, _, _, limit, offset int) (*CountryPopularityList, error) {
					capturedLimit = limit
					capturedOffset = offset
					return &CountryPopularityList{CountryPopularities: []CountryPopularity{}, Limit: limit, Offset: offset, Query: &query}, nil
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

func TestHandleSeries(t *testing.T) {
	repo := &mockRepository{
		findSeriesByCodeFunc: func(_ context.Context, code string, _, _, limit, _ int) (*CountryPopularityList, error) {
			return &CountryPopularityList{
				Total:               1,
				Count:               1,
				CountryPopularities: []CountryPopularity{{Code: code, StartMonth: 202501, EndMonth: 202501}},
				Limit:               limit,
			}, nil
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/countries/DE/series?startMonth=202501&endMonth=202501", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status %d, got %d", http.StatusOK, rr.Code)
	}

	var list CountryPopularityList
	if err := json.NewDecoder(rr.Body).Decode(&list); err != nil {
		t.Fatalf("decode response: %v", err)
	}
	if list.Total != 1 {
		t.Errorf("expected total 1, got %d", list.Total)
	}
}

func TestHandleList_MonthRangeSwap(t *testing.T) {
	var capturedStart, capturedEnd int
	repo := &mockRepository{
		findAllFunc: func(_ context.Context, query string, startMonth, endMonth, limit, offset int) (*CountryPopularityList, error) {
			capturedStart = startMonth
			capturedEnd = endMonth
			return &CountryPopularityList{CountryPopularities: []CountryPopularity{}, Limit: limit, Offset: offset, Query: &query}, nil
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/countries?startMonth=202512&endMonth=202501", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if capturedStart != 202501 {
		t.Errorf("expected swapped startMonth 202501, got %d", capturedStart)
	}
	if capturedEnd != 202512 {
		t.Errorf("expected swapped endMonth 202512, got %d", capturedEnd)
	}
}

func TestCORSHeader(t *testing.T) {
	repo := &mockRepository{
		findAllFunc: func(_ context.Context, query string, _, _, limit, offset int) (*CountryPopularityList, error) {
			return &CountryPopularityList{CountryPopularities: []CountryPopularity{}, Limit: limit, Offset: offset, Query: &query}, nil
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/countries", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if cors := rr.Header().Get("Access-Control-Allow-Origin"); cors != "*" {
		t.Errorf("expected CORS header *, got %s", cors)
	}
}
