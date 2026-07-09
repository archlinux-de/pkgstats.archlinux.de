package submit

import (
	"context"
	"encoding/json"
	"testing"

	"pkgstatsd/internal/database"
)

func TestCleanupHeaders(t *testing.T) {
	tests := []struct {
		name          string
		input         string
		wantMigration bool
		wantErr       bool
		wantFlat      map[string]string
	}{
		// --- Flattening (old array format) ---
		{
			name:          "old format single value",
			input:         `{"User-Agent":["pkgstats/3.0"]}`,
			wantMigration: true,
			wantFlat:      map[string]string{"User-Agent": "pkgstats/3.0"},
		},
		{
			name:          "old format multi value",
			input:         `{"Accept":["text/html","application/json"]}`,
			wantMigration: true,
			wantFlat:      map[string]string{"Accept": "text/html, application/json"},
		},
		{
			name:          "old format with empty array value",
			input:         `{"X-Empty":[]}`,
			wantMigration: true,
			wantFlat:      map[string]string{"X-Empty": ""},
		},
		// --- Stripping redundant headers (new flat format) ---
		{
			name:          "strip X-Real-Ip from flat format",
			input:         `{"User-Agent":"pkgstats/3.5.3","X-Real-Ip":"1.2.3.4"}`,
			wantMigration: true,
			wantFlat:      map[string]string{"User-Agent": "pkgstats/3.5.3"},
		},
		{
			name:          "strip X-Forwarded-Proto from flat format",
			input:         `{"User-Agent":"pkgstats/3.5.3","X-Forwarded-Proto":"https"}`,
			wantMigration: true,
			wantFlat:      map[string]string{"User-Agent": "pkgstats/3.5.3"},
		},
		{
			name:          "strip both redundant headers from realistic entry",
			input:         `{"Accept":"application/json","Accept-Encoding":"gzip","Content-Type":"application/json","User-Agent":"pkgstats/3.5.3","X-Forwarded-Proto":"https","X-Real-Ip":"46.253.254.161"}`,
			wantMigration: true,
			wantFlat: map[string]string{
				"Accept":          "application/json",
				"Accept-Encoding": "gzip",
				"Content-Type":    "application/json",
				"User-Agent":      "pkgstats/3.5.3",
			},
		},
		// --- Combined: old format + redundant headers ---
		{
			name:          "flatten and strip in one pass",
			input:         `{"User-Agent":["pkgstats/3.0"],"X-Real-Ip":["1.2.3.4"],"X-Forwarded-Proto":["https"]}`,
			wantMigration: true,
			wantFlat:      map[string]string{"User-Agent": "pkgstats/3.0"},
		},
		// --- No-op cases ---
		{
			name:          "already clean, no change needed",
			input:         `{"User-Agent":"pkgstats/3.5.3","Accept":"application/json"}`,
			wantMigration: false,
		},
		{
			name:          "empty object, no change needed",
			input:         `{}`,
			wantMigration: false,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, needsMigration, err := cleanupHeaders(tt.input)

			if tt.wantErr {
				if err == nil {
					t.Fatal("expected error, got nil")
				}
				return
			}
			if err != nil {
				t.Fatalf("unexpected error: %v", err)
			}

			if needsMigration != tt.wantMigration {
				t.Errorf("needsMigration = %v, want %v", needsMigration, tt.wantMigration)
			}

			if !needsMigration {
				return
			}

			var gotMap map[string]string
			if err := json.Unmarshal(got, &gotMap); err != nil {
				t.Fatalf("failed to parse result JSON: %v", err)
			}

			if len(gotMap) != len(tt.wantFlat) {
				t.Errorf("got %d keys, want %d: %v", len(gotMap), len(tt.wantFlat), gotMap)
			}
			for k, want := range tt.wantFlat {
				if v, ok := gotMap[k]; !ok {
					t.Errorf("missing key %q", k)
				} else if v != want {
					t.Errorf("key %q: got %q, want %q", k, v, want)
				}
			}
			for k := range headersToSkip {
				if _, ok := gotMap[k]; ok {
					t.Errorf("key %q should have been stripped", k)
				}
			}
		})
	}
}

