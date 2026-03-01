package submit

import (
	"net/http"
	"net/http/httptest"
	"net/netip"
	"testing"
)

func TestGetClientIP(t *testing.T) {
	tests := []struct {
		name       string
		remoteAddr string
		xRealIP    string
		expected   netip.Addr
	}{
		{
			name:       "X-Real-IP IPv4",
			remoteAddr: "127.0.0.1:1234",
			xRealIP:    "203.0.113.50",
			expected:   netip.MustParseAddr("203.0.113.50"),
		},
		{
			name:       "X-Real-IP IPv6",
			remoteAddr: "127.0.0.1:1234",
			xRealIP:    "2001:db8::1",
			expected:   netip.MustParseAddr("2001:db8::1"),
		},
		{
			name:       "X-Real-IP invalid falls back to RemoteAddr",
			remoteAddr: "198.51.100.1:8080",
			xRealIP:    "not-an-ip",
			expected:   netip.MustParseAddr("198.51.100.1"),
		},
		{
			name:       "RemoteAddr IPv4 with port",
			remoteAddr: "203.0.113.50:12345",
			expected:   netip.MustParseAddr("203.0.113.50"),
		},
		{
			name:       "RemoteAddr IPv6 with port",
			remoteAddr: "[2001:db8::1]:12345",
			expected:   netip.MustParseAddr("2001:db8::1"),
		},
		{
			name:       "RemoteAddr IPv6 without port in brackets",
			remoteAddr: "[2001:db8::1]",
			expected:   netip.MustParseAddr("2001:db8::1"),
		},
		{
			name:       "RemoteAddr IPv4 without port",
			remoteAddr: "203.0.113.50",
			expected:   netip.MustParseAddr("203.0.113.50"),
		},
		{
			name:       "empty RemoteAddr and no X-Real-IP",
			remoteAddr: "",
			expected:   netip.Addr{},
		},
		{
			name:       "X-Real-IP takes precedence over RemoteAddr",
			remoteAddr: "198.51.100.1:8080",
			xRealIP:    "203.0.113.50",
			expected:   netip.MustParseAddr("203.0.113.50"),
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			req := httptest.NewRequest(http.MethodPost, "/api/submit", nil)
			req.RemoteAddr = tt.remoteAddr
			if tt.xRealIP != "" {
				req.Header.Set("X-Real-IP", tt.xRealIP)
			}

			got := getClientIP(req)
			if got != tt.expected {
				t.Errorf("getClientIP() = %v, want %v", got, tt.expected)
			}
		})
	}
}
