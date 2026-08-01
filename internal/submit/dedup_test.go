package submit

import (
	"context"
	"net/http"
	"net/netip"
	"testing"
	"time"

	"pkgstatsd/internal/database"
)

func TestSaveSubmission_CountsFingerprintAfterExpiry(t *testing.T) {
	db, err := database.New(":memory:")
	if err != nil {
		t.Fatalf("create database: %v", err)
	}
	t.Cleanup(func() { _ = db.Close() })

	now := time.Date(2026, time.July, 1, 12, 0, 0, 0, time.UTC)
	repo := NewRepository(db)
	repo.now = func() time.Time { return now }
	req := &Request{
		System: SystemInfo{Architecture: "x86_64"},
		OS:     OSInfo{Architecture: "x86_64", ID: "arch"},
		Pacman: PacmanInfo{Packages: []string{"pkgstats", "pacman"}},
	}
	entry := NewLogEntry(http.Header{"User-Agent": {"pkgstats/3.5.4"}}, netip.MustParseAddr("203.0.113.50"), []byte(`{"packages":"stable"}`), "DE")

	counted, err := repo.SaveSubmission(context.Background(), req, "", entry)
	if err != nil || !counted {
		t.Fatalf("first submission: counted=%v err=%v", counted, err)
	}

	now = now.Add(deduplicationWindow + time.Second)
	counted, err = repo.SaveSubmission(context.Background(), req, "", entry)
	if err != nil || !counted {
		t.Fatalf("submission after expiry: counted=%v err=%v", counted, err)
	}

	var count int
	if err := db.QueryRow(`SELECT count FROM package WHERE name = 'pacman'`).Scan(&count); err != nil {
		t.Fatalf("read package count: %v", err)
	}
	if count != 2 {
		t.Errorf("package count = %d, want 2", count)
	}
}
