package submit

import (
	"net/url"
	"regexp"
	"strings"
)

var (
	// Matches IPv4 addresses
	ipv4Regex = regexp.MustCompile(`^[0-9.]+$`)
	// Matches IPv6 addresses in brackets
	ipv6Regex = regexp.MustCompile(`^\[[0-9a-f:]+]$`)
	// Matches local/private domain suffixes
	localDomainRegex = regexp.MustCompile(`(?:^|\.)(?:localhost|local|box|lan|home|onion|internal|intranet|private)$`)
	// Matches package paths to strip
	packagePathRegex = regexp.MustCompile(`^(.+?)(?:extra|core)/(?:os/)?.*`)
	// Matches pkgstats package in path
	pkgstatsPathRegex = regexp.MustCompile(`^(.+?)pkgstats-[0-9.]+-[0-9]+-.+?\.pkg\.tar\.(?:gz|xz|zst)$`)
)

// FilterMirrorURL validates and normalizes a mirror URL.
// Returns empty string if the URL is invalid or should be excluded.
func FilterMirrorURL(rawURL string) string {
	if rawURL == "" {
		return ""
	}

	parsed, err := url.Parse(rawURL)
	if err != nil {
		return ""
	}

	// Check scheme
	if parsed.Scheme != "http" && parsed.Scheme != "https" && parsed.Scheme != "ftp" {
		return ""
	}

	// Check host
	if parsed.Host == "" {
		return ""
	}

	// Extract hostname without port
	hostname := parsed.Hostname()

	// Reject if port is specified
	if parsed.Port() != "" {
		return ""
	}

	// Reject if user info is present
	if parsed.User != nil {
		return ""
	}

	// Must have at least one dot in hostname
	if strings.Count(hostname, ".") < 1 {
		return ""
	}

	// Reject IPv4 addresses
	if ipv4Regex.MatchString(hostname) {
		return ""
	}

	// Reject IPv6 addresses
	if ipv6Regex.MatchString(hostname) {
		return ""
	}

	// Reject local/private domains
	if localDomainRegex.MatchString(hostname) {
		return ""
	}

	// Normalize path
	path := parsed.Path
	if path == "" {
		path = "/"
	}

	// Strip package-specific paths
	if match := packagePathRegex.FindStringSubmatch(path); match != nil {
		path = match[1]
	}
	if match := pkgstatsPathRegex.FindStringSubmatch(path); match != nil {
		path = match[1]
	}

	// Clean up path
	path = strings.ReplaceAll(path, "//", "/")
	path = strings.ReplaceAll(path, "\\", "")

	return parsed.Scheme + "://" + hostname + path
}
