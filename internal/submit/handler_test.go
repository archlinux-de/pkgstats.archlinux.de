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

	"pkgstats.archlinux.de/internal/database"
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
