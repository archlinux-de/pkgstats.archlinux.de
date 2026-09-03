package submit

import (
	"bufio"
	"compress/gzip"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log/slog"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"syscall"
	"time"
)

const (
	archiveDirectoryMode   = 0o750
	archiveFileMode        = 0o600
	archiveRetentionMonths = 12
)

type archivedLogEntry struct {
	ID          int64  `json:"id"`
	Month       int    `json:"month"`
	Timestamp   int64  `json:"timestamp"`
	IP          string `json:"ip"`
	Headers     string `json:"headers"`
	Payload     string `json:"payload"`
	PayloadHash string `json:"payload_hash"`
	Country     string `json:"country"`
}

type ArchivePruneResult struct {
	ArchivedMonths  int
	PrunedEntries   int64
	RemovedArchives int
}

// ArchiveAndPruneLog archives every expired submission-log month before
// removing it from the live database. archiveDir is required only when there
// are expired entries to archive.
func (r *Repository) ArchiveAndPruneLog(ctx context.Context, archiveDir string) (ArchivePruneResult, error) {
	now := r.now()
	cutoff := retentionCutoff(now)
	months, err := r.expiredLogMonths(ctx, cutoff)
	if err != nil {
		return ArchivePruneResult{}, fmt.Errorf("find expired submission log months: %w", err)
	}
	if len(months) > 0 && archiveDir == "" {
		return ArchivePruneResult{}, errors.New("SUBMISSION_LOG_ARCHIVE_DIR is required to archive expired submission logs")
	}

	release, err := lockArchiveDirectory(archiveDir)
	if err != nil {
		return ArchivePruneResult{}, fmt.Errorf("lock submission log archive: %w", err)
	}
	defer release()

	for _, month := range months {
		if err := r.archiveLogMonth(ctx, archiveDir, month); err != nil {
			return ArchivePruneResult{}, fmt.Errorf("archive submission log month %d: %w", month, err)
		}
	}

	deleted, err := r.pruneLog(ctx, cutoff, now)
	if err != nil {
		return ArchivePruneResult{}, err
	}
	removed, err := r.pruneArchives(archiveDir, archiveRetentionCutoff(now))
	if err != nil {
		return ArchivePruneResult{}, fmt.Errorf("prune submission log archives: %w", err)
	}
	return ArchivePruneResult{
		ArchivedMonths:  len(months),
		PrunedEntries:   deleted,
		RemovedArchives: removed,
	}, nil
}

func lockArchiveDirectory(archiveDir string) (func(), error) {
	if archiveDir == "" {
		return func() {}, nil
	}
	if err := os.MkdirAll(archiveDir, archiveDirectoryMode); err != nil {
		return nil, err
	}
	lock, err := os.OpenFile(filepath.Join(archiveDir, ".submission-log-archive.lock"), os.O_CREATE|os.O_RDWR, archiveFileMode) //nolint:gosec // archiveDir is operator configured
	if err != nil {
		return nil, err
	}
	if err := syscall.Flock(int(lock.Fd()), syscall.LOCK_EX|syscall.LOCK_NB); err != nil {
		_ = lock.Close()
		return nil, err
	}
	return func() {
		_ = syscall.Flock(int(lock.Fd()), syscall.LOCK_UN)
		_ = lock.Close()
	}, nil
}

func (r *Repository) pruneArchives(archiveDir string, cutoff int) (int, error) {
	if archiveDir == "" {
		return 0, nil
	}
	entries, err := os.ReadDir(archiveDir)
	if errors.Is(err, os.ErrNotExist) {
		return 0, nil
	}
	if err != nil {
		return 0, err
	}
	removed := 0
	for _, entry := range entries {
		month, ok := archiveMonth(entry.Name())
		if !ok || entry.IsDir() || month >= cutoff {
			continue
		}
		if err := os.Remove(filepath.Join(archiveDir, entry.Name())); err != nil {
			return 0, err
		}
		removed++
	}
	return removed, nil
}

func archiveRetentionCutoff(now time.Time) int {
	return yearMonth(time.Date(now.Year(), now.Month()-archiveRetentionMonths, 1, 0, 0, 0, 0, now.Location()))
}

