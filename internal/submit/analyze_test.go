package submit

import (
	"context"
	"encoding/json"
	"fmt"
	"testing"

	"pkgstatsd/internal/database"
)

func TestFindMaterialReplays(t *testing.T) {
	db, err := database.New(":memory:")
	if err != nil {
		t.Fatalf("create database: %v", err)
	}
	t.Cleanup(func() { _ = db.Close() })

	packages := make([]string, minReplayPackageObservations)
	for i := range packages {
		packages[i] = fmt.Sprintf("package-%d", i)
	}
	payload, err := json.Marshal(Request{Pacman: PacmanInfo{Packages: packages}})
	if err != nil {
		t.Fatalf("marshal payload: %v", err)
	}

	_, err = db.Exec(`INSERT INTO package (name, month, count) VALUES ('base', 202607, 100000)`)
	if err != nil {
		t.Fatalf("insert aggregate count: %v", err)
	}

	for _, entry := range []struct {
		ip   string
		hash string
	}{
		{"192.0.2.1", "same-hash"},
		{"192.0.2.99", "same-hash"},
		{"198.51.100.1", "same-hash"},
		{"192.0.2.2", "different-hash"},
	} {
		_, err := db.Exec(`
			INSERT INTO submission_log (month, timestamp, ip, headers, payload, payload_hash, country)
			VALUES (202607, 0, ?, '{}', ?, ?, '')`, entry.ip, string(payload), entry.hash)
		if err != nil {
			t.Fatalf("insert log entry: %v", err)
		}
	}

	groups, total, err := findMaterialReplays(context.Background(), db, 202607)
	if err != nil {
		t.Fatalf("find material replays: %v", err)
	}
	if total != 100000 {
		t.Errorf("aggregate package observations = %d, want 100000", total)
	}
	if len(groups) != 1 {
		t.Fatalf("material replay groups = %d, want 1", len(groups))
	}

	group := groups[0]
	if group.Network != "192.0.2.0" {
		t.Errorf("network = %q, want 192.0.2.0", group.Network)
	}
	if group.Reports != 2 {
		t.Errorf("reports = %d, want 2", group.Reports)
	}
	if group.ExtraPackageObservations != minReplayPackageObservations {
		t.Errorf("extra observations = %d, want %d", group.ExtraPackageObservations, minReplayPackageObservations)
	}
}

func TestValidMonth(t *testing.T) {
	for _, month := range []int{202601, 202612} {
		if !validMonth(month) {
			t.Errorf("validMonth(%d) = false, want true", month)
		}
	}
	for _, month := range []int{0, 202600, 202613, 99912} {
		if validMonth(month) {
			t.Errorf("validMonth(%d) = true, want false", month)
		}
	}
}
