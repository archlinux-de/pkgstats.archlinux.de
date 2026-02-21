package submit

import (
	"context"
	"database/sql"
	"fmt"
	"time"
)

const monthMultiplier = 100

// Repository handles persistence of submission data.
type Repository struct {
	db  *sql.DB
	now func() time.Time
}

// NewRepository creates a new Repository.
func NewRepository(db *sql.DB) *Repository {
	return &Repository{
		db:  db,
		now: time.Now,
	}
}

// SaveSubmission persists all data from a submission in a single transaction.
func (r *Repository) SaveSubmission(ctx context.Context, req *Request, mirrorURL string) error {
	month := r.currentMonth()

	tx, err := r.db.BeginTx(ctx, nil)
	if err != nil {
		return fmt.Errorf("begin transaction: %w", err)
	}
	defer func() { _ = tx.Rollback() }()

	// Save packages
	packages := req.DeduplicatePackages()
	if err := r.savePackages(ctx, tx, packages, month); err != nil {
		return fmt.Errorf("save packages: %w", err)
	}

	// Save country (if available)
	if req.Country != "" {
		if err := r.upsertCountry(ctx, tx, req.Country, month); err != nil {
			return fmt.Errorf("save country: %w", err)
		}
	}

	// Save mirror (if available)
	if mirrorURL != "" {
		if err := r.upsertMirror(ctx, tx, mirrorURL, month); err != nil {
			return fmt.Errorf("save mirror: %w", err)
		}
	}

	// Save system architecture
	if err := r.upsertSystemArchitecture(ctx, tx, req.System.Architecture, month); err != nil {
		return fmt.Errorf("save system architecture: %w", err)
	}

	// Save operating system architecture
	if err := r.upsertOSArchitecture(ctx, tx, req.OS.Architecture, month); err != nil {
		return fmt.Errorf("save OS architecture: %w", err)
	}

	// Save operating system ID (if available)
	if req.OS.ID != "" {
		if err := r.upsertOperatingSystemId(ctx, tx, req.OS.ID, month); err != nil {
			return fmt.Errorf("save OS ID: %w", err)
		}
	}

	if err := tx.Commit(); err != nil {
		return fmt.Errorf("commit transaction: %w", err)
	}

	return nil
}

func (r *Repository) savePackages(ctx context.Context, tx *sql.Tx, packages []string, month int) error {
	//nolint:gosec // Query uses a hardcoded string and parameterized inputs
	stmt, err := tx.PrepareContext(ctx,
		`INSERT INTO package (name, month, count) VALUES (?, ?, 1)
		 ON CONFLICT(name, month) DO UPDATE SET count = count + 1`)
	if err != nil {
		return err
	}
	defer func() { _ = stmt.Close() }()

	for _, pkg := range packages {
		if _, err := stmt.ExecContext(ctx, pkg, month); err != nil {
			return fmt.Errorf("insert package %s: %w", pkg, err)
		}
	}

	return nil
}

func (r *Repository) upsertCountry(ctx context.Context, tx *sql.Tx, code string, month int) error {
	//nolint:gosec // Query uses a hardcoded string and parameterized inputs
	_, err := tx.ExecContext(ctx,
		`INSERT INTO country (code, month, count) VALUES (?, ?, 1)
		 ON CONFLICT(code, month) DO UPDATE SET count = count + 1`,
		code, month)
	return err
}

func (r *Repository) upsertMirror(ctx context.Context, tx *sql.Tx, url string, month int) error {
	//nolint:gosec // Query uses a hardcoded string and parameterized inputs
	_, err := tx.ExecContext(ctx,
		`INSERT INTO mirror (url, month, count) VALUES (?, ?, 1)
		 ON CONFLICT(url, month) DO UPDATE SET count = count + 1`,
		url, month)
	return err
}

func (r *Repository) upsertSystemArchitecture(ctx context.Context, tx *sql.Tx, name string, month int) error {
	//nolint:gosec // Query uses a hardcoded string and parameterized inputs
	_, err := tx.ExecContext(ctx,
		`INSERT INTO system_architecture (name, month, count) VALUES (?, ?, 1)
		 ON CONFLICT(name, month) DO UPDATE SET count = count + 1`,
		name, month)
	return err
}

func (r *Repository) upsertOSArchitecture(ctx context.Context, tx *sql.Tx, name string, month int) error {
	//nolint:gosec // Query uses a hardcoded string and parameterized inputs
	_, err := tx.ExecContext(ctx,
		`INSERT INTO operating_system_architecture (name, month, count) VALUES (?, ?, 1)
		 ON CONFLICT(name, month) DO UPDATE SET count = count + 1`,
		name, month)
	return err
}

func (r *Repository) upsertOperatingSystemId(ctx context.Context, tx *sql.Tx, id string, month int) error {
	//nolint:gosec // Query uses a hardcoded string and parameterized inputs
	_, err := tx.ExecContext(ctx,
		`INSERT INTO operating_system_id (id, month, count) VALUES (?, ?, 1)
		 ON CONFLICT(id, month) DO UPDATE SET count = count + 1`,
		id, month)
	return err
}

func (r *Repository) currentMonth() int {
	now := r.now()
	return now.Year()*monthMultiplier + int(now.Month())
}
