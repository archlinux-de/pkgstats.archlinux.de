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

// flattenHeaders converts a map[string][]string (old HTTP header format written
// by http.Header marshalling) to map[string]string by joining values with ", ".
// Returns (nil, false, nil) when the input is already in the new flat format.
func flattenHeaders(headersJSON string) ([]byte, bool, error) {
	// An empty object ("{}") can unmarshal into either map[string][]string or
	// map[string]string, so treat it as already flat to avoid a no-op update.
	if headersJSON == "{}" {
		return nil, false, nil
	}

	var oldHeaders map[string][]string
	if err := json.Unmarshal([]byte(headersJSON), &oldHeaders); err != nil {
		// Unmarshal failed: the value uses string values — already in the new
		// map[string]string format, skip it.
		return nil, false, nil
	}

	flat := make(map[string]string, len(oldHeaders))
	for k, vv := range oldHeaders {
		flat[k] = strings.Join(vv, ", ")
	}

	flatJSON, err := json.Marshal(flat)
	if err != nil {
		return nil, true, fmt.Errorf("marshal flat headers: %w", err)
	}
	return flatJSON, true, nil
}

type pendingUpdate struct {
	id       int64
	flatJSON string
}

// migrateHeaders reads all rows from submission_log and rewrites any
// array-valued headers column to the flat string format. It collects all
// rows before writing so the read cursor is closed before any updates are
// issued, which is required by SQLite. It returns the number of rows
// migrated and the number skipped (already flat).
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

		flatJSON, needsMigration, ferr := flattenHeaders(headersJSON)
		if ferr != nil {
			_ = rows.Close()
			return 0, 0, fmt.Errorf("row %d: %w", id, ferr)
		}
		if !needsMigration {
			skipped++
			continue
		}
		pending = append(pending, pendingUpdate{id: id, flatJSON: string(flatJSON)})
	}

	if err := rows.Close(); err != nil {
		return 0, skipped, fmt.Errorf("close rows: %w", err)
	}
	if err := rows.Err(); err != nil {
		return 0, skipped, fmt.Errorf("iterate rows: %w", err)
	}

	// Phase 2: apply updates now that the read cursor is closed.
	for _, u := range pending {
		if _, uerr := db.ExecContext(ctx,
			`UPDATE submission_log SET headers = ? WHERE id = ?`,
			u.flatJSON, u.id,
		); uerr != nil {
			return fixed, skipped, fmt.Errorf("update row %d: %w", u.id, uerr)
		}
		fixed++
	}

	return fixed, skipped, nil
}

// RunFixHeaders executes the fix-submission-log-headers subcommand. It migrates
// existing submission log entries from array-valued headers to string-valued
// headers. This is a one-time migration command that can be safely re-run;
// already-migrated rows are skipped. Returns the process exit code.
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

	fmt.Printf("Migrated %d rows, skipped %d (already flat).\n", fixed, skipped)
	return 0
}
