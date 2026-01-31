package packages

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

// mockRepository implements Repository for testing
type mockRepository struct {
	findByNameFunc       func(ctx context.Context, name string, startMonth, endMonth int) (*PackagePopularity, error)
	findAllFunc          func(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*PackagePopularityList, error)
	findSeriesByNameFunc func(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*PackagePopularityList, error)
}

func (m *mockRepository) FindByName(ctx context.Context, name string, startMonth, endMonth int) (*PackagePopularity, error) {
	return m.findByNameFunc(ctx, name, startMonth, endMonth)
}

func (m *mockRepository) FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*PackagePopularityList, error) {
	return m.findAllFunc(ctx, query, startMonth, endMonth, limit, offset)
}

func (m *mockRepository) FindSeriesByName(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*PackagePopularityList, error) {
	return m.findSeriesByNameFunc(ctx, name, startMonth, endMonth, limit, offset)
}

func TestHandleGet(t *testing.T) {
	repo := &mockRepository{
		findByNameFunc: func(_ context.Context, name string, _, _ int) (*PackagePopularity, error) {
			return &PackagePopularity{
				Name:       name,
				Samples:    500,
				Count:      100,
				Popularity: 20,
				StartMonth: 202501,
				EndMonth:   202501,
			}, nil
		},
	}

	handler := NewHandler(repo)
	mux := http.NewServeMux()
	handler.RegisterRoutes(mux)

	req := httptest.NewRequest(http.MethodGet, "/api/packages/pacman", nil)
	rr := httptest.NewRecorder()

	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status %d, got %d", http.StatusOK, rr.Code)
	}

	var pkg PackagePopularity
	if err := json.NewDecoder(rr.Body).Decode(&pkg); err != nil {
		t.Fatalf("decode response: %v", err)
	}

	if pkg.Name != "pacman" {
		t.Errorf("expected name pacman, got %s", pkg.Name)
	}
	if pkg.Count != 100 {
		t.Errorf("expected count 100, got %d", pkg.Count)
	}
}

func TestHandleList(t *testing.T) {
	repo := &mockRepository{
		findAllFunc: func(_ context.Context, query string, _, _, limit, offset int) (*PackagePopularityList, error) {
			return &PackagePopularityList{
				Total: 1,
				Count: 1,
				PackagePopularities: []PackagePopularity{
					{Name: "pacman", Count: 100},
				},
				Limit:  limit,
				Offset: offset,
				Query:  query,
			}, nil
		},
	}

	handler := NewHandler(repo)
	mux := http.NewServeMux()
	handler.RegisterRoutes(mux)

	req := httptest.NewRequest(http.MethodGet, "/api/packages?query=pac&limit=10", nil)
	rr := httptest.NewRecorder()

	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status %d, got %d", http.StatusOK, rr.Code)
	}

	var list PackagePopularityList
	if err := json.NewDecoder(rr.Body).Decode(&list); err != nil {
		t.Fatalf("decode response: %v", err)
	}

	if list.Query != "pac" {
		t.Errorf("expected query pac, got %s", list.Query)
	}
	if list.Limit != 10 {
		t.Errorf("expected limit 10, got %d", list.Limit)
	}
}

func TestCORSHeader(t *testing.T) {
	repo := &mockRepository{
		findAllFunc: func(_ context.Context, _ string, _, _, _, _ int) (*PackagePopularityList, error) {
			return &PackagePopularityList{}, nil
		},
	}

	handler := NewHandler(repo)
	mux := http.NewServeMux()
	handler.RegisterRoutes(mux)

	req := httptest.NewRequest(http.MethodGet, "/api/packages", nil)
	rr := httptest.NewRecorder()

	mux.ServeHTTP(rr, req)

	cors := rr.Header().Get("Access-Control-Allow-Origin")
	if cors != "*" {
		t.Errorf("expected CORS header *, got %s", cors)
	}
}
