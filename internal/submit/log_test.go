package submit

import (
	"context"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"net/http"
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
