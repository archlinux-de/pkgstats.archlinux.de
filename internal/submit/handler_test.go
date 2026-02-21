package submit

import (
	"context"
	"database/sql"
	"errors"
	"net/http"
	"net/http/httptest"
	"net/netip"
	"strings"
	"testing"
	"time"

	"pkgstatsd/internal/database"
)

type mockGeoIP struct {
	code string
}

func (m *mockGeoIP) GetCountryCode(_ netip.Addr) string {
	return m.code
}

func (m *mockGeoIP) Close() error {
	return nil
}

func setupTestHandler(t *testing.T) (*Handler, *sql.DB) {
	t.Helper()

	db, err := database.New(":memory:")
	if err != nil {
		t.Fatalf("failed to create test database: %v", err)
	}
	t.Cleanup(func() { _ = db.Close() })

	repo := NewRepository(db)
	geoip := &mockGeoIP{code: "DE"}
	handler := NewHandler(repo, geoip, NoopRateLimiter{})

	return handler, db
}

func submitRequest(handler *Handler, body string) *httptest.ResponseRecorder {
	req := httptest.NewRequest(http.MethodPost, "/api/submit", strings.NewReader(body))
	req.Header.Set("X-Forwarded-For", "203.0.113.50")
	w := httptest.NewRecorder()
	handler.HandleSubmit(w, req)
	return w
}

func validRequestBody() string {
	return `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64", "id": "arch"},
		"pacman": {
			"mirror": "https://geo.mirror.pkgbuild.com/",
			"packages": ["pkgstats", "pacman", "linux"]
		}
	}`
}

func TestHandleSubmit_Success(t *testing.T) {
	handler, db := setupTestHandler(t)

	w := submitRequest(handler, validRequestBody())

	if w.Code != http.StatusNoContent {
		t.Fatalf("expected 204, got %d: %s", w.Code, w.Body.String())
	}

	// Verify empty response body
	if w.Body.Len() != 0 {
		t.Errorf("expected empty response body, got %d bytes", w.Body.Len())
	}

	// Verify packages
	var pkgCount int
	_ = db.QueryRow("SELECT COUNT(*) FROM package").Scan(&pkgCount)
	if pkgCount != 3 {
		t.Errorf("expected 3 packages, got %d", pkgCount)
	}

	// Verify country
	var countryCount int
	_ = db.QueryRow("SELECT count FROM country WHERE code = 'DE'").Scan(&countryCount)
	if countryCount != 1 {
		t.Errorf("expected country count 1, got %d", countryCount)
	}

	// Verify mirror
	var mirrorCount int
	_ = db.QueryRow("SELECT count FROM mirror WHERE url = 'https://geo.mirror.pkgbuild.com/'").Scan(&mirrorCount)
	if mirrorCount != 1 {
		t.Errorf("expected mirror count 1, got %d", mirrorCount)
	}

	// Verify system architecture
	var sysArchCount int
	_ = db.QueryRow("SELECT count FROM system_architecture WHERE name = 'x86_64'").Scan(&sysArchCount)
	if sysArchCount != 1 {
		t.Errorf("expected system_architecture count 1, got %d", sysArchCount)
	}

	// Verify OS architecture
	var osArchCount int
	_ = db.QueryRow("SELECT count FROM operating_system_architecture WHERE name = 'x86_64'").Scan(&osArchCount)
	if osArchCount != 1 {
		t.Errorf("expected operating_system_architecture count 1, got %d", osArchCount)
	}

	// Verify OS ID
	var osIDCount int
	_ = db.QueryRow("SELECT count FROM operating_system_id WHERE id = 'arch'").Scan(&osIDCount)
	if osIDCount != 1 {
		t.Errorf("expected operating_system_id count 1, got %d", osIDCount)
	}
}

