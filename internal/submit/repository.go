package submit

import (
	"context"
	"database/sql"
	"fmt"
	"time"
)

const (
	monthMultiplier     = 100
	deduplicationWindow = 2 * time.Hour
)

type Repository struct {
	db  *sql.DB
	now func() time.Time
}

func NewRepository(db *sql.DB) *Repository {
	return &Repository{
		db:  db,
		now: time.Now,
	}
}

// SaveSubmission records a submission unless an identical recent request from
// the same client address has already been accepted. It reports whether the
// submission contributed to the aggregate tables.
func (r *Repository) SaveSubmission(ctx context.Context, req *Request, mirrorURL string, logEntry *LogEntry) (bool, error) {
	now := r.now()
	month := yearMonth(now)

	tx, err := r.db.BeginTx(ctx, nil)
	if err != nil {
		return false, fmt.Errorf("begin transaction: %w", err)
	}
	defer func() { _ = tx.Rollback() }()

	accepted, err := r.claimSubmissionFingerprint(ctx, tx, logEntry.Fingerprint, now)
	if err != nil {
		return false, fmt.Errorf("claim submission fingerprint: %w", err)
	}
	if !accepted {
		if err := tx.Commit(); err != nil {
			return false, fmt.Errorf("commit duplicate submission: %w", err)
		}
		return false, nil
	}

	packages := req.DeduplicatePackages()
	if err := r.savePackages(ctx, tx, packages, month); err != nil {
		return false, fmt.Errorf("save packages: %w", err)
	}

	if req.Country != "" {
		if err := r.upsertCountry(ctx, tx, req.Country, month); err != nil {
			return false, fmt.Errorf("save country: %w", err)
		}
	}

	if mirrorURL != "" {
		if err := r.upsertMirror(ctx, tx, mirrorURL, month); err != nil {
			return false, fmt.Errorf("save mirror: %w", err)
		}
	}

	if err := r.upsertSystemArchitecture(ctx, tx, req.System.Architecture, month); err != nil {
		return false, fmt.Errorf("save system architecture: %w", err)
	}

	if err := r.upsertOSArchitecture(ctx, tx, req.OS.Architecture, month); err != nil {
		return false, fmt.Errorf("save OS architecture: %w", err)
	}

	if req.OS.ID != "" {
		if err := r.upsertOperatingSystemId(ctx, tx, req.OS.ID, month); err != nil {
			return false, fmt.Errorf("save OS ID: %w", err)
		}
	}

	// Logged in the same transaction so the log contains exactly the
	// submissions that were counted.
	if err := r.insertLogEntry(ctx, tx, logEntry, month, now.Unix()); err != nil {
		return false, fmt.Errorf("save submission log: %w", err)
	}

	if err := tx.Commit(); err != nil {
		return false, fmt.Errorf("commit transaction: %w", err)
	}

	return true, nil
}

func (r *Repository) claimSubmissionFingerprint(ctx context.Context, tx *sql.Tx, fingerprint []byte, now time.Time) (bool, error) {
	if len(fingerprint) == 0 {
		return true, nil
	}

	result, err := tx.ExecContext(ctx,
		`INSERT INTO submission_dedup (fingerprint, expires_at) VALUES (?, ?)
		 ON CONFLICT(fingerprint) DO UPDATE SET expires_at = excluded.expires_at
		 WHERE submission_dedup.expires_at < ?`,
		fingerprint, now.Add(deduplicationWindow).Unix(), now.Unix(),
	)
	if err != nil {
		return false, err
	}

	updated, err := result.RowsAffected()
	if err != nil {
		return false, err
	}
	return updated == 1, nil
}

func (r *Repository) insertLogEntry(ctx context.Context, tx *sql.Tx, entry *LogEntry, month int, timestamp int64) error {
	//nolint:gosec // Query uses a hardcoded string and parameterized inputs
	_, err := tx.ExecContext(ctx,
		`INSERT INTO submission_log (month, timestamp, ip, headers, payload, payload_hash, country)
		 VALUES (?, ?, ?, ?, ?, ?, ?)`,
		month, timestamp, entry.IP, entry.Headers,
		entry.Payload, entry.PayloadHash, entry.Country)
	return err
}

// retentionMonths is the number of previous calendar months kept in the
// submission log in addition to the current one. Older entries are pruned
// to limit how long client IPs and headers are retained.
const retentionMonths = 2

// retentionCutoff returns the oldest month still kept in the submission log
// (inclusive): the current month and the retentionMonths preceding ones.
// Older months are pruned.
func retentionCutoff(now time.Time) int {
	return yearMonth(time.Date(now.Year(), now.Month()-retentionMonths, 1, 0, 0, 0, 0, now.Location()))
}

// PruneLog deletes expired submission logs and deduplication fingerprints. It
// returns the number of log entries removed and runs as scheduled maintenance.
func (r *Repository) PruneLog(ctx context.Context) (int64, error) {
	result, err := r.db.ExecContext(ctx,
		`DELETE FROM submission_log WHERE month < ?`, retentionCutoff(r.now()))
	if err != nil {
		return 0, fmt.Errorf("prune submission log: %w", err)
	}
	if _, err := r.db.ExecContext(ctx,
		`DELETE FROM submission_dedup WHERE expires_at < ?`, r.now().Unix()); err != nil {
		return 0, fmt.Errorf("prune submission deduplication: %w", err)
	}
	return result.RowsAffected()
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

func yearMonth(t time.Time) int {
	return t.Year()*monthMultiplier + int(t.Month())
}
