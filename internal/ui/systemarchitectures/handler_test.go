package systemarchitectures

import (
	"context"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"pkgstats.archlinux.de/internal/systemarchitectures"
	"pkgstats.archlinux.de/internal/ui/layout"
)

type mockRepo struct {
	findSeriesByNameFunc func(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*systemarchitectures.SystemArchitecturePopularityList, error)
}

func (m *mockRepo) FindByName(ctx context.Context, name string, startMonth, endMonth int) (*systemarchitectures.SystemArchitecturePopularity, error) {
	return nil, nil
}

func (m *mockRepo) FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*systemarchitectures.SystemArchitecturePopularityList, error) {
	return nil, nil
}

func (m *mockRepo) FindSeriesByName(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*systemarchitectures.SystemArchitecturePopularityList, error) {
	return m.findSeriesByNameFunc(ctx, name, startMonth, endMonth, limit, offset)
}

func TestHandleCompare(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	repo := &mockRepo{
		findSeriesByNameFunc: func(ctx context.Context, name string, _, _, _, _ int) (*systemarchitectures.SystemArchitecturePopularityList, error) {
			return &systemarchitectures.SystemArchitecturePopularityList{
				Total: 1,
				SystemArchitecturePopularities: []systemarchitectures.SystemArchitecturePopularity{
					{Name: name, StartMonth: 202501, EndMonth: 202501, Popularity: 10.5},
				},
			}, nil
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/compare/system-architectures/current", nil)
	req.SetPathValue("preset", "current")
	rr := httptest.NewRecorder()

	handler.HandleCompare(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status 200, got %d", rr.Code)
	}

	body := rr.Body.String()
	if !strings.Contains(body, "Compare System Architectures") {
		t.Error("expected body to contain title")
	}
}

func TestHandleCompare_NotFound(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	handler := NewHandler(nil, manifest)

	req := httptest.NewRequest(http.MethodGet, "/compare/system-architectures/nonexistent", nil)
	req.SetPathValue("preset", "nonexistent")
	rr := httptest.NewRecorder()

	handler.HandleCompare(rr, req)

	if rr.Code != http.StatusNotFound {
		t.Errorf("expected status 404, got %d", rr.Code)
	}
}