func TestHandleSubmit_IncrementsCount(t *testing.T) {
	handler, db := setupTestHandler(t)

	// Submit twice
	w1 := submitRequest(handler, validRequestBody())
	if w1.Code != http.StatusNoContent {
		t.Fatalf("first submit: expected 204, got %d", w1.Code)
	}

	w2 := submitRequest(handler, validRequestBody())
	if w2.Code != http.StatusNoContent {
		t.Fatalf("second submit: expected 204, got %d", w2.Code)
	}

	// Verify package count incremented
	var count int
	_ = db.QueryRow("SELECT count FROM package WHERE name = 'pacman'").Scan(&count)
	if count != 2 {
		t.Errorf("expected package count 2, got %d", count)
	}

	// Verify single row (not duplicate)
	var rows int
	_ = db.QueryRow("SELECT COUNT(*) FROM package WHERE name = 'pacman'").Scan(&rows)
	if rows != 1 {
		t.Errorf("expected 1 package row, got %d", rows)
	}
}

func TestHandleSubmit_RateLimited(t *testing.T) {
	db, err := database.New(":memory:")
	if err != nil {
		t.Fatalf("failed to create test database: %v", err)
	}
	t.Cleanup(func() { _ = db.Close() })

	repo := NewRepository(db)
	geoip := &mockGeoIP{code: "DE"}
	limiter := NewInMemoryRateLimiter()
	limiter.limit = 2
	handler := NewHandler(repo, geoip, limiter)

	body := validRequestBody()

	// First two should succeed
	w1 := submitRequest(handler, body)
	if w1.Code != http.StatusNoContent {
		t.Fatalf("request 1: expected 204, got %d", w1.Code)
	}
	w2 := submitRequest(handler, body)
	if w2.Code != http.StatusNoContent {
		t.Fatalf("request 2: expected 204, got %d", w2.Code)
	}

	// Third should be rate limited
	w3 := submitRequest(handler, body)
	if w3.Code != http.StatusTooManyRequests {
		t.Errorf("request 3: expected 429, got %d", w3.Code)
	}
}

func TestHandleSubmit_LocalMirrorIgnored(t *testing.T) {
	handler, db := setupTestHandler(t)

	body := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64"},
		"pacman": {
			"mirror": "file:///var/cache/pacman/",
			"packages": ["pkgstats", "pacman", "linux"]
		}
	}`

	w := submitRequest(handler, body)
	if w.Code != http.StatusNoContent {
		t.Fatalf("expected 204, got %d: %s", w.Code, w.Body.String())
	}

	var mirrorRows int
	_ = db.QueryRow("SELECT COUNT(*) FROM mirror").Scan(&mirrorRows)
	if mirrorRows != 0 {
		t.Errorf("expected 0 mirror rows, got %d", mirrorRows)
	}
}

func TestHandleSubmit_LongMirrorRejected(t *testing.T) {
	handler, db := setupTestHandler(t)

	longURL := "https://mirror.example.com/" + strings.Repeat("a", 230) + "/"
	body := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64"},
		"pacman": {
			"mirror": "` + longURL + `",
			"packages": ["pkgstats", "pacman", "linux"]
		}
	}`

	w := submitRequest(handler, body)
	if w.Code != http.StatusNoContent {
		t.Fatalf("expected 204, got %d: %s", w.Code, w.Body.String())
	}

	var mirrorRows int
	_ = db.QueryRow("SELECT COUNT(*) FROM mirror").Scan(&mirrorRows)
	if mirrorRows != 0 {
		t.Errorf("expected 0 mirror rows for long URL, got %d", mirrorRows)
	}
}

func TestHandleSubmit_InvalidVersion(t *testing.T) {
	handler, _ := setupTestHandler(t)

	body := `{
		"version": "2",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64"},
		"pacman": {"packages": ["pacman"]}
	}`

	w := submitRequest(handler, body)
	if w.Code != http.StatusBadRequest {
		t.Errorf("expected 400, got %d", w.Code)
	}
}

func TestHandleSubmit_EmptyPackages(t *testing.T) {
	handler, _ := setupTestHandler(t)

	body := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64"},
		"pacman": {"packages": []}
	}`

	w := submitRequest(handler, body)
	if w.Code != http.StatusBadRequest {
		t.Errorf("expected 400, got %d", w.Code)
	}
}

func TestHandleSubmit_InvalidJSON(t *testing.T) {
	handler, _ := setupTestHandler(t)

	w := submitRequest(handler, `{not valid json}`)
	if w.Code != http.StatusBadRequest {
		t.Errorf("expected 400, got %d", w.Code)
	}
}

func TestHandleSubmit_DeduplicatesPackages(t *testing.T) {
	handler, db := setupTestHandler(t)

	body := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64"},
		"pacman": {
			"packages": ["pkgstats", "pacman", "PACMAN", "linux"]
		}
	}`

	w := submitRequest(handler, body)
	if w.Code != http.StatusNoContent {
		t.Fatalf("expected 204, got %d: %s", w.Code, w.Body.String())
	}

	var pkgCount int
	_ = db.QueryRow("SELECT COUNT(*) FROM package").Scan(&pkgCount)
	if pkgCount != 3 {
		t.Errorf("expected 3 deduplicated packages, got %d", pkgCount)
	}
}

