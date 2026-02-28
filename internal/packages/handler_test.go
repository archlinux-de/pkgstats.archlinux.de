package packages

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

func currentMonth() int {
	now := time.Now()
	return now.Year()*100 + int(now.Month())
}

func newTestMux(repo Repository) *http.ServeMux {
	handler := NewHandler(repo)
	mux := http.NewServeMux()
	handler.RegisterRoutes(mux)
	return mux
}

// captureRepo returns a mockRepository that captures the parameters passed to FindAll
func captureListRepo() (*mockRepository, *capturedListParams) {
	captured := &capturedListParams{}
	repo := &mockRepository{
		findAllFunc: func(_ context.Context, query string, startMonth, endMonth, limit, offset int) (*PackagePopularityList, error) {
			captured.query = query
			captured.startMonth = startMonth
			captured.endMonth = endMonth
			captured.limit = limit
			captured.offset = offset
			return &PackagePopularityList{
				PackagePopularities: []PackagePopularity{},
				Limit:               limit,
				Offset:              offset,
				Query:               &query,
			}, nil
		},
	}
	return repo, captured
}

type capturedListParams struct {
	query      string
	startMonth int
	endMonth   int
	limit      int
	offset     int
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

	mux := newTestMux(repo)
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

func TestHandleGet_ResponseStructure(t *testing.T) {
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

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages/pacman", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	// Verify JSON has exact expected keys (matching PHP response shape)
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
	if len(raw) != len(expectedKeys) {
		t.Errorf("expected %d keys, got %d: %v", len(expectedKeys), len(raw), raw)
	}
}

func TestHandleGet_RepositoryError(t *testing.T) {
	repo := &mockRepository{
		findByNameFunc: func(_ context.Context, _ string, _, _ int) (*PackagePopularity, error) {
			return nil, errors.New("database error")
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages/pacman", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusInternalServerError {
		t.Errorf("expected status %d, got %d", http.StatusInternalServerError, rr.Code)
	}
}

func TestHandleList(t *testing.T) {
	const testQuery = "pac"

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
				Query:  &query,
			}, nil
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages?query="+testQuery+"&limit=10", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status %d, got %d", http.StatusOK, rr.Code)
	}

	var list PackagePopularityList
	if err := json.NewDecoder(rr.Body).Decode(&list); err != nil {
		t.Fatalf("decode response: %v", err)
	}

	if *list.Query != testQuery {
		t.Errorf("expected query %s, got %s", testQuery, *list.Query)
	}
	if list.Limit != 10 {
		t.Errorf("expected limit 10, got %d", list.Limit)
	}
}

func TestHandleList_ResponseStructure(t *testing.T) {
	repo, _ := captureListRepo()

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	// Verify JSON has exact expected keys (matching PHP response shape)
	var raw map[string]any
	if err := json.NewDecoder(rr.Body).Decode(&raw); err != nil {
		t.Fatalf("decode response: %v", err)
	}

	expectedKeys := []string{"total", "count", "packagePopularities", "limit", "offset", "query"}
	for _, key := range expectedKeys {
		if _, ok := raw[key]; !ok {
			t.Errorf("missing key %q in response", key)
		}
	}
	if len(raw) != len(expectedKeys) {
		t.Errorf("expected %d keys, got %d: %v", len(expectedKeys), len(raw), raw)
	}
}

func TestHandleList_DefaultPagination(t *testing.T) {
	repo, captured := captureListRepo()

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
	if captured.limit != web.DefaultLimit {
		t.Errorf("expected default limit %d, got %d", web.DefaultLimit, captured.limit)
	}
	if captured.offset != 0 {
		t.Errorf("expected default offset 0, got %d", captured.offset)
	}
}

func TestHandleList_LimitZeroMeansMaxLimit(t *testing.T) {
	repo, captured := captureListRepo()

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages?limit=0", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
	if captured.limit != web.MaxLimit {
		t.Errorf("limit=0 should resolve to maxLimit %d, got %d", web.MaxLimit, captured.limit)
	}
}

func TestHandleList_LimitExceedsMax(t *testing.T) {
	repo, captured := captureListRepo()

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages?limit=99999", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
	if captured.limit != web.MaxLimit {
		t.Errorf("limit > max should be capped to %d, got %d", web.MaxLimit, captured.limit)
	}
}

func TestHandleList_LimitNegative(t *testing.T) {
	repo, captured := captureListRepo()

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages?limit=-5", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
	if captured.limit != 1 {
		t.Errorf("negative limit should resolve to 1, got %d", captured.limit)
	}
}

