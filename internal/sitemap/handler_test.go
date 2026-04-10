package sitemap

import (
	"context"
	"errors"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"pkgstatsd/internal/countries"
	"pkgstatsd/internal/packages"
	"pkgstatsd/internal/web"
)

type mockPackageRepo struct{}

type mockCountryRepo struct{}

type errorPackageRepo struct {
	err error
}

type errorCountryRepo struct {
	err error
}

func (m *mockPackageRepo) FindByName(_ context.Context, _ string, _, _ int) (*packages.PackagePopularity, error) {
	return nil, nil
}

func (m *mockPackageRepo) FindSeriesByName(_ context.Context, _ string, _, _, _, _ int) (*packages.PackagePopularityList, error) {
	return nil, nil
}

func (m *mockPackageRepo) FindAll(_ context.Context, _ string, _, _, _, _ int) (*packages.PackagePopularityList, error) {
	return &packages.PackagePopularityList{
		PackagePopularities: []packages.PackagePopularity{
			{Name: "linux"},
			{Name: "firefox"},
		},
	}, nil
}

func (m *mockCountryRepo) FindByCode(_ context.Context, _ string, _, _ int) (*countries.CountryPopularity, error) {
	return nil, nil
}

func (m *mockCountryRepo) FindSeriesByCode(_ context.Context, _ string, _, _, _, _ int) (*countries.CountryPopularityList, error) {
	return nil, nil
}

func (m *mockCountryRepo) FindAll(_ context.Context, _ string, _, _, _, _ int) (*countries.CountryPopularityList, error) {
	return &countries.CountryPopularityList{
		CountryPopularities: []countries.CountryPopularity{
			{Code: "DE"},
			{Code: "US"},
		},
	}, nil
}

func TestHandleSitemap(t *testing.T) {
	handler := NewHandler(&mockPackageRepo{}, &mockCountryRepo{})

	req := httptest.NewRequest(http.MethodGet, "/sitemap.xml", nil)
	req.Host = "example.com"
	rr := httptest.NewRecorder()

	handler.HandleSitemap(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status 200, got %d", rr.Code)
	}

	if cc := rr.Header().Get("Cache-Control"); cc != "public, max-age=86400" {
		t.Errorf("expected Cache-Control %q, got %q", "public, max-age=86400", cc)
	}

	body := rr.Body.String()

	for _, path := range []string{
		"http://example.com/",
		"http://example.com/packages",
		"http://example.com/countries",
		"http://example.com/countries/de",
		"http://example.com/countries/us",
		"http://example.com/fun",
		"http://example.com/fun/Browsers/current",
		"http://example.com/fun/Browsers/history",
		"http://example.com/fun/Editors/current",
		"http://example.com/fun/Desktop%20Environments/current",
		"http://example.com/fun/Desktop%20Environments/history",
		"http://example.com/packages/linux",
		"http://example.com/packages/firefox",
	} {
		if !strings.Contains(body, "<loc>"+path+"</loc>") {
			t.Errorf("expected sitemap to contain %s", path)
		}
	}

	expectedLastMod := lastDayOfMonth(web.GetLastCompleteMonth())
	if !strings.Contains(body, "<lastmod>"+expectedLastMod+"</lastmod>") {
		t.Errorf("expected sitemap to contain lastmod %s", expectedLastMod)
	}

	// getting-started is static and should not have lastmod
	if strings.Contains(body, "getting-started</loc>\n      <lastmod>") {
		t.Error("expected getting-started to not have lastmod")
	}
}

func (m *errorPackageRepo) FindByName(_ context.Context, _ string, _, _ int) (*packages.PackagePopularity, error) {
	return nil, m.err
}

func (m *errorPackageRepo) FindSeriesByName(_ context.Context, _ string, _, _, _, _ int) (*packages.PackagePopularityList, error) {
	return nil, m.err
}

func (m *errorPackageRepo) FindAll(_ context.Context, _ string, _, _, _, _ int) (*packages.PackagePopularityList, error) {
	return nil, m.err
}

func (m *errorCountryRepo) FindByCode(_ context.Context, _ string, _, _ int) (*countries.CountryPopularity, error) {
	return nil, m.err
}

func (m *errorCountryRepo) FindSeriesByCode(_ context.Context, _ string, _, _, _, _ int) (*countries.CountryPopularityList, error) {
	return nil, m.err
}

func (m *errorCountryRepo) FindAll(_ context.Context, _ string, _, _, _, _ int) (*countries.CountryPopularityList, error) {
	return nil, m.err
}

func TestHandleSitemapReturnsEarlyOnClientDisconnect(t *testing.T) {
	handler := NewHandler(&errorPackageRepo{err: context.Canceled}, &errorCountryRepo{err: context.Canceled})

	req := httptest.NewRequest(http.MethodGet, "/sitemap.xml", nil)
	req.Host = "example.com"
	rr := httptest.NewRecorder()

	handler.HandleSitemap(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status 200, got %d", rr.Code)
	}
	if rr.Body.Len() != 0 {
		t.Errorf("expected empty body on client disconnect, got %d bytes", rr.Body.Len())
	}
}

func TestHandleSitemapServesPartialOnError(t *testing.T) {
	handler := NewHandler(&errorPackageRepo{err: errors.New("db error")}, &errorCountryRepo{err: errors.New("db error")})

	req := httptest.NewRequest(http.MethodGet, "/sitemap.xml", nil)
	req.Host = "example.com"
	rr := httptest.NewRecorder()

	handler.HandleSitemap(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status 200, got %d", rr.Code)
	}

	body := rr.Body.String()
	if !strings.Contains(body, "<loc>http://example.com/</loc>") {
		t.Error("expected static URLs in partial sitemap")
	}
	if strings.Contains(body, "<loc>http://example.com/packages/linux</loc>") {
		t.Error("expected no package URLs on error")
	}
}

func TestLastDayOfMonth(t *testing.T) {
	tests := []struct {
		yearMonth int
		expected  string
	}{
		{202602, "2026-02-28"},
		{202412, "2024-12-31"},
		{202401, "2024-01-31"},
		{202404, "2024-04-30"},
		{202402, "2024-02-29"}, // leap year
	}

	for _, tt := range tests {
		if got := lastDayOfMonth(tt.yearMonth); got != tt.expected {
			t.Errorf("lastDayOfMonth(%d) = %q, want %q", tt.yearMonth, got, tt.expected)
		}
	}
}