func TestHandleSubmit_NoMirrorStored(t *testing.T) {
	handler, db := setupTestHandler(t)

	body := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64"},
		"pacman": {
			"mirror": "",
			"packages": ["pkgstats", "pacman", "linux"]
		}
	}`

	w := submitRequest(handler, body)
	if w.Code != http.StatusNoContent {
		t.Fatalf("expected 204, got %d: %s", w.Code, w.Body.String())
	}

	var mirrorRows int
	_ = db.QueryRow("SELECT COUNT(*) FROM mirror").Scan(&mirrorRows)
	if mirrorRows != 0 {
		t.Errorf("expected 0 mirror rows, got %d", mirrorRows)
	}
}

func TestHandleSubmit_NoCountryWhenGeoIPEmpty(t *testing.T) {
	db, err := database.New(":memory:")
	if err != nil {
		t.Fatalf("failed to create test database: %v", err)
	}
	t.Cleanup(func() { _ = db.Close() })

	repo := NewRepository(db)
	geoip := &mockGeoIP{code: ""}
	handler := NewHandler(repo, geoip, NoopRateLimiter{})

	body := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64"},
		"pacman": {
			"packages": ["pkgstats", "pacman", "linux"]
		}
	}`

	w := submitRequest(handler, body)
	if w.Code != http.StatusNoContent {
		t.Fatalf("expected 204, got %d: %s", w.Code, w.Body.String())
	}

	var countryRows int
	_ = db.QueryRow("SELECT COUNT(*) FROM country").Scan(&countryRows)
	if countryRows != 0 {
		t.Errorf("expected 0 country rows, got %d", countryRows)
	}
}

func TestHandleSubmit_InvalidPackageName(t *testing.T) {
	handler, _ := setupTestHandler(t)

	body := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64"},
		"pacman": {"packages": ["-pkgstats"]}
	}`

	w := submitRequest(handler, body)
	if w.Code != http.StatusBadRequest {
		t.Errorf("expected 400, got %d: %s", w.Code, w.Body.String())
	}
}

func TestHandleSubmit_InvalidPackageList(t *testing.T) {
	handler, _ := setupTestHandler(t)

	body := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64"},
		"pacman": {"packages": ["linux", "base"]}
	}`

	w := submitRequest(handler, body)
	if w.Code != http.StatusBadRequest {
		t.Errorf("expected 400 for missing expected packages, got %d: %s", w.Code, w.Body.String())
	}
}

