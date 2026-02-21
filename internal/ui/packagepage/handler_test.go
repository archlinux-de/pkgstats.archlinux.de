package packagepage

import (
	"context"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"pkgstatsd/internal/packages"
	"pkgstatsd/internal/ui/layout"
)

type mockRepo struct {
	findAllFunc    func(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*packages.PackagePopularityList, error)
	findByNameFunc func(ctx context.Context, name string, startMonth, endMonth int) (*packages.PackagePopularity, error)
}

func (m *mockRepo) FindByName(ctx context.Context, name string, startMonth, endMonth int) (*packages.PackagePopularity, error) {
	if m.findByNameFunc != nil {
		return m.findByNameFunc(ctx, name, startMonth, endMonth)
	}
	return &packages.PackagePopularity{Name: name, Popularity: 5.0}, nil
}

func (m *mockRepo) FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*packages.PackagePopularityList, error) {
	return m.findAllFunc(ctx, query, startMonth, endMonth, limit, offset)
}

func (m *mockRepo) FindSeriesByName(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*packages.PackagePopularityList, error) {
	return nil, nil
}

func TestHandlePackages(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	repo := &mockRepo{
		findAllFunc: func(ctx context.Context, query string, _, _, _, _ int) (*packages.PackagePopularityList, error) {
			return &packages.PackagePopularityList{
				Total: 1,
				PackagePopularities: []packages.PackagePopularity{
					{Name: "pacman", Popularity: 10.5},
				},
			}, nil
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/packages", nil)
	rr := httptest.NewRecorder()

	handler.HandlePackages(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status 200, got %d", rr.Code)
	}

	body := rr.Body.String()
	if !strings.Contains(body, "Package statistics") {
		t.Error("expected body to contain title")
	}
	if !strings.Contains(body, "pacman") {
		t.Error("expected body to contain package name")
	}
}

func TestHandlePackages_WithCompare(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	repo := &mockRepo{
		findAllFunc: func(ctx context.Context, query string, _, _, _, _ int) (*packages.PackagePopularityList, error) {
			return &packages.PackagePopularityList{Total: 0}, nil
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/packages?compare=glibc,linux", nil)
	rr := httptest.NewRecorder()

	handler.HandlePackages(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status 200, got %d", rr.Code)
	}

	body := rr.Body.String()
	if !strings.Contains(body, "glibc") || !strings.Contains(body, "linux") {
		t.Error("expected body to contain compare package names")
	}
}
