package database

import (
	"database/sql"
	"testing"
	"time"

	_ "modernc.org/sqlite"
)

func TestMonthlySamplesCache(t *testing.T) {
	db, err := sql.Open("sqlite", ":memory:")
	if err != nil {
		t.Fatalf("open database: %v", err)
	}
	defer func() { _ = db.Close() }()

	_, _ = db.Exec(`CREATE TABLE test_samples (month INTEGER, count INTEGER)`)
	_, _ = db.Exec(`INSERT INTO test_samples (month, count) VALUES (202501, 100), (202502, 200)`)

	cache := NewMonthlySamplesCache(db, "SELECT month, count FROM test_samples")

	// First load
	res, err := cache.Get(202501, 202502)
	if err != nil {
		t.Fatalf("Get error: %v", err)
	}
	if len(res) != 2 {
		t.Errorf("expected 2 results, got %d", len(res))
	}
	if res[202501] != 100 {
		t.Errorf("expected 100 for 202501, got %d", res[202501])
	}

	// Update DB, but cache should still be valid
	_, _ = db.Exec(`UPDATE test_samples SET count = 300 WHERE month = 202501`)
	res, _ = cache.Get(202501, 202502)
	if res[202501] != 100 {
		t.Errorf("expected cached value 100, got %d", res[202501])
	}

	// Force expire cache by setting expiry in the past
	cache.mu.Lock()
	cache.expiry = time.Now().Add(-1 * time.Hour)
	cache.mu.Unlock()

	res, _ = cache.Get(202501, 202502)
	if res[202501] != 300 {
		t.Errorf("expected new value 300 after expiry, got %d", res[202501])
	}
}

func TestStartOfNextMonth(t *testing.T) {
	now := time.Now()
	next := startOfNextMonth()

	if next.Month() == now.Month() {
		t.Errorf("expected different month, got %v", next.Month())
	}
	if next.Day() != 1 {
		t.Errorf("expected day 1, got %d", next.Day())
	}
}
