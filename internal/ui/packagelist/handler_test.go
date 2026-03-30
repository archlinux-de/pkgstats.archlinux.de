package packagelist

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
	if m.findAllFunc != nil {
		return m.findAllFunc(ctx, query, startMonth, endMonth, limit, offset)
	}
	return &packages.PackagePopularityList{Total: 0}, nil
}

func (m *mockRepo) FindSeriesByName(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*packages.PackagePopularityList, error) {
	return nil, nil
}

func TestHandlePackages(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	repo := &mockRepo{
		findAllFunc: func(ctx context.Context, query string, _, _, _, _ int) (*packages.PackagePopularityList, error) {
			t.Error("FindAll should not be called without a query")
			return &packages.PackagePopularityList{Total: 0}, nil
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
}

func TestHandlePackages_WithQuery(t *testing.T) {
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

	req := httptest.NewRequest(http.MethodGet, "/packages?query=pacman", nil)
	rr := httptest.NewRecorder()

	handler.HandlePackages(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status 200, got %d", rr.Code)
	}

	body := rr.Body.String()
	if !strings.Contains(body, "pacman") {
		t.Error("expected body to contain package name")
	}
}

func TestHandlePackages_WithCompare(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	// Track which individual names were looked up to ensure comma-separated
	// values are correctly split. This URL schema is also used by the pkgstats
	// client, so the decoding must not change.
	var lookedUp []string
	repo := &mockRepo{
		findByNameFunc: func(_ context.Context, name string, _, _ int) (*packages.PackagePopularity, error) {
			lookedUp = append(lookedUp, name)
			return &packages.PackagePopularity{Name: name, Popularity: 5.0}, nil
		},
		findAllFunc: func(ctx context.Context, query string, _, _, _, _ int) (*packages.PackagePopularityList, error) {
			t.Error("FindAll should not be called when compare is set without query")
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

	if len(lookedUp) != 2 || lookedUp[0] != "glibc" || lookedUp[1] != "linux" {
		t.Errorf("expected individual lookups for [glibc linux], got %v", lookedUp)
	}
}

func TestHandlePackages_WithCompareAndQuery(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	findAllCalled := false
	repo := &mockRepo{
		findAllFunc: func(ctx context.Context, query string, _, _, _, _ int) (*packages.PackagePopularityList, error) {
			findAllCalled = true
			return &packages.PackagePopularityList{
				Total: 1,
				PackagePopularities: []packages.PackagePopularity{
					{Name: "pacman", Popularity: 10.5},
				},
			}, nil
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/packages?query=pac&compare=glibc", nil)
	rr := httptest.NewRecorder()

	handler.HandlePackages(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status 200, got %d", rr.Code)
	}

	if !findAllCalled {
		t.Error("FindAll should be called when both query and compare are set")
	}

	body := rr.Body.String()
	if !strings.Contains(body, "pacman") {
		t.Error("expected body to contain search result")
	}
}

func TestHandlePackages_CompareExceedsSelectLimit(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))

	// Build a list of more than MaxSelectPackages
	names := make([]string, layout.MaxSelectPackages+2)
	for i := range names {
		names[i] = "pkg" + strings.Repeat("x", i)
	}

	repo := &mockRepo{
		findAllFunc: func(_ context.Context, _ string, _, _, _, _ int) (*packages.PackagePopularityList, error) {
			return &packages.PackagePopularityList{
				Total: 1,
				PackagePopularities: []packages.PackagePopularity{
					{Name: "newpkg", Popularity: 1.0},
				},
			}, nil
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/packages?query=pkg&compare="+strings.Join(names, ","), nil)
	rr := httptest.NewRecorder()

	handler.HandlePackages(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status 200, got %d", rr.Code)
	}

	body := rr.Body.String()

	// Should show the selection limit warning
	if !strings.Contains(body, "You can only select up to") {
		t.Error("expected body to contain selection limit warning")
	}

	// Should show excess package names with a remove link
	excessNames := names[layout.MaxSelectPackages:]
	for _, name := range excessNames {
		if !strings.Contains(body, name) {
			t.Errorf("expected body to contain excess package name %q", name)
		}
	}

	// The compare table should have individual remove links (title="Remove X") only for the first MaxSelectPackages
	beyondLimit := names[layout.MaxSelectPackages]
	if strings.Contains(body, "title=\"Remove "+beyondLimit+"\"") {
		t.Error("expected compare table NOT to contain packages beyond the limit as removable entries")
	}

	// The "newpkg" row should NOT have a + link since we're at the limit
	if strings.Contains(body, "Add newpkg") {
		t.Error("expected + link to be hidden when at select limit")
	}
}

func TestHandlePackages_CompareExceedsChartLimit(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))

	// Build a list between MaxCompareChartPackages and MaxSelectPackages
	names := make([]string, layout.MaxCompareChartPackages+2)
	for i := range names {
		names[i] = "pkg" + strings.Repeat("x", i)
	}

	repo := &mockRepo{}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/packages?compare="+strings.Join(names, ","), nil)
	rr := httptest.NewRecorder()

	handler.HandlePackages(rr, req)

	if rr.Code != http.StatusOK {
		t.Errorf("expected status 200, got %d", rr.Code)
	}

	body := rr.Body.String()

	// Should show the chart limit warning
	if !strings.Contains(body, "You can only compare up to") {
		t.Error("expected body to contain chart limit warning")
	}

	// Compare button should be disabled
	if !strings.Contains(body, "disabled") {
		t.Error("expected compare button to be disabled")
	}

	// Should NOT show the selection limit warning
	if strings.Contains(body, "You can only select up to") {
		t.Error("expected body NOT to contain selection limit warning")
	}
}

func TestToggleURL_EncodesQuery(t *testing.T) {
	got := string(toggleURL("foo bar", 0, 25, "", "linux"))
	if !strings.Contains(got, "query=foo+bar") {
		t.Errorf("expected encoded query in URL, got %s", got)
	}
	if strings.Contains(got, "query=foo bar") {
		t.Errorf("expected no literal space in URL, got %s", got)
	}
}

func TestPaginationURL_EncodesQuery(t *testing.T) {
	got := string(paginationURL("foo bar", 25, 25, "linux"))
	if !strings.Contains(got, "query=foo+bar") {
		t.Errorf("expected encoded query in URL, got %s", got)
	}
	if strings.Contains(got, "query=foo bar") {
		t.Errorf("expected no literal space in URL, got %s", got)
	}
}

func TestToggleURL_CompareFormat(t *testing.T) {
	// The compare parameter format (comma-separated package names) is part of the
	// public URL schema and also used by the pkgstats client. Do not change it.
	got := string(toggleURL("", 0, 25, "glibc", "linux"))
	expected := "/packages?limit=25&offset=0&compare=glibc,linux"
	if got != expected {
		t.Errorf("expected %q, got %q", expected, got)
	}
}

func TestToggleURL_RemoveFromCompare(t *testing.T) {
	got := string(toggleURL("", 0, 25, "glibc,linux", "glibc"))
	expected := "/packages?limit=25&offset=0&compare=linux"
	if got != expected {
		t.Errorf("expected %q, got %q", expected, got)
	}
}

func TestPaginationURL_CompareFormat(t *testing.T) {
	got := string(paginationURL("", 0, 25, "glibc,linux,pacman"))
	expected := "/packages?limit=25&offset=0&compare=glibc,linux,pacman"
	if got != expected {
		t.Errorf("expected %q, got %q", expected, got)
	}
}

func TestHandlePackages_CompareError(t *testing.T) {
	manifest, _ := layout.NewManifest([]byte(`{}`))
	repo := &mockRepo{
		findByNameFunc: func(_ context.Context, _ string, _, _ int) (*packages.PackagePopularity, error) {
			return nil, errors.New("db error")
		},
	}
	handler := NewHandler(repo, manifest)

	req := httptest.NewRequest(http.MethodGet, "/packages?compare=glibc", nil)
	rr := httptest.NewRecorder()

	handler.HandlePackages(rr, req)

	if rr.Code != http.StatusInternalServerError {
		t.Errorf("expected status 500, got %d", rr.Code)
	}
}
