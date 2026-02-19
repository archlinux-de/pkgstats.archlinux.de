package countrypage

import (
	"context"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"pkgstats.archlinux.de/internal/countries"
	"pkgstats.archlinux.de/internal/ui/layout"
)

type mockRepo struct {
	findAllFunc func(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*countries.CountryPopularityList, error)
}

func (m *mockRepo) FindByCode(ctx context.Context, code string, startMonth, endMonth int) (*countries.CountryPopularity, error) {
	return nil, nil
}

func (m *mockRepo) FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*countries.CountryPopularityList, error) {
	return m.findAllFunc(ctx, query, startMonth, endMonth, limit, offset)
}

func (m *mockRepo) FindSeriesByCode(ctx context.Context, code string, startMonth, endMonth, limit, offset int) (*countries.CountryPopularityList, error) {
	return nil, nil
}

func TestHandleCountries(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	repo := &mockRepo{
		findAllFunc: func(ctx context.Context, query string, _, _, _, _ int) (*countries.CountryPopularityList, error) {
			return &countries.CountryPopularityList{
				Total: 1,
				CountryPopularities: []countries.CountryPopularity{
					{Code: "DE", Popularity: 50.0},
				},
			}, nil
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/countries", nil)
	rr := httptest.NewRecorder()

	handler.HandleCountries(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status 200, got %d", rr.Code)
	}

	body := rr.Body.String()
	if !strings.Contains(body, "Country statistics") {
		t.Error("expected body to contain title")
	}
	if !strings.Contains(body, "DE") {
		t.Error("expected body to contain country code")
	}
}
