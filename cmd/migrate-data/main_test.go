package main

import (
	"context"
	"database/sql"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"testing"

	_ "modernc.org/sqlite"

	"pkgstats.archlinux.de/internal/database"
)

type tableSpec struct {
	name    string
	columns string
}

var tables = []tableSpec{
	{"package", "name, month, count"},
	{"country", "code, month, count"},
	{"mirror", "url, month, count"},
	{"system_architecture", "name, month, count"},
	{"operating_system_architecture", "name, month, count"},
	{"operating_system_id", "id, month, count"},
}

// setupSourceDB creates an in-memory SQLite database with the same table
// schemas as the MariaDB source, to be used as a fake source in tests.
func setupSourceDB(t *testing.T) *sql.DB {
	t.Helper()

	db, err := sql.Open("sqlite", ":memory:")
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = db.Close() })

	ddl := []string{
		"CREATE TABLE package (name TEXT NOT NULL, month INTEGER NOT NULL, count INTEGER NOT NULL, PRIMARY KEY (name, month))",
		"CREATE TABLE country (code TEXT NOT NULL, month INTEGER NOT NULL, count INTEGER NOT NULL, PRIMARY KEY (code, month))",
		"CREATE TABLE mirror (url TEXT NOT NULL, month INTEGER NOT NULL, count INTEGER NOT NULL, PRIMARY KEY (url, month))",
		"CREATE TABLE system_architecture (name TEXT NOT NULL, month INTEGER NOT NULL, count INTEGER NOT NULL, PRIMARY KEY (name, month))",
		"CREATE TABLE operating_system_architecture (name TEXT NOT NULL, month INTEGER NOT NULL, count INTEGER NOT NULL, PRIMARY KEY (name, month))",
		"CREATE TABLE operating_system_id (id TEXT NOT NULL, month INTEGER NOT NULL, count INTEGER NOT NULL, PRIMARY KEY (id, month))",
	}

	for _, stmt := range ddl {
		if _, err := db.Exec(stmt); err != nil {
			t.Fatalf("create table: %v", err)
		}
	}

	return db
}

// setupDestDB creates a SQLite destination database in a temp file using
// database.New, which runs the real migrations.
func setupDestDB(t *testing.T) *sql.DB {
	t.Helper()

	path := filepath.Join(t.TempDir(), "dest.db")

	db, err := database.New(path)
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = db.Close() })

	return db
}

func TestMigrateTable(t *testing.T) {
	t.Parallel()

	for _, table := range tables {
		t.Run(table.name, func(t *testing.T) {
			t.Parallel()

			src := setupSourceDB(t)
			dst := setupDestDB(t)
			ctx := context.Background()

			// Insert sample rows into source
			for i := range 5 {
				_, err := src.Exec(
					fmt.Sprintf("INSERT INTO %s VALUES (?, ?, ?)", table.name),
					fmt.Sprintf("value-%d", i), 202601+i, (i+1)*10,
				)
				if err != nil {
					t.Fatal(err)
				}
			}

			if err := migrateTable(ctx, src, dst, table.name, table.columns); err != nil {
				t.Fatalf("migrateTable: %v", err)
			}

			// Verify row count
			var count int
			if err := dst.QueryRow("SELECT COUNT(*) FROM " + table.name).Scan(&count); err != nil {
				t.Fatal(err)
			}
			if count != 5 {
				t.Errorf("got %d rows, want 5", count)
			}

			// Verify data matches
			rows, err := dst.Query(fmt.Sprintf("SELECT %s FROM %s ORDER BY month", table.columns, table.name))
			if err != nil {
				t.Fatal(err)
			}
			defer func() { _ = rows.Close() }()

			var i int
			for rows.Next() {
				var col1 string
				var col2, col3 int
				if err := rows.Scan(&col1, &col2, &col3); err != nil {
					t.Fatal(err)
				}

				wantCol1 := fmt.Sprintf("value-%d", i)
				wantCol2 := 202601 + i
				wantCol3 := (i + 1) * 10

				if col1 != wantCol1 || col2 != wantCol2 || col3 != wantCol3 {
					t.Errorf("row %d = (%q, %d, %d), want (%q, %d, %d)",
						i, col1, col2, col3, wantCol1, wantCol2, wantCol3)
				}
				i++
			}

			if err := rows.Err(); err != nil {
				t.Fatal(err)
			}
		})
	}
}