func TestHandleSubmit_WithOSID(t *testing.T) {
	handler, db := setupTestHandler(t)

	body := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64", "id": "arch"},
		"pacman": {
			"packages": ["pkgstats", "pacman", "linux"]
		}
	}`

	w := submitRequest(handler, body)
	if w.Code != http.StatusNoContent {
		t.Fatalf("expected 204, got %d: %s", w.Code, w.Body.String())
	}

	var osIDCount int
	_ = db.QueryRow("SELECT count FROM operating_system_id WHERE id = 'arch'").Scan(&osIDCount)
	if osIDCount != 1 {
		t.Errorf("expected operating_system_id count 1, got %d", osIDCount)
	}
}

func TestHandleSubmit_RateLimitError(t *testing.T) {
	db, err := database.New(":memory:")
	if err != nil {
		t.Fatalf("failed to create test database: %v", err)
	}
	t.Cleanup(func() { _ = db.Close() })

	repo := NewRepository(db)
	geoip := &mockGeoIP{code: "DE"}
	limiter := &errorRateLimiter{}
	handler := NewHandler(repo, geoip, limiter)

	w := submitRequest(handler, validRequestBody())
	if w.Code != http.StatusInternalServerError {
		t.Errorf("expected 500, got %d", w.Code)
	}
}

// errorRateLimiter always returns an error.
type errorRateLimiter struct{}

func (errorRateLimiter) Allow(_ context.Context, _ string) (bool, time.Time, error) {
	return false, time.Time{}, errors.New("rate limit error")
}

func TestHandleSubmit_UnsupportedVersions(t *testing.T) {
	handler, _ := setupTestHandler(t)

	versions := []string{
		"1.0", "2.0", "2.1", "2.2", "2.3", "2.4", "2.4.0", "2.4.2",
		"2.4.9999", "2.4.2-5-g163d6c2", "2.5.0", "3.0.0", "3.1", "3.2.0",
		"0.1", "", "a", "1.0alpha", "42",
	}

	for _, version := range versions {
		t.Run("version="+version, func(t *testing.T) {
			body := `{
				"version": "` + version + `",
				"system": {"architecture": "x86_64"},
				"os": {"architecture": "x86_64"},
				"pacman": {"packages": ["pkgstats", "pacman", "linux"]}
			}`

			w := submitRequest(handler, body)
			if w.Code != http.StatusBadRequest {
				t.Errorf("version %q: expected 400, got %d", version, w.Code)
			}
		})
	}
}

func TestHandleSubmit_SupportedArchitectureCombinations(t *testing.T) {
	archCombos := []struct {
		systemArch string
		osArch     string
	}{
		// x86_64 system
		{"x86_64", "x86_64"},
		{"x86_64", "i686"},
		{"x86_64", "i586"},
		// x86_64_v2 system
		{"x86_64_v2", "x86_64"},
		{"x86_64_v2", "i686"},
		{"x86_64_v2", "i586"},
		// x86_64_v3 system
		{"x86_64_v3", "x86_64"},
		{"x86_64_v3", "i686"},
		{"x86_64_v3", "i586"},
		// x86_64_v4 system
		{"x86_64_v4", "x86_64"},
		{"x86_64_v4", "i686"},
		{"x86_64_v4", "i586"},
		// i686 system
		{"i686", "i686"},
		{"i686", "i586"},
		// i586 system
		{"i586", "i586"},
		// aarch64 system
		{"aarch64", "aarch64"},
		{"aarch64", "armv7h"},
		{"aarch64", "armv6h"},
		{"aarch64", "armv7l"},
		{"aarch64", "armv6l"},
		{"aarch64", "arm"},
		{"aarch64", "armv5tel"},
		// armv7 system
		{"armv7", "armv7h"},
		{"armv7", "armv6h"},
		{"armv7", "armv7l"},
		{"armv7", "armv6l"},
		{"armv7", "arm"},
		{"armv7", "armv5tel"},
		// armv6 system
		{"armv6", "armv6h"},
		{"armv6", "armv6l"},
		{"armv6", "arm"},
		{"armv6", "armv5tel"},
		// armv5 system
		{"armv5", "arm"},
		{"armv5", "armv5tel"},
		// riscv64 system
		{"riscv64", "riscv64"},
		// loong64 system
		{"loong64", "loongarch64"},
	}

	for _, combo := range archCombos {
		t.Run(combo.systemArch+"/"+combo.osArch, func(t *testing.T) {
			handler, db := setupTestHandler(t)

			body := `{
				"version": "3",
				"system": {"architecture": "` + combo.systemArch + `"},
				"os": {"architecture": "` + combo.osArch + `"},
				"pacman": {
					"packages": ["pkgstats", "pacman", "linux"]
				}
			}`

			w := submitRequest(handler, body)
			if w.Code != http.StatusNoContent {
				t.Fatalf("expected 204, got %d: %s", w.Code, w.Body.String())
			}

			var sysArchCount int
			_ = db.QueryRow("SELECT count FROM system_architecture WHERE name = ?", combo.systemArch).Scan(&sysArchCount)
			if sysArchCount != 1 {
				t.Errorf("expected system_architecture count 1 for %s, got %d", combo.systemArch, sysArchCount)
			}

			var osArchCount int
			_ = db.QueryRow("SELECT count FROM operating_system_architecture WHERE name = ?", combo.osArch).Scan(&osArchCount)
			if osArchCount != 1 {
				t.Errorf("expected operating_system_architecture count 1 for %s, got %d", combo.osArch, osArchCount)
			}
		})
	}
}

func TestHandleSubmit_UnsupportedArchitectureCombinations(t *testing.T) {
	handler, _ := setupTestHandler(t)

	archCombos := []struct {
		systemArch string
		osArch     string
	}{
		{"", ""},
		{"ppc", "ppc"},
		{"i486", "i486"},
		{"aarch64", "x86_64"},
		{"aarch64", "armv5"},
		{"x86_64", "aarch64"},
	}

	for _, combo := range archCombos {
		t.Run(combo.systemArch+"/"+combo.osArch, func(t *testing.T) {
			body := `{
				"version": "3",
				"system": {"architecture": "` + combo.systemArch + `"},
				"os": {"architecture": "` + combo.osArch + `"},
				"pacman": {"packages": ["pkgstats", "pacman", "linux"]}
			}`

			w := submitRequest(handler, body)
			if w.Code != http.StatusBadRequest {
				t.Errorf("arch combo (%s, %s): expected 400, got %d", combo.systemArch, combo.osArch, w.Code)
			}
		})
	}
}

func TestHandleSubmit_LongPackageName(t *testing.T) {
	handler, _ := setupTestHandler(t)

	longPkg := strings.Repeat("a", 256)
	body := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64"},
		"pacman": {"packages": ["pkgstats", "pacman", "` + longPkg + `"]}
	}`

	w := submitRequest(handler, body)
	if w.Code != http.StatusBadRequest {
		t.Errorf("expected 400 for long package name, got %d", w.Code)
	}
}

