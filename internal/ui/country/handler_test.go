package country

import (
	"context"
	"errors"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"pkgstatsd/internal/countries"
	"pkgstatsd/internal/ui/layout"
)

type mockRepo struct {
	findAllFunc          func(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*countries.CountryPopularityList, error)
	findSeriesByCodeFunc func(ctx context.Context, code string, startMonth, endMonth, limit, offset int) (*countries.CountryPopularityList, error)
}

func (m *mockRepo) FindByCode(ctx context.Context, code string, startMonth, endMonth int) (*countries.CountryPopularity, error) {
	return nil, nil
}

func (m *mockRepo) FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*countries.CountryPopularityList, error) {
	return m.findAllFunc(ctx, query, startMonth, endMonth, limit, offset)
}

func (m *mockRepo) FindSeriesByCode(ctx context.Context, code string, startMonth, endMonth, limit, offset int) (*countries.CountryPopularityList, error) {
	if m.findSeriesByCodeFunc != nil {
		return m.findSeriesByCodeFunc(ctx, code, startMonth, endMonth, limit, offset)
	}
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

func TestHandleCountryDetail(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	var queriedCode string
	repo := &mockRepo{
		findSeriesByCodeFunc: func(ctx context.Context, code string, _, _, _, _ int) (*countries.CountryPopularityList, error) {
			queriedCode = code
			return &countries.CountryPopularityList{
				Total: 1,
				CountryPopularities: []countries.CountryPopularity{
					{Code: code, StartMonth: 202501, EndMonth: 202501, Popularity: 10.5},
				},
			}, nil
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/countries/de", nil)
	req.SetPathValue("code", "de")
	rr := httptest.NewRecorder()

	handler.HandleCountryDetail(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status 200, got %d", rr.Code)
	}

	if queriedCode != "DE" {
		t.Errorf("expected uppercase query DE, got %q", queriedCode)
	}

	body := rr.Body.String()
	if !strings.Contains(body, "DE") {
		t.Error("expected body to contain country code")
	}
}

func TestHandleCountryDetail_NotFound(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	repo := &mockRepo{
		findSeriesByCodeFunc: func(ctx context.Context, code string, _, _, _, _ int) (*countries.CountryPopularityList, error) {
			return &countries.CountryPopularityList{Total: 0}, nil
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/countries/xx", nil)
	req.SetPathValue("code", "xx")
	rr := httptest.NewRecorder()

	handler.HandleCountryDetail(rr, req)

	if rr.Code != http.StatusNotFound {
		t.Errorf("expected status 404, got %d", rr.Code)
	}
}

func TestHandleCountryDetail_Error(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	repo := &mockRepo{
		findSeriesByCodeFunc: func(_ context.Context, _ string, _, _, _, _ int) (*countries.CountryPopularityList, error) {
			return nil, errors.New("db error")
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/countries/de", nil)
	req.SetPathValue("code", "de")
	rr := httptest.NewRecorder()

	handler.HandleCountryDetail(rr, req)

	if rr.Code != http.StatusInternalServerError {
		t.Errorf("expected status 500, got %d", rr.Code)
	}
}
