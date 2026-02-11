package submit

import (
	"strings"
	"testing"
)

func TestFilterMirrorURL(t *testing.T) {
	tests := []struct {
		name     string
		input    string
		expected string
	}{
		// Valid URLs
		{"https simple", "https://mirror.example.com/", "https://mirror.example.com/"},
		{"http simple", "http://mirror.example.com/", "http://mirror.example.com/"},
		{"ftp simple", "ftp://mirror.example.com/", "ftp://mirror.example.com/"},
		{"with path", "https://mirror.example.com/archlinux/", "https://mirror.example.com/archlinux/"},

		// Path normalization
		{"strip core path", "https://mirror.example.com/archlinux/core/os/x86_64/", "https://mirror.example.com/archlinux/"},
		{"strip extra path", "https://mirror.example.com/archlinux/extra/os/x86_64/", "https://mirror.example.com/archlinux/"},
		{"strip pkgstats", "https://mirror.example.com/archlinux/pkgstats-3.0.4-1-any.pkg.tar.zst", "https://mirror.example.com/archlinux/"},

		// Add trailing slash if missing
		{"empty path", "https://mirror.example.com", "https://mirror.example.com/"},

		// Invalid - should return empty
		{"empty string", "", ""},
		{"no scheme", "mirror.example.com/", ""},
		{"invalid scheme", "gopher://mirror.example.com/", ""},
		{"with port", "https://mirror.example.com:8080/", ""},
		{"with user", "https://user@mirror.example.com/", ""},
		{"with user pass", "https://user:pass@mirror.example.com/", ""},

		// Invalid hosts
		{"no dots", "https://localhost/", ""},
		{"ipv4", "https://192.168.1.1/", ""},
		{"ipv6", "https://[::1]/", ""},

		// Local domains
		{"localhost suffix", "https://mirror.localhost/", ""},
		{"local suffix", "https://mirror.local/", ""},
		{"lan suffix", "https://mirror.lan/", ""},
		{"home suffix", "https://mirror.home/", ""},
		{"onion suffix", "https://mirror.onion/", ""},
		{"internal suffix", "https://mirror.internal/", ""},
		{"intranet suffix", "https://mirror.intranet/", ""},
		{"private suffix", "https://mirror.private/", ""},
		{"box suffix", "https://mirror.box/", ""},

		// Double slashes and backslashes
		{"double slash", "https://mirror.example.com//archlinux//", "https://mirror.example.com/archlinux/"},
		{"backslash", "https://mirror.example.com/arch\\linux/", "https://mirror.example.com/archlinux/"},

		// Length limit
		{"too long URL", "https://mirror.example.com/" + strings.Repeat("a", 230) + "/", ""},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			result := FilterMirrorURL(tt.input)
			if result != tt.expected {
				t.Errorf("FilterMirrorURL(%q) = %q, want %q", tt.input, result, tt.expected)
			}
		})
	}
}
