package database

import (
	"os"
	"path/filepath"
	"testing"
)

func TestNew(t *testing.T) {
	// Create temp directory for test database
	tmpDir := t.TempDir()
	dbPath := filepath.Join(tmpDir, "test.db")

	db, err := New(dbPath)
	if err != nil {
		t.Fatalf("New() error = %v", err)
	}
	defer func() { _ = db.Close() }()

	// Verify database file was created
	if _, err := os.Stat(dbPath); os.IsNotExist(err) {
		t.Error("database file was not created")
	}

	// Verify tables exist
	tables := []string{
		"package",
		"country",
		"mirror",
		"system_architecture",
		"operating_system_architecture",
		"rate_limit",
	}

	for _, table := range tables {
		var name string
		err := db.QueryRow(
			"SELECT name FROM sqlite_master WHERE type='table' AND name=?",
			table,
		).Scan(&name)
		if err != nil {
			t.Errorf("table %q not found: %v", table, err)
		}
	}
}

func TestNew_InMemory(t *testing.T) {
	db, err := New(":memory:")
	if err != nil {
		t.Fatalf("New() error = %v", err)
	}
	defer func() { _ = db.Close() }()

	// Verify we can insert and query data
	_, err = db.Exec("INSERT INTO package (name, month, count) VALUES (?, ?, ?)", "pacman", 202501, 100)
	if err != nil {
		t.Fatalf("insert error = %v", err)
	}

	var count int
	err = db.QueryRow("SELECT count FROM package WHERE name = ?", "pacman").Scan(&count)
	if err != nil {
		t.Fatalf("query error = %v", err)
	}

	if count != 100 {
		t.Errorf("expected count 100, got %d", count)
	}
}

func TestNew_MigrationsIdempotent(t *testing.T) {
	tmpDir := t.TempDir()
	dbPath := filepath.Join(tmpDir, "test.db")

	// Run migrations first time
	db1, err := New(dbPath)
	if err != nil {
		t.Fatalf("first New() error = %v", err)
	}
	_ = db1.Close()

	// Run migrations second time (should be idempotent)
	db2, err := New(dbPath)
	if err != nil {
		t.Fatalf("second New() error = %v", err)
	}
	_ = db2.Close()
}
