package submit

import (
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"regexp"
	"slices"
	"strings"
)

var (
	osIDRegexp        = regexp.MustCompile(`^[0-9a-z._-]{1,50}$`)
	packageNameRegexp = regexp.MustCompile(`^[a-zA-Z0-9][a-zA-Z0-9@:.+_-]{0,190}$`)
)

const (
	expectedVersion       = "3"
	maxPackages           = 20000
	minPackages           = 1
	maxPackageLen         = 191
	truncateErrorMsgLimit = 20
	archX86_64            = "x86_64"
)

// Request represents a pkgstats submission request.
type Request struct {
	Version string     `json:"version"`
	System  SystemInfo `json:"system"`
	OS      OSInfo     `json:"os"`
	Pacman  PacmanInfo `json:"pacman"`
	Country string     `json:"-"` // Set by server from GeoIP
}

// SystemInfo contains system architecture information.
type SystemInfo struct {
	Architecture string `json:"architecture"`
}

// OSInfo contains OS architecture information.
type OSInfo struct {
	Architecture string `json:"architecture"`
	ID           string `json:"id"`
}

// PacmanInfo contains pacman-related information.
type PacmanInfo struct {
	Mirror   string   `json:"mirror"`
	Packages []string `json:"packages"`
}

// ParseRequest parses and validates a request from the given reader.
func ParseRequest(r io.Reader) (*Request, error) {
	var req Request
	if err := json.NewDecoder(r).Decode(&req); err != nil {
		return nil, fmt.Errorf("invalid JSON: %w", err)
	}

	if err := req.Validate(); err != nil {
		return nil, err
	}

	return &req, nil
}

// Validate checks that the request is valid.
func (r *Request) Validate() error {
	if r.Version != expectedVersion {
		return errors.New("version must be \"3\"")
	}

	if r.System.Architecture == "" {
		return errors.New("system.architecture is required")
	}

	if r.OS.Architecture == "" {
		return errors.New("os.architecture is required")
	}

	if len(r.Pacman.Packages) < minPackages {
		return errors.New("pacman.packages must contain at least 1 package")
	}

	if len(r.Pacman.Packages) > maxPackages {
		return fmt.Errorf("pacman.packages must contain at most %d packages", maxPackages)
	}

	for _, pkg := range r.Pacman.Packages {
		if pkg == "" {
			return errors.New("package name cannot be empty")
		}
		if len(pkg) > maxPackageLen {
			return fmt.Errorf("package name %q exceeds maximum length of %d", truncate(pkg, truncateErrorMsgLimit), maxPackageLen)
		}
		if !packageNameRegexp.MatchString(pkg) {
			return fmt.Errorf("invalid package name %q", truncate(pkg, truncateErrorMsgLimit))
		}
	}

	if err := validateArchitectures(r.System.Architecture, r.OS.Architecture); err != nil {
		return err
	}

	if r.OS.ID != "" && !osIDRegexp.MatchString(r.OS.ID) {
		return errors.New("os.id must match pattern [0-9a-z._-]{1,50}")
	}

	return nil
}

// validateArchitectures checks that the system and OS architectures are compatible.
func validateArchitectures(systemArch, osArch string) error {
	validOSArchs := getValidOSArchitectures(systemArch)
	if len(validOSArchs) == 0 {
		return fmt.Errorf("invalid system architecture: %s", systemArch)
	}

	if !slices.Contains(validOSArchs, osArch) {
		return fmt.Errorf("invalid OS architecture %s for system architecture %s", osArch, systemArch)
	}

	validSystemArchs := getValidSystemArchitectures(osArch)
	if !slices.Contains(validSystemArchs, systemArch) {
		return fmt.Errorf("invalid system architecture %s for OS architecture %s", systemArch, osArch)
	}

	return nil
}

// getValidOSArchitectures returns valid OS architectures for a given system architecture.
func getValidOSArchitectures(systemArch string) []string {
	switch systemArch {
	case archX86_64, "x86_64_v2", "x86_64_v3", "x86_64_v4":
		return []string{archX86_64, "i686", "i586"}
	case "i686":
		return []string{"i686", "i586"}
	case "i586":
		return []string{"i586"}
	case "aarch64":
		return []string{"aarch64", "armv7h", "armv6h", "armv7l", "armv6l", "arm", "armv5tel"}
	case "armv7":
		return []string{"armv7h", "armv6h", "armv7l", "armv6l", "arm", "armv5tel"}
	case "armv6":
		return []string{"armv6h", "armv6l", "arm", "armv5tel"}
	case "armv5":
		return []string{"arm", "armv5tel"}
	case "riscv64":
		return []string{"riscv64"}
	case "loong64":
		return []string{"loongarch64"}
	default:
		return nil
	}
}

// getValidSystemArchitectures returns valid system architectures for a given OS architecture.
func getValidSystemArchitectures(osArch string) []string {
	switch osArch {
	case archX86_64:
		return []string{archX86_64, "x86_64_v2", "x86_64_v3", "x86_64_v4"}
	case "i686":
		return []string{"i686", archX86_64, "x86_64_v2", "x86_64_v3", "x86_64_v4"}
	case "i586":
		return []string{"i586", "i686", archX86_64, "x86_64_v2", "x86_64_v3", "x86_64_v4"}
	case "aarch64":
		return []string{"aarch64"}
	case "armv6h", "armv6l":
		return []string{"armv6", "armv7", "aarch64"}
	case "armv7h", "armv7l":
		return []string{"armv7", "aarch64"}
	case "arm", "armv5tel":
		return []string{"armv5", "armv6", "armv7", "aarch64"}
	case "riscv64":
		return []string{"riscv64"}
	case "loongarch64":
		return []string{"loong64"}
	default:
		return nil
	}
}

// ValidateExpectedPackages checks that a sufficient fraction of expected packages are present.
// Returns an error if more than maxMissing fraction of expected packages are missing.
func ValidateExpectedPackages(packages, expected []string, maxMissing float64) error {
	if len(expected) == 0 {
		return nil
	}

	pkgSet := make(map[string]struct{}, len(packages))
	for _, pkg := range packages {
		pkgSet[strings.ToLower(pkg)] = struct{}{}
	}

	missing := 0
	for _, exp := range expected {
		if _, ok := pkgSet[strings.ToLower(exp)]; !ok {
			missing++
		}
	}

	if float64(missing)/float64(len(expected)) > maxMissing {
		return errors.New("package list does not contain expected packages")
	}

	return nil
}

func truncate(s string, maxLen int) string {
	if len(s) <= maxLen {
		return s
	}
	return s[:maxLen] + "..."
}

// DeduplicatePackages returns unique package names, lowercased.
func (r *Request) DeduplicatePackages() []string {
	seen := make(map[string]struct{}, len(r.Pacman.Packages))
	result := make([]string, 0, len(r.Pacman.Packages))

	for _, pkg := range r.Pacman.Packages {
		lower := strings.ToLower(pkg)
		if _, ok := seen[lower]; !ok {
			seen[lower] = struct{}{}
			result = append(result, lower)
		}
	}

	return result
}
