package popularity

import (
	"context"
	"database/sql"
	"testing"

	"pkgstatsd/internal/database"
)

type testItem struct {
	ID         string  `json:"id"`
	Samples    int     `json:"samples"`
	Count      int     `json:"count"`
	Popularity float64 `json:"popularity"`
	StartMonth int     `json:"startMonth"`
	EndMonth   int     `json:"endMonth"`
}

type testList struct {
	Total  int        `json:"total"`
	Count  int        `json:"count"`
	Items  []testItem `json:"items"`
	Limit  int        `json:"limit"`
	Offset int        `json:"offset"`
	Query  *string    `json:"query"`
}

func newTestItem(identifier string, samples, count int, popularity float64, startMonth, endMonth int) testItem {
	return testItem{
		ID: identifier, Samples: samples, Count: count,
		Popularity: popularity, StartMonth: startMonth, EndMonth: endMonth,
	}
}

func newTestList(total, count int, items []testItem, limit, offset int, query *string) testList {
	return testList{
		Total: total, Count: count, Items: items,
		Limit: limit, Offset: offset, Query: query,
	}
}

func setupTestRepository(t *testing.T, cfg Config) (*Repository[testItem, testList], *sql.DB) {
	t.Helper()
	db, err := database.New(":memory:")
	if err != nil {
		t.Fatalf("create database: %v", err)
	}
	t.Cleanup(func() { _ = db.Close() })

	// Create a dummy table for testing
	_, err = db.Exec(`CREATE TABLE ` + cfg.Table + ` (
		` + cfg.Column + ` TEXT,
		month INTEGER,
		count INTEGER
	)`)
	if err != nil {
		t.Fatalf("create test table: %v", err)
	}

	repo := NewRepository(db, cfg, newTestItem, newTestList)
	return repo, db
}

func TestFindByIdentifier(t *testing.T) {
	repo, db := setupTestRepository(t, Config{Table: "test_entity", Column: "id"})

	_, _ = db.Exec(`INSERT INTO test_entity (id, month, count) VALUES ('a', 202501, 10), ('b', 202501, 90)`)

	item, err := repo.FindByIdentifier(context.Background(), "a", 202501, 202501)
	if err != nil {
		t.Fatalf("FindByIdentifier error: %v", err)
	}

	if item.ID != "a" {
		t.Errorf("expected ID a, got %s", item.ID)
	}
	if item.Count != 10 {
		t.Errorf("expected count 10, got %d", item.Count)
	}
	if item.Samples != 100 {
		t.Errorf("expected samples 100, got %d", item.Samples)
	}
	if item.Popularity != 10.0 {
		t.Errorf("expected popularity 10.0, got %f", item.Popularity)
	}
}

func TestFindAll(t *testing.T) {
	repo, db := setupTestRepository(t, Config{Table: "test_entity", Column: "id"})

	_, _ = db.Exec(`INSERT INTO test_entity (id, month, count) VALUES
		('a', 202501, 10),
		('b', 202501, 20),
		('c', 202501, 30)`)

	list, err := repo.FindAll(context.Background(), "", 202501, 202501, 2, 0)
	if err != nil {
		t.Fatalf("FindAll error: %v", err)
	}

	if list.Total != 3 {
		t.Errorf("expected total 3, got %d", list.Total)
	}
	if len(list.Items) != 2 {
		t.Errorf("expected 2 items, got %d", len(list.Items))
	}
	// Should be ordered by count DESC
	if list.Items[0].ID != "c" {
		t.Errorf("expected first item c, got %s", list.Items[0].ID)
	}
}

func TestFindAll_WithQuery(t *testing.T) {
	repo, db := setupTestRepository(t, Config{Table: "test_entity", Column: "id", QueryContains: false})

	_, _ = db.Exec(`INSERT INTO test_entity (id, month, count) VALUES ('foo', 202501, 10), ('bar', 202501, 20)`)

	list, err := repo.FindAll(context.Background(), "f", 202501, 202501, 10, 0)
	if err != nil {
		t.Fatalf("FindAll error: %v", err)
	}

	if list.Total != 1 {
		t.Errorf("expected total 1, got %d", list.Total)
	}
	if list.Items[0].ID != "foo" {
		t.Errorf("expected foo, got %s", list.Items[0].ID)
	}
}

func TestFindAll_WithQueryContains(t *testing.T) {
	repo, db := setupTestRepository(t, Config{Table: "test_entity", Column: "id", QueryContains: true})

	_, _ = db.Exec(`INSERT INTO test_entity (id, month, count) VALUES ('foobar', 202501, 10), ('baz', 202501, 20)`)

	list, err := repo.FindAll(context.Background(), "oba", 202501, 202501, 10, 0)
	if err != nil {
		t.Fatalf("FindAll error: %v", err)
	}

	if list.Total != 1 {
		t.Errorf("expected total 1, got %d", list.Total)
	}
	if list.Items[0].ID != "foobar" {
		t.Errorf("expected foobar, got %s", list.Items[0].ID)
	}
}

func TestFindSeries(t *testing.T) {
	repo, db := setupTestRepository(t, Config{Table: "test_entity", Column: "id"})

	_, _ = db.Exec(`INSERT INTO test_entity (id, month, count) VALUES ('a', 202501, 10), ('a', 202502, 20)`)

	list, err := repo.FindSeries(context.Background(), "a", 202501, 202502, 10, 0)
	if err != nil {
		t.Fatalf("FindSeries error: %v", err)
	}

	if list.Total != 2 {
		t.Errorf("expected total 2, got %d", list.Total)
	}
	if list.Items[0].StartMonth != 202501 {
		t.Errorf("expected first month 202501, got %d", list.Items[0].StartMonth)
	}
}

func TestCalculatePopularity(t *testing.T) {
	tests := []struct {
		count    int
		samples  int
		expected float64
	}{
		{0, 0, 0},
		{50, 100, 50},
		{1, 3, 33.33},
	}

	for _, tt := range tests {
		got := calculatePopularity(tt.count, tt.samples)
		if got != tt.expected {
			t.Errorf("calculatePopularity(%d, %d) = %f, want %f", tt.count, tt.samples, got, tt.expected)
		}
	}
}
