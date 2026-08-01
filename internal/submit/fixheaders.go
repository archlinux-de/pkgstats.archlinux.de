package submit

import (
	"context"
	"database/sql"
	"encoding/json"
	"fmt"
	"os"
	"strings"

	"pkgstatsd/internal/config"
	"pkgstatsd/internal/database"
)

// cleanupHeaders normalises a single headers JSON value from the submission
// log. It performs two transformations in one pass:
//
//  1. Flatten: if the value is in the old map[string][]string format (written
//     before the marshalHeaders fix), join each slice into a comma-separated
//     string to produce map[string]string.
//
//  2. Strip: remove any keys in headersToSkip (X-Real-Ip, X-Forwarded-Proto)
//     that are redundant in the log.
//
// Returns (nil, false, nil) when the value already needs no changes.
func cleanupHeaders(headersJSON string) ([]byte, bool, error) {
	// An empty object can unmarshal as either format; nothing to strip either.
	if headersJSON == "{}" {
		return nil, false, nil
	}

	wasOldFormat := false
	var flat map[string]string

	// Try the old array format first.
	var oldHeaders map[string][]string
	if err := json.Unmarshal([]byte(headersJSON), &oldHeaders); err == nil {
		wasOldFormat = true
		flat = make(map[string]string, len(oldHeaders))
		for k, vv := range oldHeaders {
			flat[k] = strings.Join(vv, ", ")
		}
	} else {
		// Already the flat string format.
		if err := json.Unmarshal([]byte(headersJSON), &flat); err != nil {
			return nil, false, fmt.Errorf("unrecognised headers format: %w", err)
		}
	}

	// Strip nginx-injected headers that are redundant in the log,
	// reusing the same exclusion set as marshalHeaders in log.go.
	strippedAny := false
	for k := range headersToSkip {
		if _, ok := flat[k]; ok {
			delete(flat, k)
			strippedAny = true
		}
	}

	if !wasOldFormat && !strippedAny {
		return nil, false, nil
	}

	out, err := json.Marshal(flat)
	if err != nil {
		return nil, true, fmt.Errorf("marshal cleaned headers: %w", err)
	}
	return out, true, nil
}

type pendingUpdate struct {
	id      int64
	cleaned string
}

// migrateHeaders reads all rows from submission_log and rewrites any that need
// normalisation (old array format or redundant nginx headers). It collects all
// rows before writing so the read cursor is closed before any updates are
// issued, which is required by SQLite. Returns the number of rows updated and
// the number skipped (already clean).
func migrateHeaders(ctx context.Context, db *sql.DB) (fixed, skipped int, err error) {
	// Phase 1: collect all rows that need updating.
	var pending []pendingUpdate

	rows, err := db.QueryContext(ctx, `SELECT id, headers FROM submission_log`)
	if err != nil {
		return 0, 0, fmt.Errorf("query submission_log: %w", err)
	}

	for rows.Next() {
		var (
			id          int64
			headersJSON string
		)
		if err := rows.Scan(&id, &headersJSON); err != nil {
			_ = rows.Close()
			return 0, 0, fmt.Errorf("scan row %d: %w", id, err)
		}

		cleaned, needsUpdate, ferr := cleanupHeaders(headersJSON)
		if ferr != nil {
			_ = rows.Close()
			return 0, 0, fmt.Errorf("row %d: %w", id, ferr)
		}
		if !needsUpdate {
			skipped++
			continue
		}
		pending = append(pending, pendingUpdate{id: id, cleaned: string(cleaned)})
	}

	if err := rows.Close(); err != nil {
		return 0, skipped, fmt.Errorf("close rows: %w", err)
	}
	if err := rows.Err(); err != nil {
		return 0, skipped, fmt.Errorf("iterate rows: %w", err)
	}

	// Phase 2: apply updates atomically now that the read cursor is closed.
	// Besides avoiding a partially cleaned log on failure, this avoids a
	// separate SQLite transaction for every row.
	tx, err := db.BeginTx(ctx, nil)
	if err != nil {
		return 0, skipped, fmt.Errorf("begin updates: %w", err)
	}
	defer func() { _ = tx.Rollback() }()

	stmt, err := tx.PrepareContext(ctx, `UPDATE submission_log SET headers = ? WHERE id = ?`)
	if err != nil {
		return 0, skipped, fmt.Errorf("prepare updates: %w", err)
	}
	defer func() { _ = stmt.Close() }()

	for _, u := range pending {
		if _, uerr := stmt.ExecContext(ctx, u.cleaned, u.id); uerr != nil {
			return fixed, skipped, fmt.Errorf("update row %d: %w", u.id, uerr)
		}
		fixed++
	}
	if err := tx.Commit(); err != nil {
		return 0, skipped, fmt.Errorf("commit updates: %w", err)
	}

	return fixed, skipped, nil
}

// RunFixHeaders executes the fix-submission-log-headers subcommand. It
// normalises existing submission log entries: flattening the old array-valued
// header format and stripping redundant nginx-injected headers (X-Real-Ip,
// X-Forwarded-Proto). The command is idempotent and safe to re-run.
// Returns the process exit code.
func RunFixHeaders(_ []string, cfg config.Config) int {
	db, err := database.New(cfg.Database)
	if err != nil {
		fmt.Fprintf(os.Stderr, "Error: %v\n", err)
		return 1
	}
	defer func() { _ = db.Close() }()

	fixed, skipped, err := migrateHeaders(context.Background(), db)
	if err != nil {
		fmt.Fprintf(os.Stderr, "Error: %v\n", err)
		return 1
	}

	fmt.Printf("Cleaned %d rows, skipped %d (already normalised).\n", fixed, skipped)
	return 0
}