func TestMigrateTable_EmptyTable(t *testing.T) {
	t.Parallel()

	src := setupSourceDB(t)
	dst := setupDestDB(t)

	if err := migrateTable(context.Background(), src, dst, "package", "name, month, count"); err != nil {
		t.Fatalf("migrateTable on empty table: %v", err)
	}

	var count int
	if err := dst.QueryRow("SELECT COUNT(*) FROM package").Scan(&count); err != nil {
		t.Fatal(err)
	}
	if count != 0 {
		t.Errorf("got %d rows, want 0", count)
	}
}

func TestMigrateTable_LargeDataset(t *testing.T) {
	t.Parallel()

	src := setupSourceDB(t)
	dst := setupDestDB(t)
	ctx := context.Background()

	const numRows = 1500

	tx, err := src.Begin()
	if err != nil {
		t.Fatal(err)
	}
	for i := range numRows {
		if _, err := tx.Exec(
			"INSERT INTO package VALUES (?, ?, ?)",
			fmt.Sprintf("pkg-%04d", i), 202601, i+1,
		); err != nil {
			t.Fatal(err)
		}
	}
	if err := tx.Commit(); err != nil {
		t.Fatal(err)
	}

	if err := migrateTable(ctx, src, dst, "package", "name, month, count"); err != nil {
		t.Fatalf("migrateTable: %v", err)
	}

	var count int
	if err := dst.QueryRow("SELECT COUNT(*) FROM package").Scan(&count); err != nil {
		t.Fatal(err)
	}
	if count != numRows {
		t.Errorf("got %d rows, want %d", count, numRows)
	}
}

func TestVerifyCount_Match(t *testing.T) {
	t.Parallel()

	src := setupSourceDB(t)
	dst := setupDestDB(t)
	ctx := context.Background()

	// Insert same number of rows into both
	for i := range 3 {
		val := fmt.Sprintf("v%d", i)
		if _, err := src.Exec("INSERT INTO package VALUES (?, ?, ?)", val, 202601, i); err != nil {
			t.Fatal(err)
		}
		if _, err := dst.Exec("INSERT INTO package VALUES (?, ?, ?)", val, 202601, i); err != nil {
			t.Fatal(err)
		}
	}

	if err := verifyCount(ctx, src, dst, "package"); err != nil {
		t.Errorf("verifyCount should pass: %v", err)
	}
}

func TestVerifyCount_Mismatch(t *testing.T) {
	t.Parallel()

	src := setupSourceDB(t)
	dst := setupDestDB(t)
	ctx := context.Background()

	// Insert 2 rows into source, 1 into destination
	for i := range 2 {
		if _, err := src.Exec("INSERT INTO package VALUES (?, ?, ?)", fmt.Sprintf("v%d", i), 202601, i); err != nil {
			t.Fatal(err)
		}
	}
	if _, err := dst.Exec("INSERT INTO package VALUES (?, ?, ?)", "v0", 202601, 0); err != nil {
		t.Fatal(err)
	}

	err := verifyCount(ctx, src, dst, "package")
	if err == nil {
		t.Fatal("expected error, got nil")
	}

	want := "count mismatch"
	if got := err.Error(); !strings.Contains(got, want) {
		t.Errorf("error %q does not contain %q", got, want)
	}
}

func TestRun_FailsIfFileExists(t *testing.T) {
	t.Parallel()

	path := filepath.Join(t.TempDir(), "existing.db")
	if err := os.WriteFile(path, []byte("data"), 0o600); err != nil {
		t.Fatal(err)
	}

	err := run("unused-dsn", path)
	if err == nil {
		t.Fatal("expected error, got nil")
	}

	want := "already exists"
	if got := err.Error(); !strings.Contains(got, want) {
		t.Errorf("error %q does not contain %q", got, want)
	}
}
