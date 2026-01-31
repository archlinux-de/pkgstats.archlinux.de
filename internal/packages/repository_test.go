package packages

import (
	"context"
	"testing"

	"pkgstats.archlinux.de/internal/database"
)

func setupTestDB(t *testing.T) *SQLiteRepository {
	t.Helper()
	db, err := database.New(":memory:")
	if err != nil {
		t.Fatalf("create database: %v", err)
	}
	t.Cleanup(func() { _ = db.Close() })
	return NewSQLiteRepository(db)
}

func TestFindByName_Empty(t *testing.T) {
	repo := setupTestDB(t)

	pkg, err := repo.FindByName(context.Background(), "pacman", 202501, 202501)
	if err != nil {
		t.Fatalf("FindByName error: %v", err)
	}

	if pkg.Name != "pacman" {
		t.Errorf("expected name pacman, got %s", pkg.Name)
	}
	if pkg.Count != 0 {
		t.Errorf("expected count 0, got %d", pkg.Count)
	}
	if pkg.Samples != 0 {
		t.Errorf("expected samples 0, got %d", pkg.Samples)
	}
}

func TestFindByName_WithData(t *testing.T) {
	repo := setupTestDB(t)

	// Insert test data
	_, err := repo.db.Exec(`
		INSERT INTO package (name, month, count) VALUES
		('pacman', 202501, 100),
		('glibc', 202501, 500)
	`)
	if err != nil {
		t.Fatalf("insert test data: %v", err)
	}

	pkg, err := repo.FindByName(context.Background(), "pacman", 202501, 202501)
	if err != nil {
		t.Fatalf("FindByName error: %v", err)
	}

	if pkg.Count != 100 {
		t.Errorf("expected count 100, got %d", pkg.Count)
	}
	if pkg.Samples != 500 {
		t.Errorf("expected samples 500, got %d", pkg.Samples)
	}
	if pkg.Popularity != 20 {
		t.Errorf("expected popularity 20, got %f", pkg.Popularity)
	}
}

func TestFindAll_Empty(t *testing.T) {
	repo := setupTestDB(t)

	list, err := repo.FindAll(context.Background(), "", 202501, 202501, 100, 0)
	if err != nil {
		t.Fatalf("FindAll error: %v", err)
	}

	if list.Total != 0 {
		t.Errorf("expected total 0, got %d", list.Total)
	}
	if len(list.PackagePopularities) != 0 {
		t.Errorf("expected empty list, got %d items", len(list.PackagePopularities))
	}
}

func TestFindAll_WithData(t *testing.T) {
	repo := setupTestDB(t)

	// Insert test data (need count >= minPopularity)
	_, err := repo.db.Exec(`
		INSERT INTO package (name, month, count) VALUES
		('pacman', 202501, 100),
		('glibc', 202501, 500),
		('unpopular', 202501, 5)
	`)
	if err != nil {
		t.Fatalf("insert test data: %v", err)
	}

	list, err := repo.FindAll(context.Background(), "", 202501, 202501, 100, 0)
	if err != nil {
		t.Fatalf("FindAll error: %v", err)
	}

	if list.Total != 2 {
		t.Errorf("expected total 2 (unpopular filtered), got %d", list.Total)
	}
	if len(list.PackagePopularities) != 2 {
		t.Errorf("expected 2 items, got %d", len(list.PackagePopularities))
	}
	// Should be ordered by count DESC
	if list.PackagePopularities[0].Name != "glibc" {
		t.Errorf("expected first item glibc, got %s", list.PackagePopularities[0].Name)
	}
}

func TestFindAll_WithQuery(t *testing.T) {
	repo := setupTestDB(t)

	_, err := repo.db.Exec(`
		INSERT INTO package (name, month, count) VALUES
		('pacman', 202501, 100),
		('pacman-contrib', 202501, 50),
		('glibc', 202501, 500)
	`)
	if err != nil {
		t.Fatalf("insert test data: %v", err)
	}

	list, err := repo.FindAll(context.Background(), "pacman", 202501, 202501, 100, 0)
	if err != nil {
		t.Fatalf("FindAll error: %v", err)
	}

	if list.Total != 2 {
		t.Errorf("expected total 2, got %d", list.Total)
	}
}

func TestFindSeriesByName(t *testing.T) {
	repo := setupTestDB(t)

	_, err := repo.db.Exec(`
		INSERT INTO package (name, month, count) VALUES
		('pacman', 202501, 100),
		('pacman', 202502, 150),
		('pacman', 202503, 200)
	`)
	if err != nil {
		t.Fatalf("insert test data: %v", err)
	}

	list, err := repo.FindSeriesByName(context.Background(), "pacman", 202501, 202503, 100, 0)
	if err != nil {
		t.Fatalf("FindSeriesByName error: %v", err)
	}

	if list.Total != 3 {
		t.Errorf("expected total 3, got %d", list.Total)
	}
	if len(list.PackagePopularities) != 3 {
		t.Errorf("expected 3 items, got %d", len(list.PackagePopularities))
	}
	// Should be ordered by month ASC
	if list.PackagePopularities[0].StartMonth != 202501 {
		t.Errorf("expected first month 202501, got %d", list.PackagePopularities[0].StartMonth)
	}
}

func TestCalculatePopularity(t *testing.T) {
	tests := []struct {
		count    int
		samples  int
		expected float64
	}{
		{0, 0, 0},
		{0, 100, 0},
		{100, 100, 100},
		{50, 100, 50},
		{1, 3, 33.33},
		{90, 499, 18.04}, // Match PHP fixture data
	}

	for _, tt := range tests {
		got := calculatePopularity(tt.count, tt.samples)
		if got != tt.expected {
			t.Errorf("calculatePopularity(%d, %d) = %f, want %f", tt.count, tt.samples, got, tt.expected)
		}
	}
}
