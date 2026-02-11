package submit

import (
	"net/http"
	"net/netip"
	"testing"
)

func TestAnonymizeIP_IPv4(t *testing.T) {
	ip := netip.MustParseAddr("192.168.1.100")
	result := AnonymizeIP(ip)
	if result != "192.168.1.0" {
		t.Errorf("AnonymizeIP(%s) = %q, want %q", ip, result, "192.168.1.0")
	}
}

func TestAnonymizeIP_IPv6(t *testing.T) {
	ip := netip.MustParseAddr("2a02:fb00::1")
	result := AnonymizeIP(ip)
	// Keeps first 48 bits (6 bytes), zeroes last 80 bits
	if result != "2a02:fb00::" {
		t.Errorf("AnonymizeIP(%s) = %q, want %q", ip, result, "2a02:fb00::")
	}
}

func TestAnonymizeIP_Invalid(t *testing.T) {
	result := AnonymizeIP(netip.Addr{})
	if result != "" {
		t.Errorf("AnonymizeIP(invalid) = %q, want empty", result)
	}
}

func TestGetClientIP_XForwardedFor(t *testing.T) {
	r, _ := http.NewRequest(http.MethodPost, "/", nil)
	r.Header.Set("X-Forwarded-For", "203.0.113.50")

	ip := getClientIP(r)
	expected := netip.MustParseAddr("203.0.113.50")
	if ip != expected {
		t.Errorf("getClientIP() = %v, want %v", ip, expected)
	}
}

func TestGetClientIP_XForwardedForMultiple(t *testing.T) {
	r, _ := http.NewRequest(http.MethodPost, "/", nil)
	r.Header.Set("X-Forwarded-For", "203.0.113.50, 70.41.3.18, 150.172.238.178")

	ip := getClientIP(r)
	expected := netip.MustParseAddr("203.0.113.50")
	if ip != expected {
		t.Errorf("getClientIP() = %v, want %v", ip, expected)
	}
}

func TestGetClientIP_XRealIP(t *testing.T) {
	r, _ := http.NewRequest(http.MethodPost, "/", nil)
	r.Header.Set("X-Real-IP", "198.51.100.10")

	ip := getClientIP(r)
	expected := netip.MustParseAddr("198.51.100.10")
	if ip != expected {
		t.Errorf("getClientIP() = %v, want %v", ip, expected)
	}
}

func TestGetClientIP_RemoteAddr(t *testing.T) {
	r, _ := http.NewRequest(http.MethodPost, "/", nil)
	r.RemoteAddr = "192.0.2.1:12345"

	ip := getClientIP(r)
	expected := netip.MustParseAddr("192.0.2.1")
	if ip != expected {
		t.Errorf("getClientIP() = %v, want %v", ip, expected)
	}
}

func TestGetClientIP_IPv6RemoteAddr(t *testing.T) {
	r, _ := http.NewRequest(http.MethodPost, "/", nil)
	r.RemoteAddr = "[::1]:12345"

	ip := getClientIP(r)
	expected := netip.MustParseAddr("::1")
	if ip != expected {
		t.Errorf("getClientIP() = %v, want %v", ip, expected)
	}
}
