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
	if pkg.Popularity != 0 {
		t.Errorf("expected popularity 0, got %f", pkg.Popularity)
	}
}

func TestFindByName_WithData(t *testing.T) {
	repo := setupTestDB(t)

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

func TestFindByName_MultiMonth(t *testing.T) {
	repo := setupTestDB(t)

	_, err := repo.db.Exec(`
		INSERT INTO package (name, month, count) VALUES
		('pacman', 202501, 100),
		('pacman', 202502, 150),
		('glibc', 202501, 500),
		('glibc', 202502, 600)
	`)
	if err != nil {
		t.Fatalf("insert test data: %v", err)
	}

	pkg, err := repo.FindByName(context.Background(), "pacman", 202501, 202502)
	if err != nil {
		t.Fatalf("FindByName error: %v", err)
	}

	// Count should be sum across months
	if pkg.Count != 250 {
		t.Errorf("expected count 250, got %d", pkg.Count)
	}
	// Samples should be sum of max per month (500 + 600 = 1100)
	if pkg.Samples != 1100 {
		t.Errorf("expected samples 1100, got %d", pkg.Samples)
	}
	if pkg.StartMonth != 202501 {
		t.Errorf("expected startMonth 202501, got %d", pkg.StartMonth)
	}
	if pkg.EndMonth != 202502 {
		t.Errorf("expected endMonth 202502, got %d", pkg.EndMonth)
	}
}

func TestFindByName_NonExistentRetainsName(t *testing.T) {
	repo := setupTestDB(t)

	_, err := repo.db.Exec(`INSERT INTO package (name, month, count) VALUES ('glibc', 202501, 500)`)
	if err != nil {
		t.Fatalf("insert test data: %v", err)
	}

	pkg, err := repo.FindByName(context.Background(), "nonexistent", 202501, 202501)
	if err != nil {
		t.Fatalf("FindByName error: %v", err)
	}

	if pkg.Name != "nonexistent" {
		t.Errorf("expected name nonexistent, got %s", pkg.Name)
	}
	if pkg.Count != 0 {
		t.Errorf("expected count 0, got %d", pkg.Count)
	}
	// Samples should still reflect the month's data
	if pkg.Samples != 500 {
		t.Errorf("expected samples 500, got %d", pkg.Samples)
	}
	if pkg.Popularity != 0 {
		t.Errorf("expected popularity 0, got %f", pkg.Popularity)
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
	// Empty list should be non-nil (for JSON serialization as [])
	if list.PackagePopularities == nil {
		t.Error("expected non-nil empty slice, got nil")
	}
}

func TestFindAll_WithData(t *testing.T) {
	repo := setupTestDB(t)

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

func TestFindAll_MinPopularityFilter(t *testing.T) {
	repo := setupTestDB(t)

	_, err := repo.db.Exec(`
		INSERT INTO package (name, month, count) VALUES
		('popular', 202501, 16),
		('barely-popular', 202501, 15),
		('unpopular', 202501, 1)
	`)
	if err != nil {
		t.Fatalf("insert test data: %v", err)
	}

	list, err := repo.FindAll(context.Background(), "", 202501, 202501, 100, 0)
	if err != nil {
		t.Fatalf("FindAll error: %v", err)
	}

	// Only count >= 16 should be included
	if list.Total != 1 {
		t.Errorf("expected total 1 (only count >= 16), got %d", list.Total)
	}
	if len(list.PackagePopularities) != 1 {
		t.Errorf("expected 1 item, got %d", len(list.PackagePopularities))
	}
	if list.PackagePopularities[0].Name != "popular" {
		t.Errorf("expected popular, got %s", list.PackagePopularities[0].Name)
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

func TestFindAll_EmptyQueryReturnsAll(t *testing.T) {
	repo := setupTestDB(t)

	_, err := repo.db.Exec(`
		INSERT INTO package (name, month, count) VALUES
		('pacman', 202501, 100),
		('glibc', 202501, 500)
	`)
	if err != nil {
		t.Fatalf("insert test data: %v", err)
	}

	list, err := repo.FindAll(context.Background(), "", 202501, 202501, 100, 0)
	if err != nil {
		t.Fatalf("FindAll error: %v", err)
	}

	if list.Total != 2 {
		t.Errorf("expected total 2, got %d", list.Total)
	}
}

func TestFindAll_QueryIsPrefixMatch(t *testing.T) {
	repo := setupTestDB(t)

	_, err := repo.db.Exec(`
		INSERT INTO package (name, month, count) VALUES
		('php', 202501, 100),
		('php-fpm', 202501, 50),
		('xphp', 202501, 50)
	`)
	if err != nil {
		t.Fatalf("insert test data: %v", err)
	}

	list, err := repo.FindAll(context.Background(), "php", 202501, 202501, 100, 0)
	if err != nil {
		t.Fatalf("FindAll error: %v", err)
	}

	// Should match "php" and "php-fpm" but NOT "xphp" (prefix match, not substring)
	if list.Total != 2 {
		t.Errorf("expected total 2 (prefix match), got %d", list.Total)
	}
}

func TestFindAll_Pagination(t *testing.T) {
	repo := setupTestDB(t)

	_, err := repo.db.Exec(`
		INSERT INTO package (name, month, count) VALUES
		('a', 202501, 100),
		('b', 202501, 90),
		('c', 202501, 80),
		('d', 202501, 70),
		('e', 202501, 60)
	`)
	if err != nil {
		t.Fatalf("insert test data: %v", err)
	}

	// Limit 2, offset 0: first 2
	list, err := repo.FindAll(context.Background(), "", 202501, 202501, 2, 0)
	if err != nil {
		t.Fatalf("FindAll error: %v", err)
	}
	if list.Total != 5 {
		t.Errorf("expected total 5, got %d", list.Total)
	}
	if list.Count != 2 {
		t.Errorf("expected count 2, got %d", list.Count)
	}
	if list.PackagePopularities[0].Name != "a" {
		t.Errorf("expected first item a, got %s", list.PackagePopularities[0].Name)
	}

	// Limit 2, offset 2: next 2
	list, err = repo.FindAll(context.Background(), "", 202501, 202501, 2, 2)
	if err != nil {
		t.Fatalf("FindAll error: %v", err)
	}
	if list.Total != 5 {
		t.Errorf("expected total 5, got %d", list.Total)
	}
	if list.Count != 2 {
		t.Errorf("expected count 2, got %d", list.Count)
	}
	if list.PackagePopularities[0].Name != "c" {
		t.Errorf("expected first item c, got %s", list.PackagePopularities[0].Name)
	}

	// Limit 2, offset 4: last 1
	list, err = repo.FindAll(context.Background(), "", 202501, 202501, 2, 4)
	if err != nil {
		t.Fatalf("FindAll error: %v", err)
	}
	if list.Count != 1 {
		t.Errorf("expected count 1, got %d", list.Count)
	}

	// Offset beyond total: empty result
	list, err = repo.FindAll(context.Background(), "", 202501, 202501, 2, 100)
	if err != nil {
		t.Fatalf("FindAll error: %v", err)
	}
	if list.Count != 0 {
		t.Errorf("expected count 0, got %d", list.Count)
	}
	if list.Total != 5 {
		t.Errorf("total should still be 5, got %d", list.Total)
	}
}

func TestFindAll_MultiMonth(t *testing.T) {
	repo := setupTestDB(t)

	_, err := repo.db.Exec(`
		INSERT INTO package (name, month, count) VALUES
		('pacman', 202501, 100),
		('pacman', 202502, 200),
		('glibc', 202501, 500),
		('glibc', 202502, 600)
	`)
	if err != nil {
		t.Fatalf("insert test data: %v", err)
	}

	list, err := repo.FindAll(context.Background(), "", 202501, 202502, 100, 0)
	if err != nil {
		t.Fatalf("FindAll error: %v", err)
	}

	if list.Total != 2 {
		t.Errorf("expected total 2, got %d", list.Total)
	}
	// glibc (500+600=1100) should be first, pacman (100+200=300) second
	if list.PackagePopularities[0].Name != "glibc" {
		t.Errorf("expected first item glibc, got %s", list.PackagePopularities[0].Name)
	}
	if list.PackagePopularities[0].Count != 1100 {
		t.Errorf("expected count 1100, got %d", list.PackagePopularities[0].Count)
	}
	// Samples should be sum of monthly maximums (500 + 600 = 1100)
	if list.PackagePopularities[0].Samples != 1100 {
		t.Errorf("expected samples 1100, got %d", list.PackagePopularities[0].Samples)
	}
}

func TestFindAll_ResponseMetadata(t *testing.T) {
	repo := setupTestDB(t)

	query := "pac"
	list, err := repo.FindAll(context.Background(), query, 202501, 202501, 50, 10)
	if err != nil {
		t.Fatalf("FindAll error: %v", err)
	}

	if list.Limit != 50 {
		t.Errorf("expected limit 50, got %d", list.Limit)
	}
	if list.Offset != 10 {
		t.Errorf("expected offset 10, got %d", list.Offset)
	}
	if list.Query == nil {
		t.Fatal("expected non-nil query")
	}
	if *list.Query != "pac" {
		t.Errorf("expected query pac, got %s", *list.Query)
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
	// Each entry should have startMonth == endMonth
	if list.PackagePopularities[0].StartMonth != list.PackagePopularities[0].EndMonth {
		t.Error("series entries should have startMonth == endMonth")
	}
}

func TestFindSeriesByName_Pagination(t *testing.T) {
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

	list, err := repo.FindSeriesByName(context.Background(), "pacman", 202501, 202503, 1, 0)
	if err != nil {
		t.Fatalf("FindSeriesByName error: %v", err)
	}

	if list.Total != 3 {
		t.Errorf("expected total 3, got %d", list.Total)
	}
	if list.Count != 1 {
		t.Errorf("expected count 1 (limited), got %d", list.Count)
	}

	// Offset 1: second entry
	list, err = repo.FindSeriesByName(context.Background(), "pacman", 202501, 202503, 1, 1)
	if err != nil {
		t.Fatalf("FindSeriesByName error: %v", err)
	}
	if list.PackagePopularities[0].StartMonth != 202502 {
		t.Errorf("expected month 202502, got %d", list.PackagePopularities[0].StartMonth)
	}
}

func TestFindSeriesByName_NoQuery(t *testing.T) {
	repo := setupTestDB(t)

	list, err := repo.FindSeriesByName(context.Background(), "pacman", 202501, 202501, 100, 0)
	if err != nil {
		t.Fatalf("FindSeriesByName error: %v", err)
	}

	// Series endpoints should not include query in response
	if list.Query != nil {
		t.Errorf("expected nil query for series, got %v", list.Query)
	}
}

func TestFindSeriesByName_PerMonthSamples(t *testing.T) {
	repo := setupTestDB(t)

	_, err := repo.db.Exec(`
		INSERT INTO package (name, month, count) VALUES
		('pacman', 202501, 100),
		('pacman', 202502, 150),
		('glibc', 202501, 500),
		('glibc', 202502, 600)
	`)
	if err != nil {
		t.Fatalf("insert test data: %v", err)
	}

	list, err := repo.FindSeriesByName(context.Background(), "pacman", 202501, 202502, 100, 0)
	if err != nil {
		t.Fatalf("FindSeriesByName error: %v", err)
	}

	// Each month should have its own samples (max count for that month)
	if list.PackagePopularities[0].Samples != 500 {
		t.Errorf("expected month 202501 samples 500, got %d", list.PackagePopularities[0].Samples)
	}
	if list.PackagePopularities[1].Samples != 600 {
		t.Errorf("expected month 202502 samples 600, got %d", list.PackagePopularities[1].Samples)
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
		{90, 499, 18.04},
		{200, 100, 200},   // count > samples: no cap in calculatePopularity
		{1, 1000000, 0},   // very small ratio rounds to 0
		{999, 1000, 99.9}, // near 100%
	}

	for _, tt := range tests {
		got := calculatePopularity(tt.count, tt.samples)
		if got != tt.expected {
			t.Errorf("calculatePopularity(%d, %d) = %f, want %f", tt.count, tt.samples, got, tt.expected)
		}
	}
}