func TestHandleList_OffsetNegative(t *testing.T) {
	repo, captured := captureListRepo()

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages?offset=-10", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
	if captured.offset != 0 {
		t.Errorf("negative offset should resolve to 0, got %d", captured.offset)
	}
}

func TestHandleList_InvalidLimitFallsToDefault(t *testing.T) {
	repo, captured := captureListRepo()

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages?limit=abc", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
	if captured.limit != web.DefaultLimit {
		t.Errorf("invalid limit should fall back to default %d, got %d", web.DefaultLimit, captured.limit)
	}
}

func TestHandleList_MonthRangeDefaults(t *testing.T) {
	repo, captured := captureListRepo()
	expected := currentMonth()

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
	if captured.startMonth != expected {
		t.Errorf("expected default startMonth %d, got %d", expected, captured.startMonth)
	}
	if captured.endMonth != expected {
		t.Errorf("expected default endMonth %d, got %d", expected, captured.endMonth)
	}
}

func TestHandleList_MonthZeroMeansNoFilter(t *testing.T) {
	repo, captured := captureListRepo()

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages?startMonth=0&endMonth=0", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
	// startMonth=0 means "from the beginning" (0 is lower than any real month)
	if captured.startMonth != 0 {
		t.Errorf("expected startMonth 0, got %d", captured.startMonth)
	}
	// endMonth=0 means "no upper bound" and should be mapped to 999912
	if captured.endMonth != 999912 {
		t.Errorf("expected endMonth 999912 (no upper bound), got %d", captured.endMonth)
	}
}