func archiveMonth(name string) (int, bool) {
	const prefix = "submission-log-"
	const suffix = ".jsonl.gz"
	if !strings.HasPrefix(name, prefix) || !strings.HasSuffix(name, suffix) {
		return 0, false
	}
	month, err := strconv.Atoi(strings.TrimSuffix(strings.TrimPrefix(name, prefix), suffix))
	if err != nil || month < 100001 || month%monthMultiplier < 1 || month%monthMultiplier > 12 {
		return 0, false
	}
	return month, true
}

func (r *Repository) expiredLogMonths(ctx context.Context, cutoff int) ([]int, error) {
	rows, err := r.db.QueryContext(ctx,
		`SELECT DISTINCT month FROM submission_log WHERE month < ? ORDER BY month`, cutoff)
	if err != nil {
		return nil, err
	}
	defer func() { _ = rows.Close() }()

	var months []int
	for rows.Next() {
		var month int
		if err := rows.Scan(&month); err != nil {
			return nil, err
		}
		months = append(months, month)
	}
	return months, rows.Err()
}

func (r *Repository) archiveLogMonth(ctx context.Context, archiveDir string, month int) error {
	path := filepath.Join(archiveDir, fmt.Sprintf("submission-log-%d.jsonl.gz", month))
	if _, err := os.Lstat(path); err == nil {
		slog.Warn("replacing existing submission log archive", "month", month, "path", path)
	} else if !errors.Is(err, os.ErrNotExist) {
		return fmt.Errorf("stat archive: %w", err)
	}
	temporary, err := os.CreateTemp(archiveDir, ".submission-log-*.jsonl.gz")
	if err != nil {
		return fmt.Errorf("create temporary archive: %w", err)
	}
	temporaryPath := temporary.Name()
	defer func() { _ = os.Remove(temporaryPath) }()
	if err := temporary.Chmod(archiveFileMode); err != nil {
		_ = temporary.Close()
		return fmt.Errorf("set archive permissions: %w", err)
	}

	if err := r.writeLogArchive(ctx, temporary, month); err != nil {
		_ = temporary.Close()
		return err
	}
	if err := temporary.Sync(); err != nil {
		_ = temporary.Close()
		return fmt.Errorf("sync archive: %w", err)
	}
	if err := temporary.Close(); err != nil {
		return fmt.Errorf("close archive: %w", err)
	}
	if err := os.Rename(temporaryPath, path); err != nil {
		return fmt.Errorf("finalize archive: %w", err)
	}
	if err := syncDirectory(archiveDir); err != nil {
		return fmt.Errorf("sync archive directory: %w", err)
	}
	return nil
}

func (r *Repository) writeLogArchive(ctx context.Context, destination io.Writer, month int) error {
	rows, err := r.db.QueryContext(ctx, `
		SELECT id, month, timestamp, ip, headers, payload, payload_hash, country
		FROM submission_log
		WHERE month = ?
		ORDER BY id`, month)
	if err != nil {
		return fmt.Errorf("query submission log: %w", err)
	}
	defer func() { _ = rows.Close() }()

	compressed := gzip.NewWriter(destination)
	writer := bufio.NewWriter(compressed)
	encoder := json.NewEncoder(writer)
	for rows.Next() {
		entry := archivedLogEntry{}
		if err := rows.Scan(
			&entry.ID, &entry.Month, &entry.Timestamp, &entry.IP, &entry.Headers,
			&entry.Payload, &entry.PayloadHash, &entry.Country,
		); err != nil {
			return fmt.Errorf("scan submission log: %w", err)
		}
		if err := encoder.Encode(entry); err != nil {
			return fmt.Errorf("encode submission log: %w", err)
		}
	}
	if err := rows.Err(); err != nil {
		return fmt.Errorf("iterate submission log: %w", err)
	}

	if err := writer.Flush(); err != nil {
		return fmt.Errorf("flush archive: %w", err)
	}
	if err := compressed.Close(); err != nil {
		return fmt.Errorf("close gzip archive: %w", err)
	}
	return nil
}

func syncDirectory(path string) error {
	directory, err := os.Open(path) //nolint:gosec // path is the operator-configured archive directory
	if err != nil {
		return err
	}
	defer func() { _ = directory.Close() }()
	return directory.Sync()
}
