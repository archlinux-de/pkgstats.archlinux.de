package submit

import (
	"context"
	"crypto/sha256"
	"encoding/hex"
	"net/http"
	"testing"
	"time"
)

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
		 FROM submission_log`).
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
	if headers == "" || headers == "{}" {
		t.Errorf("expected headers to be captured, got %q", headers)
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
		 VALUES (200001, 0, '', '{}', '{}', '', '')`)
	if err != nil {
		t.Fatalf("failed to insert expired log entry: %v", err)
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

	deleted, err := NewRepository(db).PruneLog(context.Background())
	if err != nil {
		t.Fatalf("prune failed: %v", err)
	}
	if deleted != 1 {
		t.Errorf("expected 1 pruned entry, got %d", deleted)
	}

	var remaining int
	if err := db.QueryRow(`SELECT COUNT(*) FROM submission_log WHERE month = 200001`).Scan(&remaining); err != nil {
		t.Fatalf("failed to count log entries: %v", err)
	}
	if remaining != 0 {
		t.Errorf("expected expired log entry to be pruned, found %d", remaining)
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
