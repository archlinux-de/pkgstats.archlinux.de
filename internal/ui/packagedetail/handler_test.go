package packagedetail

import (
	"context"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"pkgstats.archlinux.de/internal/packages"
	"pkgstats.archlinux.de/internal/ui/layout"
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

func TestHandlePackageDetail(t *testing.T) {
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

	req := httptest.NewRequest(http.MethodGet, "/packages/pacman", nil)
	req.SetPathValue("name", "pacman")
	rr := httptest.NewRecorder()

	handler.HandlePackageDetail(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status 200, got %d", rr.Code)
	}

	body := rr.Body.String()
	if !strings.Contains(body, "pacman - Package statistics") {
		t.Error("expected body to contain package name in title")
	}
}

func TestHandlePackageDetail_NotFound(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	repo := &mockRepo{
		findSeriesByNameFunc: func(ctx context.Context, name string, _, _, _, _ int) (*packages.PackagePopularityList, error) {
			return &packages.PackagePopularityList{Total: 0}, nil
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/packages/nonexistent", nil)
	req.SetPathValue("name", "nonexistent")
	rr := httptest.NewRecorder()

	handler.HandlePackageDetail(rr, req)

	if rr.Code != http.StatusNotFound {
		t.Errorf("expected status 404, got %d", rr.Code)
	}
}
