package operatingsystems

import (
	"context"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"pkgstatsd/internal/operatingsystems"
	"pkgstatsd/internal/ui/layout"
)

type mockRepo struct {
	findAllFunc        func(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*operatingsystems.OperatingSystemIdPopularityList, error)
	findSeriesByIDFunc func(ctx context.Context, id string, startMonth, endMonth, limit, offset int) (*operatingsystems.OperatingSystemIdPopularityList, error)
}

func (m *mockRepo) FindByID(ctx context.Context, id string, startMonth, endMonth int) (*operatingsystems.OperatingSystemIdPopularity, error) {
	return nil, nil
}

func (m *mockRepo) FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*operatingsystems.OperatingSystemIdPopularityList, error) {
	return m.findAllFunc(ctx, query, startMonth, endMonth, limit, offset)
}

func (m *mockRepo) FindSeriesByID(ctx context.Context, id string, startMonth, endMonth, limit, offset int) (*operatingsystems.OperatingSystemIdPopularityList, error) {
	return m.findSeriesByIDFunc(ctx, id, startMonth, endMonth, limit, offset)
}

func TestHandleCompare(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	repo := &mockRepo{
		findAllFunc: func(_ context.Context, _ string, _, _, _, _ int) (*operatingsystems.OperatingSystemIdPopularityList, error) {
			return &operatingsystems.OperatingSystemIdPopularityList{
				Total: 2,
				OperatingSystemIdPopularities: []operatingsystems.OperatingSystemIdPopularity{
					{ID: "arch", StartMonth: 202602, EndMonth: 202602, Popularity: 88.0},
					{ID: "cachyos", StartMonth: 202602, EndMonth: 202602, Popularity: 5.0},
				},
			}, nil
		},
		findSeriesByIDFunc: func(_ context.Context, id string, _, _, _, _ int) (*operatingsystems.OperatingSystemIdPopularityList, error) {
			return &operatingsystems.OperatingSystemIdPopularityList{
				Total: 1,
				OperatingSystemIdPopularities: []operatingsystems.OperatingSystemIdPopularity{
					{ID: id, StartMonth: 202602, EndMonth: 202602, Popularity: 10.5},
				},
			}, nil
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/compare/operating-systems", nil)
	rr := httptest.NewRecorder()

	handler.HandleCompare(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status 200, got %d", rr.Code)
	}

	body := rr.Body.String()
	if !strings.Contains(body, "Compare Operating Systems") {
		t.Error("expected body to contain title")
	}
}
