package countries

import (
	"context"
	"testing"

	"pkgstats.archlinux.de/internal/database"
)

func TestSQLiteRepository(t *testing.T) {
	db, err := database.New(":memory:")
	if err != nil {
		t.Fatalf("failed to create database: %v", err)
	}
	defer db.Close()

	repo := NewSQLiteRepository(db)

	_, _ = db.Exec(`INSERT INTO country (code, month, count) VALUES ('DE', 202501, 100), ('US', 202501, 200)`)

	// Test FindByCode
	c, err := repo.FindByCode(context.Background(), "DE", 202501, 202501)
	if err != nil {
		t.Fatalf("FindByCode error: %v", err)
	}
	if c.Code != "DE" || c.Count != 100 {
		t.Errorf("unexpected country data: %+v", c)
	}

	// Test FindAll
	list, err := repo.FindAll(context.Background(), "", 202501, 202501, 10, 0)
	if err != nil {
		t.Fatalf("FindAll error: %v", err)
	}
	if list.Total != 2 {
		t.Errorf("expected 2 countries, got %d", list.Total)
	}

	// Test FindSeriesByCode
	series, err := repo.FindSeriesByCode(context.Background(), "DE", 202501, 202501, 10, 0)
	if err != nil {
		t.Fatalf("FindSeriesByCode error: %v", err)
	}
	if series.Total != 1 {
		t.Errorf("expected 1 series entry, got %d", series.Total)
	}
}
