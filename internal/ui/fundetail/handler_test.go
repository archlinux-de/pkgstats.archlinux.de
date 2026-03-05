package fundetail

import (
	"context"
	"errors"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"pkgstatsd/internal/packages"
	"pkgstatsd/internal/ui/layout"
)

type mockRepo struct {
	findByNameFunc       func(ctx context.Context, name string, startMonth, endMonth int) (*packages.PackagePopularity, error)
	findSeriesByNameFunc func(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*packages.PackagePopularityList, error)
}

func (m *mockRepo) FindByName(ctx context.Context, name string, startMonth, endMonth int) (*packages.PackagePopularity, error) {
	if m.findByNameFunc != nil {
		return m.findByNameFunc(ctx, name, startMonth, endMonth)
	}
	return &packages.PackagePopularity{Name: name, Popularity: 1.0}, nil
}

func (m *mockRepo) FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*packages.PackagePopularityList, error) {
	return nil, nil
}

func (m *mockRepo) FindSeriesByName(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*packages.PackagePopularityList, error) {
	if m.findSeriesByNameFunc != nil {
		return m.findSeriesByNameFunc(ctx, name, startMonth, endMonth, limit, offset)
	}
	return &packages.PackagePopularityList{
		Total: 1,
		PackagePopularities: []packages.PackagePopularity{
			{Name: name, StartMonth: 202501, EndMonth: 202501, Popularity: 1.0},
		},
	}, nil
}

