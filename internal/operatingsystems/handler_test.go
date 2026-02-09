package operatingsystems

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
	findByIDFunc       func(ctx context.Context, id string, startMonth, endMonth int) (*OperatingSystemIdPopularity, error)
	findAllFunc        func(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*OperatingSystemIdPopularityList, error)
	findSeriesByIDFunc func(ctx context.Context, id string, startMonth, endMonth, limit, offset int) (*OperatingSystemIdPopularityList, error)
}

func (m *mockRepository) FindByID(ctx context.Context, id string, startMonth, endMonth int) (*OperatingSystemIdPopularity, error) {
	return m.findByIDFunc(ctx, id, startMonth, endMonth)
}

func (m *mockRepository) FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*OperatingSystemIdPopularityList, error) {
	return m.findAllFunc(ctx, query, startMonth, endMonth, limit, offset)
}

func (m *mockRepository) FindSeriesByID(ctx context.Context, id string, startMonth, endMonth, limit, offset int) (*OperatingSystemIdPopularityList, error) {
	return m.findSeriesByIDFunc(ctx, id, startMonth, endMonth, limit, offset)
}

func newTestMux(repo Repository) *http.ServeMux {
	handler := NewHandler(repo)
	mux := http.NewServeMux()
	handler.RegisterRoutes(mux)
	return mux
}

func TestHandleGet(t *testing.T) {
	repo := &mockRepository{
		findByIDFunc: func(_ context.Context, id string, _, _ int) (*OperatingSystemIdPopularity, error) {
			return &OperatingSystemIdPopularity{ID: id, Samples: 500, Count: 100, Popularity: 20, StartMonth: 202501, EndMonth: 202501}, nil
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/operating-systems/arch", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status %d, got %d", http.StatusOK, rr.Code)
	}

	var raw map[string]any
	if err := json.NewDecoder(rr.Body).Decode(&raw); err != nil {
		t.Fatalf("decode response: %v", err)
	}

	expectedKeys := []string{"id", "samples", "count", "popularity", "startMonth", "endMonth"}
	for _, key := range expectedKeys {
		if _, ok := raw[key]; !ok {
			t.Errorf("missing key %q in response", key)
		}
	}
	if raw["id"] != "arch" {
		t.Errorf("expected id arch, got %v", raw["id"])
	}
}

func TestHandleGet_RepositoryError(t *testing.T) {
	repo := &mockRepository{
		findByIDFunc: func(_ context.Context, _ string, _, _ int) (*OperatingSystemIdPopularity, error) {
			return nil, errors.New("database error")
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/operating-systems/arch", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusInternalServerError {
		t.Errorf("expected status %d, got %d", http.StatusInternalServerError, rr.Code)
	}
}

func TestHandleList_ResponseStructure(t *testing.T) {
	repo := &mockRepository{
		findAllFunc: func(_ context.Context, query string, _, _, limit, offset int) (*OperatingSystemIdPopularityList, error) {
			return &OperatingSystemIdPopularityList{
				OperatingSystemIdPopularities: []OperatingSystemIdPopularity{},
				Limit:                         limit,
				Offset:                        offset,
				Query:                         &query,
			}, nil
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/operating-systems", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	var raw map[string]any
	if err := json.NewDecoder(rr.Body).Decode(&raw); err != nil {
		t.Fatalf("decode response: %v", err)
	}

	expectedKeys := []string{"total", "count", "operatingSystemIdPopularities", "limit", "offset", "query"}
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
		{"default", "/api/operating-systems", defaultLimit, 0},
		{"limit=0", "/api/operating-systems?limit=0", maxLimit, 0},
		{"limit=max+1", fmt.Sprintf("/api/operating-systems?limit=%d", maxLimit+1), maxLimit, 0},
		{"limit=-1", "/api/operating-systems?limit=-1", 1, 0},
		{"offset=-1", "/api/operating-systems?offset=-1", defaultLimit, 0},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			var capturedLimit, capturedOffset int
			repo := &mockRepository{
				findAllFunc: func(_ context.Context, query string, _, _, limit, offset int) (*OperatingSystemIdPopularityList, error) {
					capturedLimit = limit
					capturedOffset = offset
					return &OperatingSystemIdPopularityList{
						OperatingSystemIdPopularities: []OperatingSystemIdPopularity{},
						Limit:                         limit,
						Offset:                        offset,
						Query:                         &query,
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

func TestHandleSeries(t *testing.T) {
	repo := &mockRepository{
		findSeriesByIDFunc: func(_ context.Context, id string, _, _, limit, _ int) (*OperatingSystemIdPopularityList, error) {
			return &OperatingSystemIdPopularityList{
				Total:                         1,
				Count:                         1,
				OperatingSystemIdPopularities: []OperatingSystemIdPopularity{{ID: id, StartMonth: 202501, EndMonth: 202501}},
				Limit:                         limit,
			}, nil
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/operating-systems/arch/series", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
}

func TestCORSHeader(t *testing.T) {
	repo := &mockRepository{
		findAllFunc: func(_ context.Context, query string, _, _, limit, offset int) (*OperatingSystemIdPopularityList, error) {
			return &OperatingSystemIdPopularityList{
				OperatingSystemIdPopularities: []OperatingSystemIdPopularity{},
				Limit:                         limit,
				Offset:                        offset,
				Query:                         &query,
			}, nil
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/operating-systems", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if cors := rr.Header().Get("Access-Control-Allow-Origin"); cors != "*" {
		t.Errorf("expected CORS header *, got %s", cors)
	}
}
