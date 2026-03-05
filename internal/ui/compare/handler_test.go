package compare

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
	findSeriesByNameFunc func(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*packages.PackagePopularityList, error)
}

func (m *mockRepo) FindByName(ctx context.Context, name string, startMonth, endMonth int) (*packages.PackagePopularity, error) {
	return nil, nil
}

func (m *mockRepo) FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*packages.PackagePopularityList, error) {
	return nil, nil
}

func (m *mockRepo) FindSeriesByName(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*packages.PackagePopularityList, error) {
	return m.findSeriesByNameFunc(ctx, name, startMonth, endMonth, limit, offset)
}

func TestHandleCompare(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	repo := &mockRepo{
		findSeriesByNameFunc: func(ctx context.Context, name string, _, _, _, _ int) (*packages.PackagePopularityList, error) {
			return &packages.PackagePopularityList{
				Total: 1,
				PackagePopularities: []packages.PackagePopularity{
					{Name: name, StartMonth: 202501, EndMonth: 202501, Popularity: 10.5},
				},
			}, nil
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/compare/packages/pacman,glibc", nil)
	req.SetPathValue("names", "pacman,glibc")
	rr := httptest.NewRecorder()

	handler.HandleCompare(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status 200, got %d", rr.Code)
	}

	body := rr.Body.String()
	if !strings.Contains(body, "pacman") || !strings.Contains(body, "glibc") {
		t.Error("expected body to contain package names")
	}
}

func TestHandleCompare_SeriesError(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	repo := &mockRepo{
		findSeriesByNameFunc: func(_ context.Context, _ string, _, _, _, _ int) (*packages.PackagePopularityList, error) {
			return nil, errors.New("db error")
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/compare/packages/pacman,glibc", nil)
	req.SetPathValue("names", "pacman,glibc")
	rr := httptest.NewRecorder()

	handler.HandleCompare(rr, req)

	if rr.Code != http.StatusInternalServerError {
		t.Errorf("expected status 500, got %d", rr.Code)
	}
}

func TestHandleCompare_ExceedsLimit(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	repo := &mockRepo{
		findSeriesByNameFunc: func(ctx context.Context, name string, _, _, _, _ int) (*packages.PackagePopularityList, error) {
			return &packages.PackagePopularityList{
				Total: 1,
				PackagePopularities: []packages.PackagePopularity{
					{Name: name, StartMonth: 202501, EndMonth: 202501, Popularity: 10.5},
				},
			}, nil
		},
	}
	handler := NewHandler(repo, manifest)

	// Build a list of more than MaxCompareChartPackages
	names := make([]string, layout.MaxCompareChartPackages+2)
	for i := range names {
		names[i] = "pkg" + strings.Repeat("x", i)
	}
	namesParam := strings.Join(names, ",")

	req := httptest.NewRequest(http.MethodGet, "/compare/packages/"+namesParam, nil)
	req.SetPathValue("names", namesParam)
	rr := httptest.NewRecorder()

	handler.HandleCompare(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status 200, got %d", rr.Code)
	}

	body := rr.Body.String()

	// Should show the limit warning
	if !strings.Contains(body, "You can only compare up to") {
		t.Error("expected body to contain limit warning")
	}

	// Should show the excess package names with a remove link
	excessNames := names[layout.MaxCompareChartPackages:]
	for _, name := range excessNames {
		if !strings.Contains(body, name) {
			t.Errorf("expected body to contain excess package name %q", name)
		}
	}

	// The remove link should point to a URL with only the first MaxCompareChartPackages
	trimmedNames := strings.Join(names[:layout.MaxCompareChartPackages], ",")
	if !strings.Contains(body, "/compare/packages/"+trimmedNames) {
		t.Error("expected body to contain remove link with trimmed package list")
	}

	// The "Edit selection" link should preserve ALL names (charted + excess)
	allNames := strings.Join(names, ",")
	if !strings.Contains(body, "/packages?compare="+allNames) {
		t.Error("expected Edit selection link to contain all package names")
	}
}

func TestHandleLegacyCompare(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	handler := NewHandler(nil, manifest)

	req := httptest.NewRequest(http.MethodGet, "/compare/packages", nil)
	rr := httptest.NewRecorder()

	handler.HandleLegacyCompare(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status 200, got %d", rr.Code)
	}
}