func TestHandleCurrent_SmallCategory(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	popularity := 10.0
	repo := &mockRepo{
		findByNameFunc: func(_ context.Context, name string, _, _ int) (*packages.PackagePopularity, error) {
			popularity -= 1.0
			return &packages.PackagePopularity{Name: name, Popularity: popularity}, nil
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/fun/Widget+Toolkits/current", nil)
	req.SetPathValue("category", "Widget Toolkits")
	req.SetPathValue("preset", "current")
	rr := httptest.NewRecorder()

	handler.HandleFunDetail(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status 200, got %d", rr.Code)
	}

	body := rr.Body.String()

	// All packages should be in the table (Widget Toolkits has 4 packages, < tableLimit)
	if !strings.Contains(body, "gtk3") || !strings.Contains(body, "qt6-base") {
		t.Error("expected body to contain all package names")
	}

	// Should NOT have an "Others" section
	if strings.Contains(body, "Others") {
		t.Error("expected no Others section for small category")
	}

	// Should have a compare link
	if !strings.Contains(body, "Compare all in detail") {
		t.Error("expected compare link")
	}

	// Compare link should include all packages
	if !strings.Contains(body, "/packages?compare=") {
		t.Error("expected compare URL")
	}
}

func TestHandleCurrent_LargeCategory(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	popularity := 50.0
	repo := &mockRepo{
		findByNameFunc: func(_ context.Context, name string, _, _ int) (*packages.PackagePopularity, error) {
			popularity -= 1.0
			return &packages.PackagePopularity{Name: name, Popularity: popularity}, nil
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/fun/Browsers/current", nil)
	req.SetPathValue("category", "Browsers")
	req.SetPathValue("preset", "current")
	rr := httptest.NewRecorder()

	handler.HandleFunDetail(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status 200, got %d", rr.Code)
	}

	body := rr.Body.String()

	// Should have an "Others" section (Browsers has 23 packages, > tableLimit)
	if !strings.Contains(body, "Others") {
		t.Error("expected Others section for large category")
	}

	// Compare link should include all packages
	if !strings.Contains(body, "Compare all in detail") {
		t.Error("expected compare link")
	}
}

func TestHandleHistory_ChartLimited(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	repo := &mockRepo{
		findSeriesByNameFunc: func(_ context.Context, name string, _, _, _, _ int) (*packages.PackagePopularityList, error) {
			return &packages.PackagePopularityList{
				Total: 1,
				PackagePopularities: []packages.PackagePopularity{
					{Name: name, StartMonth: 202501, EndMonth: 202501, Popularity: 5.0},
				},
			}, nil
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/fun/Browsers/history", nil)
	req.SetPathValue("category", "Browsers")
	req.SetPathValue("preset", "history")
	rr := httptest.NewRecorder()

	handler.HandleFunDetail(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status 200, got %d", rr.Code)
	}

	body := rr.Body.String()

	// Should have the chart
	if !strings.Contains(body, "popularity-chart") {
		t.Error("expected chart element")
	}

	// Should have a compare link with all packages
	if !strings.Contains(body, "Compare all in detail") {
		t.Error("expected compare link")
	}
	if !strings.Contains(body, "/packages?compare=") {
		t.Error("expected compare URL")
	}
}

func TestHandleHistory_CompareURLIncludesAllPackages(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	repo := &mockRepo{
		findSeriesByNameFunc: func(_ context.Context, name string, _, _, _, _ int) (*packages.PackagePopularityList, error) {
			return &packages.PackagePopularityList{
				Total: 1,
				PackagePopularities: []packages.PackagePopularity{
					{Name: name, StartMonth: 202501, EndMonth: 202501, Popularity: 5.0},
				},
			}, nil
		},
	}
	handler := NewHandler(repo, manifest)

	// Use Window Managers (31 packages, well above chartLimit of 10)
	req := httptest.NewRequest(http.MethodGet, "/fun/Window+Managers/history", nil)
	req.SetPathValue("category", "Window Managers")
	req.SetPathValue("preset", "history")
	rr := httptest.NewRecorder()

	handler.HandleFunDetail(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("expected status 200, got %d", rr.Code)
	}

	body := rr.Body.String()

	// The compare URL should contain packages beyond the chart limit (e.g. sway, bspwm)
	// All 31 window managers should be in the compare URL
	for _, name := range []string{"awesome", "sway", "hyprland", "i3-wm"} {
		if !strings.Contains(body, name) {
			t.Errorf("expected compare URL to contain %q", name)
		}
	}
}

func TestHandleFunDetail_InvalidCategory(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	handler := NewHandler(nil, manifest)

	req := httptest.NewRequest(http.MethodGet, "/fun/Nonexistent/current", nil)
	req.SetPathValue("category", "Nonexistent")
	req.SetPathValue("preset", "current")
	rr := httptest.NewRecorder()

	handler.HandleFunDetail(rr, req)

	if rr.Code != http.StatusNotFound {
		t.Errorf("expected status 404, got %d", rr.Code)
	}
}

func TestHandleFunDetail_InvalidPreset(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	handler := NewHandler(nil, manifest)

	req := httptest.NewRequest(http.MethodGet, "/fun/Browsers/invalid", nil)
	req.SetPathValue("category", "Browsers")
	req.SetPathValue("preset", "invalid")
	rr := httptest.NewRecorder()

	handler.HandleFunDetail(rr, req)

	if rr.Code != http.StatusNotFound {
		t.Errorf("expected status 404, got %d", rr.Code)
	}
}

func TestHandleCurrent_FetchError(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	repo := &mockRepo{
		findByNameFunc: func(_ context.Context, _ string, _, _ int) (*packages.PackagePopularity, error) {
			return nil, errors.New("db error")
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/fun/Widget+Toolkits/current", nil)
	req.SetPathValue("category", "Widget Toolkits")
	req.SetPathValue("preset", "current")
	rr := httptest.NewRecorder()

	handler.HandleFunDetail(rr, req)

	if rr.Code != http.StatusInternalServerError {
		t.Errorf("expected status 500, got %d", rr.Code)
	}
}

func TestHandleHistory_FetchError(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	repo := &mockRepo{
		findSeriesByNameFunc: func(_ context.Context, _ string, _, _, _, _ int) (*packages.PackagePopularityList, error) {
			return nil, errors.New("db error")
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/fun/Widget+Toolkits/history", nil)
	req.SetPathValue("category", "Widget Toolkits")
	req.SetPathValue("preset", "history")
	rr := httptest.NewRecorder()

	handler.HandleFunDetail(rr, req)

	if rr.Code != http.StatusInternalServerError {
		t.Errorf("expected status 500, got %d", rr.Code)
	}
}