func TestMigrateHeaders(t *testing.T) {
	db, err := database.New(":memory:")
	if err != nil {
		t.Fatalf("failed to create test database: %v", err)
	}
	t.Cleanup(func() { _ = db.Close() })

	// Three rows:
	//   1. Old array format with redundant headers — needs both flatten + strip.
	//   2. Flat format with redundant headers — needs strip only.
	//   3. Already clean — no update needed.
	_, err = db.Exec(`
		INSERT INTO submission_log (month, timestamp, ip, headers, payload, payload_hash, country)
		VALUES
			(202607, 1000, '1.1.1.1',
			 '{"User-Agent":["pkgstats/3.0"],"X-Real-Ip":["1.1.1.1"],"X-Forwarded-Proto":["https"]}',
			 '{}', 'hash1', 'DE'),
			(202607, 1001, '2.2.2.2',
			 '{"User-Agent":"pkgstats/3.5.3","X-Real-Ip":"2.2.2.2","X-Forwarded-Proto":"https"}',
			 '{}', 'hash2', 'US'),
			(202607, 1002, '3.3.3.3',
			 '{"User-Agent":"pkgstats/3.5.3","Accept":"application/json"}',
			 '{}', 'hash3', 'FR')
	`)
	if err != nil {
		t.Fatalf("failed to insert test rows: %v", err)
	}

	ctx := context.Background()
	fixed, skipped, err := migrateHeaders(ctx, db)
	if err != nil {
		t.Fatalf("migrateHeaders returned error: %v", err)
	}

	if fixed != 2 {
		t.Errorf("fixed = %d, want 2", fixed)
	}
	if skipped != 1 {
		t.Errorf("skipped = %d, want 1", skipped)
	}

	// Row 1: should be flat and stripped.
	var h1 string
	if err := db.QueryRow(`SELECT headers FROM submission_log WHERE payload_hash = 'hash1'`).Scan(&h1); err != nil {
		t.Fatalf("read hash1: %v", err)
	}
	var m1 map[string]string
	if err := json.Unmarshal([]byte(h1), &m1); err != nil {
		t.Fatalf("hash1 not valid flat JSON: %v — raw: %s", err, h1)
	}
	if m1["User-Agent"] != "pkgstats/3.0" {
		t.Errorf("hash1 User-Agent = %q, want pkgstats/3.0", m1["User-Agent"])
	}
	for k := range headersToSkip {
		if _, ok := m1[k]; ok {
			t.Errorf("hash1: key %q should have been stripped", k)
		}
	}

	// Row 2: should have redundant headers stripped.
	var h2 string
	if err := db.QueryRow(`SELECT headers FROM submission_log WHERE payload_hash = 'hash2'`).Scan(&h2); err != nil {
		t.Fatalf("read hash2: %v", err)
	}
	var m2 map[string]string
	if err := json.Unmarshal([]byte(h2), &m2); err != nil {
		t.Fatalf("hash2 not valid flat JSON: %v — raw: %s", err, h2)
	}
	if m2["User-Agent"] != "pkgstats/3.5.3" {
		t.Errorf("hash2 User-Agent = %q, want pkgstats/3.5.3", m2["User-Agent"])
	}
	for k := range headersToSkip {
		if _, ok := m2[k]; ok {
			t.Errorf("hash2: key %q should have been stripped", k)
		}
	}

	// Row 3: should be unchanged.
	var h3 string
	if err := db.QueryRow(`SELECT headers FROM submission_log WHERE payload_hash = 'hash3'`).Scan(&h3); err != nil {
		t.Fatalf("read hash3: %v", err)
	}
	if h3 != `{"User-Agent":"pkgstats/3.5.3","Accept":"application/json"}` {
		t.Errorf("already-clean row was modified: %s", h3)
	}
}

func TestMigrateHeaders_Idempotent(t *testing.T) {
	db, err := database.New(":memory:")
	if err != nil {
		t.Fatalf("failed to create test database: %v", err)
	}
	t.Cleanup(func() { _ = db.Close() })

	_, err = db.Exec(`
		INSERT INTO submission_log (month, timestamp, ip, headers, payload, payload_hash, country)
		VALUES (202607, 1000, '', '{"X-Foo":["bar"],"X-Real-Ip":["1.2.3.4"]}', '{}', 'h1', '')
	`)
	if err != nil {
		t.Fatalf("insert: %v", err)
	}

	ctx := context.Background()

	// First run: flattens and strips.
	fixed1, skipped1, err := migrateHeaders(ctx, db)
	if err != nil {
		t.Fatalf("first run: %v", err)
	}
	if fixed1 != 1 || skipped1 != 0 {
		t.Errorf("first run: fixed=%d skipped=%d, want 1/0", fixed1, skipped1)
	}

	// Second run: nothing left to do.
	fixed2, skipped2, err := migrateHeaders(ctx, db)
	if err != nil {
		t.Fatalf("second run: %v", err)
	}
	if fixed2 != 0 || skipped2 != 1 {
		t.Errorf("second run: fixed=%d skipped=%d, want 0/1", fixed2, skipped2)
	}
}
