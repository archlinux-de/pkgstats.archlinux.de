package submit

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"net/http"
	"net/netip"
	"strings"
)

// headersToSkip lists nginx-injected headers that are redundant in the log:
//   - X-Real-Ip: already stored in the dedicated ip column.
//   - X-Forwarded-Proto: always "https" (enforced by nginx); carries no signal.
var headersToSkip = map[string]struct{}{
	"X-Real-Ip":         {},
	"X-Forwarded-Proto": {},
}

// marshalHeaders serializes http.Header to JSON, joining multi-value
// headers with commas (per HTTP spec). Nginx-injected headers that are
// redundant or constant are omitted (see headersToSkip).
func marshalHeaders(h http.Header) ([]byte, error) {
	headers := make(map[string]string, len(h))
	for k, vv := range h {
		if _, skip := headersToSkip[k]; skip {
			continue
		}
		headers[k] = strings.Join(vv, ", ")
	}
	return json.Marshal(headers)
}

// LogEntry is the raw record of an accepted submission. It allows analyzing
// malicious patterns and recovering the aggregate tables from data poisoning.
type LogEntry struct {
	IP          string
	Headers     string
	Payload     string
	PayloadHash string
	Country     string
}

// NewLogEntry captures the headers and raw payload of an accepted submission.
// The hash of the payload identifies identical payloads across submissions.
// The country is recorded as derived server-side, since the GeoIP lookup is
// not reproducible once the GeoIP database changes.
func NewLogEntry(headers http.Header, clientIP netip.Addr, body []byte, country string) *LogEntry {
	// Marshal headers, flattening single-value headers to strings for cleaner JSON storage.
	headerJSON, _ := marshalHeaders(headers)

	ip := ""
	if clientIP.IsValid() {
		ip = clientIP.String()
	}

	hash := sha256.Sum256(body)

	return &LogEntry{
		IP:          ip,
		Headers:     string(headerJSON),
		Payload:     string(body),
		PayloadHash: hex.EncodeToString(hash[:]),
		Country:     country,
	}
}
