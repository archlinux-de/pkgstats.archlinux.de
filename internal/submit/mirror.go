package submit

import (
	"net/url"
	"regexp"
	"strings"
)

var (
	ipv4Regex         = regexp.MustCompile(`^[0-9.]+$`)
	ipv6Regex         = regexp.MustCompile(`^\[[0-9a-f:]+]$`)
	localDomainRegex  = regexp.MustCompile(`(?:^|\.)(?:localhost|local|box|lan|home|onion|internal|intranet|private)$`)
	packagePathRegex  = regexp.MustCompile(`^(.+?)(?:extra|core)/(?:os/)?.*`)
	pkgstatsPathRegex = regexp.MustCompile(`^(.+?)pkgstats-[0-9.]+-[0-9]+-.+?\.pkg\.tar\.(?:gz|xz|zst)$`)
)

const maxMirrorURLLen = 255

// FilterMirrorURL validates and normalizes a mirror URL.
// Returns empty string if invalid or excluded.
func FilterMirrorURL(rawURL string) string {
	if rawURL == "" {
		return ""
	}

	if len(rawURL) > maxMirrorURLLen {
		return ""
	}

	parsed, err := url.Parse(rawURL)
	if err != nil {
		return ""
	}

	if parsed.Scheme != "http" && parsed.Scheme != "https" && parsed.Scheme != "ftp" {
		return ""
	}

	if parsed.Host == "" {
		return ""
	}

	hostname := parsed.Hostname()

	if parsed.Port() != "" {
		return ""
	}

	if parsed.User != nil {
		return ""
	}

	if strings.Count(hostname, ".") < 1 {
		return ""
	}

	if ipv4Regex.MatchString(hostname) {
		return ""
	}

	if ipv6Regex.MatchString(hostname) {
		return ""
	}

	if localDomainRegex.MatchString(hostname) {
		return ""
	}

	path := parsed.Path
	if path == "" {
		path = "/"
	}

	if match := packagePathRegex.FindStringSubmatch(path); match != nil {
		path = match[1]
	}
	if match := pkgstatsPathRegex.FindStringSubmatch(path); match != nil {
		path = match[1]
	}

	path = strings.ReplaceAll(path, "//", "/")
	path = strings.ReplaceAll(path, "\\", "")

	return parsed.Scheme + "://" + hostname + path
}
