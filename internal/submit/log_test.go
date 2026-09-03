package submit

import (
	"compress/gzip"
	"context"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"net/http"
	"os"
	"path/filepath"
	"reflect"
	"testing"
	"time"
)

func TestMarshalHeaders(t *testing.T) {
	tests := []struct {
		name     string
		headers  http.Header
		expected string
	}{
		{
			name:     "empty headers",
			headers:  http.Header{},
			expected: "{}",
		},
		{
			name: "single value headers",
			headers: http.Header{
				"User-Agent":   {"Mozilla/5.0"},
				"Content-Type": {"application/json"},
				"Accept":       {"application/json"},
			},
			expected: `{"Accept":"application/json","Content-Type":"application/json","User-Agent":"Mozilla/5.0"}`,
		},
		{
			name: "multi-value header joined with comma",
			headers: http.Header{
				"Accept": {"text/html", "application/xhtml+xml"},
			},
			expected: `{"Accept":"text/html, application/xhtml+xml"}`,
		},
		{
			name: "mixed single and multi-value headers",
			headers: http.Header{
				"User-Agent": {"Mozilla/5.0"},
				"Accept":     {"text/html", "application/xhtml+xml"},
				"X-Custom":   {"value1", "value2"},
			},
			expected: `{"Accept":"text/html, application/xhtml+xml","User-Agent":"Mozilla/5.0","X-Custom":"value1, value2"}`,
		},
		{
			name: "nginx-injected headers are excluded",
			headers: http.Header{
				"User-Agent":        {"pkgstats/3.5.3"},
				"Accept":            {"application/json"},
				"X-Real-Ip":         {"203.0.113.1"},
				"X-Forwarded-Proto": {"https"},
			},
			expected: `{"User-Agent":"pkgstats/3.5.3","Accept":"application/json"}`,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := marshalHeaders(tt.headers)
			if err != nil {
				t.Fatalf("marshalHeaders failed: %v", err)
			}

			// Parse both to compare semantically (order doesn't matter in JSON objects)
			var gotMap, expectedMap map[string]string
			if err := json.Unmarshal(got, &gotMap); err != nil {
				t.Fatalf("failed to parse got JSON: %v", err)
			}
			if err := json.Unmarshal([]byte(tt.expected), &expectedMap); err != nil {
				t.Fatalf("failed to parse expected JSON: %v", err)
			}

			if !reflect.DeepEqual(gotMap, expectedMap) {
				t.Errorf("got %s, want %s", got, tt.expected)
			}
		})
	}
}

func TestHandleSubmit_LogsSubmission(t *testing.T) {
	handler, db := setupTestHandler(t)
	body := validRequestBody()

	w := submitRequest(handler, body)
	if w.Code != http.StatusNoContent {
		t.Fatalf("expected 204, got %d: %s", w.Code, w.Body.String())
	}

	var (
		month                             int
		ip, headers, payload, payloadHash string
		country                           string
	)
	err := db.QueryRow(
		`SELECT month, ip, headers, payload, payload_hash, country
		 FROM submission_log`,
	).
		Scan(&month, &ip, &headers, &payload, &payloadHash, &country)
	if err != nil {
		t.Fatalf("failed to load log entry: %v", err)
	}

	now := time.Now()
	if expected := now.Year()*monthMultiplier + int(now.Month()); month != expected {
		t.Errorf("expected month %d, got %d", expected, month)
	}
	if ip != "203.0.113.50" {
		t.Errorf("expected IP 203.0.113.50, got %q", ip)
	}
	var parsedHeaders map[string]string
	if err := json.Unmarshal([]byte(headers), &parsedHeaders); err != nil {
		t.Fatalf("headers column is not valid JSON: %v — raw: %s", err, headers)
	}
	if _, ok := parsedHeaders["X-Real-Ip"]; ok {
		t.Error("X-Real-Ip should be excluded from stored headers (already captured in ip column)")
	}
	if parsedHeaders["User-Agent"] != "pkgstats/3.0" {
		t.Errorf("expected User-Agent to be captured, got %q", parsedHeaders["User-Agent"])
	}
	if payload != body {
		t.Errorf("expected payload to match request body, got %q", payload)
	}

	hash := sha256.Sum256([]byte(body))
	if expected := hex.EncodeToString(hash[:]); payloadHash != expected {
		t.Errorf("expected payload hash %s, got %s", expected, payloadHash)
	}

	if country != "DE" {
		t.Errorf("expected country DE, got %q", country)
	}
}