func TestHandleGet_MonthZeroMeansNoFilter(t *testing.T) {
	var capturedStart, capturedEnd int
	repo := &mockRepository{
		findByNameFunc: func(_ context.Context, name string, startMonth, endMonth int) (*PackagePopularity, error) {
			capturedStart = startMonth
			capturedEnd = endMonth
			return &PackagePopularity{
				Name:       name,
				Samples:    500,
				Count:      100,
				Popularity: 20,
				StartMonth: startMonth,
				EndMonth:   endMonth,
			}, nil
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages/pacman?startMonth=0&endMonth=0", nil)
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
		findSeriesByNameFunc: func(_ context.Context, _ string, startMonth, endMonth, _, _ int) (*PackagePopularityList, error) {
			capturedStart = startMonth
			capturedEnd = endMonth
			return &PackagePopularityList{PackagePopularities: []PackagePopularity{}}, nil
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages/pacman/series?startMonth=0&endMonth=0&limit=0", nil)
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

func TestHandleList_MonthRangeSwap(t *testing.T) {
	repo, captured := captureListRepo()

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages?startMonth=202512&endMonth=202501", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
	if captured.startMonth != 202501 {
		t.Errorf("expected swapped startMonth 202501, got %d", captured.startMonth)
	}
	if captured.endMonth != 202512 {
		t.Errorf("expected swapped endMonth 202512, got %d", captured.endMonth)
	}
}

func TestHandleList_MonthRangeExplicit(t *testing.T) {
	repo, captured := captureListRepo()

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages?startMonth=202401&endMonth=202412", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
	if captured.startMonth != 202401 {
		t.Errorf("expected startMonth 202401, got %d", captured.startMonth)
	}
	if captured.endMonth != 202412 {
		t.Errorf("expected endMonth 202412, got %d", captured.endMonth)
	}
}

func TestHandleList_EmptyQuery(t *testing.T) {
	repo, captured := captureListRepo()

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages?query=", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
	if captured.query != "" {
		t.Errorf("expected empty query, got %q", captured.query)
	}
}

func TestHandleList_RepositoryError(t *testing.T) {
	repo := &mockRepository{
		findAllFunc: func(_ context.Context, _ string, _, _, _, _ int) (*PackagePopularityList, error) {
			return nil, errors.New("database error")
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusInternalServerError {
		t.Errorf("expected status %d, got %d", http.StatusInternalServerError, rr.Code)
	}
}

func TestHandleSeries(t *testing.T) {
	repo := &mockRepository{
		findSeriesByNameFunc: func(_ context.Context, name string, _, _, limit, _ int) (*PackagePopularityList, error) {
			return &PackagePopularityList{
				Total: 3,
				Count: 3,
				PackagePopularities: []PackagePopularity{
					{Name: name, StartMonth: 202501, EndMonth: 202501},
					{Name: name, StartMonth: 202502, EndMonth: 202502},
					{Name: name, StartMonth: 202503, EndMonth: 202503},
				},
				Limit:  limit,
				Offset: 0,
			}, nil
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages/pacman/series?startMonth=202501&endMonth=202503", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status %d, got %d", http.StatusOK, rr.Code)
	}

	var list PackagePopularityList
	if err := json.NewDecoder(rr.Body).Decode(&list); err != nil {
		t.Fatalf("decode response: %v", err)
	}
	if list.Total != 3 {
		t.Errorf("expected total 3, got %d", list.Total)
	}
	if list.Query != nil {
		t.Errorf("expected nil query for series, got %v", list.Query)
	}
}

func TestHandleSeries_LimitZeroMeansMaxLimit(t *testing.T) {
	var capturedLimit int
	repo := &mockRepository{
		findSeriesByNameFunc: func(_ context.Context, _ string, _, _, limit, _ int) (*PackagePopularityList, error) {
			capturedLimit = limit
			return &PackagePopularityList{PackagePopularities: []PackagePopularity{}}, nil
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages/pacman/series?limit=0", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status %d, got %d", http.StatusOK, rr.Code)
	}
	if capturedLimit != web.MaxLimit {
		t.Errorf("limit=0 on series should resolve to maxLimit %d, got %d", web.MaxLimit, capturedLimit)
	}
}

func TestHandleSeries_RepositoryError(t *testing.T) {
	repo := &mockRepository{
		findSeriesByNameFunc: func(_ context.Context, _ string, _, _, _, _ int) (*PackagePopularityList, error) {
			return nil, errors.New("database error")
		},
	}

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages/pacman/series", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	if rr.Code != http.StatusInternalServerError {
		t.Errorf("expected status %d, got %d", http.StatusInternalServerError, rr.Code)
	}
}

func TestHandleList_ContentType(t *testing.T) {
	repo, _ := captureListRepo()

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	ct := rr.Header().Get("Content-Type")
	if ct != "application/json" {
		t.Errorf("expected Content-Type application/json, got %s", ct)
	}
}

func TestCORSHeader(t *testing.T) {
	repo, _ := captureListRepo()

	mux := newTestMux(repo)
	req := httptest.NewRequest(http.MethodGet, "/api/packages", nil)
	rr := httptest.NewRecorder()
	mux.ServeHTTP(rr, req)

	cors := rr.Header().Get("Access-Control-Allow-Origin")
	if cors != "*" {
		t.Errorf("expected CORS header *, got %s", cors)
	}
}

func TestHandleList_PaginationEdgeCases(t *testing.T) {
	tests := []struct {
		name           string
		url            string
		expectedLimit  int
		expectedOffset int
	}{
		{"default", "/api/packages", web.DefaultLimit, 0},
		{"limit=0", "/api/packages?limit=0", web.MaxLimit, 0},
		{"limit=1", "/api/packages?limit=1", 1, 0},
		{"limit=max", fmt.Sprintf("/api/packages?limit=%d", web.MaxLimit), web.MaxLimit, 0},
		{"limit=max+1", fmt.Sprintf("/api/packages?limit=%d", web.MaxLimit+1), web.MaxLimit, 0},
		{"limit=-1", "/api/packages?limit=-1", 1, 0},
		{"limit=invalid", "/api/packages?limit=abc", web.DefaultLimit, 0},
		{"offset=0", "/api/packages?offset=0", web.DefaultLimit, 0},
		{"offset=100", "/api/packages?offset=100", web.DefaultLimit, 100},
		{"offset=-1", "/api/packages?offset=-1", web.DefaultLimit, 0},
		{"offset=invalid", "/api/packages?offset=abc", web.DefaultLimit, 0},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			repo, captured := captureListRepo()

			mux := newTestMux(repo)
			req := httptest.NewRequest(http.MethodGet, tt.url, nil)
			rr := httptest.NewRecorder()
			mux.ServeHTTP(rr, req)

			if rr.Code != http.StatusOK {
				t.Fatalf("expected status %d, got %d", http.StatusOK, rr.Code)
			}
			if captured.limit != tt.expectedLimit {
				t.Errorf("expected limit %d, got %d", tt.expectedLimit, captured.limit)
			}
			if captured.offset != tt.expectedOffset {
				t.Errorf("expected offset %d, got %d", tt.expectedOffset, captured.offset)
			}
		})
	}
}
