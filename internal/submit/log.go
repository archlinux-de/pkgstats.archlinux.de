package submit

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"net/http"
	"net/netip"
)

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
	// http.Header is a map[string][]string, so marshaling cannot fail.
	headerJSON, _ := json.Marshal(headers)

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