func TestPrune(t *testing.T) {
	handler, db := setupTestHandler(t)

	_, err := db.Exec(
		`INSERT INTO submission_log (month, timestamp, ip, headers, payload, payload_hash, country)
		 VALUES (200001, 0, '', '{}', '{}', '', '')`,
	)
	if err != nil {
		t.Fatalf("failed to insert expired log entry: %v", err)
	}
	_, err = db.Exec(`INSERT INTO submission_dedup (fingerprint, expires_at) VALUES (X'01', 0)`)
	if err != nil {
		t.Fatalf("failed to insert expired deduplication entry: %v", err)
	}

	if w := submitRequest(handler, validRequestBody()); w.Code != http.StatusNoContent {
		t.Fatalf("expected 204, got %d: %s", w.Code, w.Body.String())
	}

	// Submitting must not prune; pruning is a separate maintenance command.
	var before int
	if err := db.QueryRow(`SELECT COUNT(*) FROM submission_log`).Scan(&before); err != nil {
		t.Fatalf("failed to count log entries: %v", err)
	}
	if before != 2 {
		t.Fatalf("expected 2 rows before prune, got %d", before)
	}

	archiveDir := t.TempDir()
	repository := NewRepository(db)
	repository.now = func() time.Time { return time.Date(2001, time.January, 1, 0, 0, 0, 0, time.UTC) }
	result, err := repository.ArchiveAndPruneLog(context.Background(), archiveDir)
	if err != nil {
		t.Fatalf("prune failed: %v", err)
	}
	if result.ArchivedMonths != 1 {
		t.Errorf("expected 1 archived month, got %d", result.ArchivedMonths)
	}
	if result.PrunedEntries != 1 {
		t.Errorf("expected 1 pruned entry, got %d", result.PrunedEntries)
	}
	if result.RemovedArchives != 0 {
		t.Errorf("expected no expired archives to be removed, got %d", result.RemovedArchives)
	}

	archive, err := os.Open(filepath.Join(archiveDir, "submission-log-200001.jsonl.gz"))
	if err != nil {
		t.Fatalf("open archive: %v", err)
	}
	defer func() { _ = archive.Close() }()
	compressed, err := gzip.NewReader(archive)
	if err != nil {
		t.Fatalf("read gzip archive: %v", err)
	}
	defer func() { _ = compressed.Close() }()
	var entry archivedLogEntry
	if err := json.NewDecoder(compressed).Decode(&entry); err != nil {
		t.Fatalf("decode archive entry: %v", err)
	}
	if entry.Month != 200001 {
		t.Errorf("archive month = %d, want 200001", entry.Month)
	}
	if entry.Payload != "{}" || entry.Headers != "{}" || entry.Country != "" {
		t.Errorf("archive entry = %#v, want original submission log values", entry)
	}

	var remaining int
	if err := db.QueryRow(`SELECT COUNT(*) FROM submission_log WHERE month = 200001`).Scan(&remaining); err != nil {
		t.Fatalf("failed to count log entries: %v", err)
	}
	if remaining != 0 {
		t.Errorf("expected expired log entry to be pruned, found %d", remaining)
	}

	var dedupEntries int
	if err := db.QueryRow(`SELECT COUNT(*) FROM submission_dedup WHERE fingerprint = X'01'`).Scan(&dedupEntries); err != nil {
		t.Fatalf("failed to count deduplication entries: %v", err)
	}
	if dedupEntries != 0 {
		t.Errorf("expected expired deduplication entry to be pruned, found %d", dedupEntries)
	}
}

func TestArchiveAndPruneRequiresArchiveDirectory(t *testing.T) {
	_, db := setupTestHandler(t)
	_, err := db.Exec(`
		INSERT INTO submission_log (month, timestamp, ip, headers, payload, payload_hash, country)
		VALUES (200001, 0, '', '{}', '{}', '', '')`)
	if err != nil {
		t.Fatalf("insert expired submission log: %v", err)
	}

	repository := NewRepository(db)
	repository.now = func() time.Time { return time.Date(2001, time.January, 1, 0, 0, 0, 0, time.UTC) }
	_, err = repository.ArchiveAndPruneLog(context.Background(), "")
	if err == nil {
		t.Fatal("expected missing archive directory error")
	}

	var remaining int
	if err := db.QueryRow(`SELECT COUNT(*) FROM submission_log WHERE month = 200001`).Scan(&remaining); err != nil {
		t.Fatalf("count expired submission logs: %v", err)
	}
	if remaining != 1 {
		t.Errorf("expected expired log entry to remain, found %d", remaining)
	}
}

func TestArchiveRetention(t *testing.T) {
	archiveDir := t.TempDir()
	for _, name := range []string{
		"submission-log-202409.jsonl.gz",
		"submission-log-202509.jsonl.gz",
		"unrelated.jsonl.gz",
	} {
		if err := os.WriteFile(filepath.Join(archiveDir, name), nil, archiveFileMode); err != nil {
			t.Fatalf("create archive %s: %v", name, err)
		}
	}

	repository := NewRepository(nil)
	repository.now = func() time.Time { return time.Date(2026, time.September, 3, 0, 0, 0, 0, time.UTC) }
	removed, err := repository.pruneArchives(archiveDir, archiveRetentionCutoff(repository.now()))
	if err != nil {
		t.Fatalf("prune archives: %v", err)
	}
	if removed != 1 {
		t.Errorf("removed archives = %d, want 1", removed)
	}
	for _, name := range []string{"submission-log-202509.jsonl.gz", "unrelated.jsonl.gz"} {
		if _, err := os.Stat(filepath.Join(archiveDir, name)); err != nil {
			t.Errorf("expected %s to remain: %v", name, err)
		}
	}
}