func TestHandleSubmit_OnlyUnexpectedPackages(t *testing.T) {
	handler, _ := setupTestHandler(t)

	body := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64"},
		"pacman": {"packages": ["some-other-package"]}
	}`

	w := submitRequest(handler, body)
	if w.Code != http.StatusBadRequest {
		t.Errorf("expected 400 for missing expected packages, got %d: %s", w.Code, w.Body.String())
	}
}

func TestHandleSubmit_OSIDCountIncrements(t *testing.T) {
	handler, db := setupTestHandler(t)

	body := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64", "id": "arch"},
		"pacman": {
			"packages": ["pkgstats", "pacman", "linux"]
		}
	}`

	w1 := submitRequest(handler, body)
	if w1.Code != http.StatusNoContent {
		t.Fatalf("first submit: expected 204, got %d", w1.Code)
	}

	w2 := submitRequest(handler, body)
	if w2.Code != http.StatusNoContent {
		t.Fatalf("second submit: expected 204, got %d", w2.Code)
	}

	var count int
	_ = db.QueryRow("SELECT count FROM operating_system_id WHERE id = 'arch'").Scan(&count)
	if count != 2 {
		t.Errorf("expected operating_system_id count 2, got %d", count)
	}

	var rows int
	_ = db.QueryRow("SELECT COUNT(*) FROM operating_system_id WHERE id = 'arch'").Scan(&rows)
	if rows != 1 {
		t.Errorf("expected 1 operating_system_id row, got %d", rows)
	}
}

func TestHandleSubmit_NoOSIDWhenAbsent(t *testing.T) {
	handler, db := setupTestHandler(t)

	body := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64"},
		"pacman": {
			"packages": ["pkgstats", "pacman", "linux"]
		}
	}`

	w := submitRequest(handler, body)
	if w.Code != http.StatusNoContent {
		t.Fatalf("expected 204, got %d: %s", w.Code, w.Body.String())
	}

	var osIDRows int
	_ = db.QueryRow("SELECT COUNT(*) FROM operating_system_id").Scan(&osIDRows)
	if osIDRows != 0 {
		t.Errorf("expected 0 operating_system_id rows when os.id absent, got %d", osIDRows)
	}
}

func TestHandleSubmit_MethodNotAllowed(t *testing.T) {
	handler, _ := setupTestHandler(t)

	mux := http.NewServeMux()
	handler.RegisterRoutes(mux)

	req := httptest.NewRequest(http.MethodGet, "/api/submit", nil)
	w := httptest.NewRecorder()
	mux.ServeHTTP(w, req)

	if w.Code != http.StatusMethodNotAllowed {
		t.Errorf("expected 405, got %d", w.Code)
	}
}
