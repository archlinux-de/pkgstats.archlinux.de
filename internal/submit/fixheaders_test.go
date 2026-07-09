package submit

import (
	"context"
	"encoding/json"
	"testing"

	"pkgstatsd/internal/database"
)

func TestFlattenHeaders(t *testing.T) {
	tests := []struct {
		name          string
		input         string
		wantMigration bool
		wantErr       bool
		wantFlat      map[string]string
	}{
		{
			name:          "old array format single value",
			input:         `{"User-Agent":["Mozilla/5.0"]}`,
			wantMigration: true,
			wantFlat:      map[string]string{"User-Agent": "Mozilla/5.0"},
		},
		{
			name:          "old array format multi value",
			input:         `{"Accept":["text/html","application/json"]}`,
			wantMigration: true,
			wantFlat:      map[string]string{"Accept": "text/html, application/json"},
		},
		{
			name:          "old array format multiple headers",
			input:         `{"User-Agent":["Mozilla/5.0"],"Accept":["text/html","application/xhtml+xml"]}`,
			wantMigration: true,
			wantFlat: map[string]string{
				"User-Agent": "Mozilla/5.0",
				"Accept":     "text/html, application/xhtml+xml",
			},
		},
		{
			name:          "already flat string format",
			input:         `{"User-Agent":"Mozilla/5.0","Accept":"text/html"}`,
			wantMigration: false,
		},
		{
			name:          "empty object stays flat",
			input:         `{}`,
			wantMigration: false, // "{}" is treated as already-flat (no headers)
		},
		{
			name:          "old format with empty array value",
			input:         `{"X-Empty":[]}`,
			wantMigration: true,
			wantFlat:      map[string]string{"X-Empty": ""},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, needsMigration, err := flattenHeaders(tt.input)

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
		})
	}
}

func TestMigrateHeaders(t *testing.T) {
	db, err := database.New(":memory:")
	if err != nil {
		t.Fatalf("failed to create test database: %v", err)
	}
	t.Cleanup(func() { _ = db.Close() })

	// Insert one row in the old array format and one already in the flat format.
	_, err = db.Exec(`
		INSERT INTO submission_log (month, timestamp, ip, headers, payload, payload_hash, country)
		VALUES
			(202607, 1000, '1.2.3.4', '{"User-Agent":["curl/7.0"],"Accept":["*/*"]}',  '{}', 'hash1', 'DE'),
			(202607, 1001, '5.6.7.8', '{"User-Agent":"curl/7.0","Accept":"*/*"}',       '{}', 'hash2', 'US')
	`)
	if err != nil {
		t.Fatalf("failed to insert test rows: %v", err)
	}

	ctx := context.Background()
	fixed, skipped, err := migrateHeaders(ctx, db)
	if err != nil {
		t.Fatalf("migrateHeaders returned error: %v", err)
	}

	if fixed != 1 {
		t.Errorf("fixed = %d, want 1", fixed)
	}
	if skipped != 1 {
		t.Errorf("skipped = %d, want 1", skipped)
	}

	// Verify the migrated row now has the flat format.
	var headers string
	if err := db.QueryRow(`SELECT headers FROM submission_log WHERE payload_hash = 'hash1'`).Scan(&headers); err != nil {
		t.Fatalf("failed to read migrated row: %v", err)
	}
	var got map[string]string
	if err := json.Unmarshal([]byte(headers), &got); err != nil {
		t.Fatalf("migrated headers are not valid flat JSON: %v — raw: %s", err, headers)
	}
	if got["User-Agent"] != "curl/7.0" {
		t.Errorf(`User-Agent = %q, want "curl/7.0"`, got["User-Agent"])
	}
	if got["Accept"] != "*/*" {
		t.Errorf(`Accept = %q, want "*/*"`, got["Accept"])
	}

	// Verify the already-flat row was not modified.
	var headers2 string
	if err := db.QueryRow(`SELECT headers FROM submission_log WHERE payload_hash = 'hash2'`).Scan(&headers2); err != nil {
		t.Fatalf("failed to read untouched row: %v", err)
	}
	if headers2 != `{"User-Agent":"curl/7.0","Accept":"*/*"}` {
		t.Errorf("already-flat row was modified: %s", headers2)
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
		VALUES (202607, 1000, '', '{"X-Foo":["bar"]}', '{}', 'h1', '')
	`)
	if err != nil {
		t.Fatalf("insert: %v", err)
	}

	ctx := context.Background()

	// First run: migrates 1 row.
	fixed1, skipped1, err := migrateHeaders(ctx, db)
	if err != nil {
		t.Fatalf("first run: %v", err)
	}
	if fixed1 != 1 || skipped1 != 0 {
		t.Errorf("first run: fixed=%d skipped=%d, want 1/0", fixed1, skipped1)
	}

	// Second run: the row is now flat, should be skipped.
	fixed2, skipped2, err := migrateHeaders(ctx, db)
	if err != nil {
		t.Fatalf("second run: %v", err)
	}
	if fixed2 != 0 || skipped2 != 1 {
		t.Errorf("second run: fixed=%d skipped=%d, want 0/1", fixed2, skipped2)
	}
}