func TestArchiveAndPruneReplacesExistingArchive(t *testing.T) {
	_, db := setupTestHandler(t)
	_, err := db.Exec(`
		INSERT INTO submission_log (month, timestamp, ip, headers, payload, payload_hash, country)
		VALUES (200001, 0, '', '{}', '{}', '', '')`)
	if err != nil {
		t.Fatalf("insert expired submission log: %v", err)
	}

	archiveDir := t.TempDir()
	archivePath := filepath.Join(archiveDir, "submission-log-200001.jsonl.gz")
	if err := os.WriteFile(archivePath, []byte("not a gzip archive"), archiveFileMode); err != nil {
		t.Fatalf("write old archive: %v", err)
	}
	repository := NewRepository(db)
	repository.now = func() time.Time { return time.Date(2001, time.January, 1, 0, 0, 0, 0, time.UTC) }
	result, err := repository.ArchiveAndPruneLog(context.Background(), archiveDir)
	if err != nil {
		t.Fatalf("archive and prune logs: %v", err)
	}
	if result.PrunedEntries != 1 {
		t.Errorf("pruned entries = %d, want 1", result.PrunedEntries)
	}

	archive, err := os.Open(archivePath)
	if err != nil {
		t.Fatalf("open archive: %v", err)
	}
	defer func() { _ = archive.Close() }()
	compressed, err := gzip.NewReader(archive)
	if err != nil {
		t.Fatalf("read gzip archive: %v", err)
	}
	defer func() { _ = compressed.Close() }()
	var entry archivedLogEntry
	if err := json.NewDecoder(compressed).Decode(&entry); err != nil {
		t.Fatalf("decode archive entry: %v", err)
	}
	if entry.Month != 200001 {
		t.Errorf("archive month = %d, want 200001", entry.Month)
	}
}

func TestArchiveAndPruneRejectsConcurrentRun(t *testing.T) {
	_, db := setupTestHandler(t)
	_, err := db.Exec(`
		INSERT INTO submission_log (month, timestamp, ip, headers, payload, payload_hash, country)
		VALUES (200001, 0, '', '{}', '{}', '', '')`)
	if err != nil {
		t.Fatalf("insert expired submission log: %v", err)
	}

	archiveDir := t.TempDir()
	release, err := lockArchiveDirectory(archiveDir)
	if err != nil {
		t.Fatalf("lock archive directory: %v", err)
	}
	defer release()
	repository := NewRepository(db)
	repository.now = func() time.Time { return time.Date(2001, time.January, 1, 0, 0, 0, 0, time.UTC) }
	if _, err := repository.ArchiveAndPruneLog(context.Background(), archiveDir); err == nil {
		t.Fatal("expected concurrent archive error")
	}

	var remaining int
	if err := db.QueryRow(`SELECT COUNT(*) FROM submission_log WHERE month = 200001`).Scan(&remaining); err != nil {
		t.Fatalf("count submission logs: %v", err)
	}
	if remaining != 1 {
		t.Errorf("expected expired log entry to remain, found %d", remaining)
	}
}

func TestArchiveAndPruneUsesSingleCutoff(t *testing.T) {
	_, db := setupTestHandler(t)
	_, err := db.Exec(`
		INSERT INTO submission_log (month, timestamp, ip, headers, payload, payload_hash, country)
		VALUES
			(202605, 0, '', '{}', '{}', '', ''),
			(202606, 0, '', '{}', '{}', '', '')`)
	if err != nil {
		t.Fatalf("insert submission logs: %v", err)
	}

	repository := NewRepository(db)
	nowCalls := 0
	repository.now = func() time.Time {
		nowCalls++
		if nowCalls == 1 {
			return time.Date(2026, time.August, 1, 0, 0, 0, 0, time.UTC)
		}
		return time.Date(2026, time.September, 1, 0, 0, 0, 0, time.UTC)
	}
	if _, err := repository.ArchiveAndPruneLog(context.Background(), t.TempDir()); err != nil {
		t.Fatalf("archive and prune logs: %v", err)
	}

	var remaining int
	if err := db.QueryRow(`SELECT COUNT(*) FROM submission_log WHERE month = 202606`).Scan(&remaining); err != nil {
		t.Fatalf("count submission logs: %v", err)
	}
	if remaining != 1 {
		t.Errorf("expected June log entry to remain, found %d", remaining)
	}
}

func TestRetentionCutoff(t *testing.T) {
	tests := []struct {
		name     string
		now      time.Time
		expected int
	}{
		{"mid-year", time.Date(2026, time.July, 4, 12, 0, 0, 0, time.UTC), 202605},
		{"cross-year from February", time.Date(2026, time.February, 1, 0, 0, 0, 0, time.UTC), 202512},
		{"month-end cross-year from January", time.Date(2026, time.January, 31, 23, 59, 59, 0, time.UTC), 202511},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if cutoff := retentionCutoff(tt.now); cutoff != tt.expected {
				t.Errorf("retentionCutoff(%s) = %d, want %d", tt.now, cutoff, tt.expected)
			}
		})
	}
}
