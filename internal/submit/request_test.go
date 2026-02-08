package submit

import (
	"strings"
	"testing"
)

func TestParseRequest_ValidRequest(t *testing.T) {
	jsonData := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64"},
		"pacman": {
			"mirror": "https://geo.mirror.pkgbuild.com/",
			"packages": ["pacman", "linux", "base"]
		}
	}`

	req, err := ParseRequest(strings.NewReader(jsonData))
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if req.Version != "3" {
		t.Errorf("expected version 3, got %s", req.Version)
	}
	if req.System.Architecture != "x86_64" { //nolint:goconst
		t.Errorf("expected system architecture x86_64, got %s", req.System.Architecture)
	}
	if len(req.Pacman.Packages) != 3 {
		t.Errorf("expected 3 packages, got %d", len(req.Pacman.Packages))
	}
}

func TestParseRequest_ValidRequestWithOSID(t *testing.T) {
	jsonData := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64", "id": "arch"},
		"pacman": {"packages": ["pacman"]}
	}`

	req, err := ParseRequest(strings.NewReader(jsonData))
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if req.OS.ID != "arch" {
		t.Errorf("expected os.id arch, got %s", req.OS.ID)
	}
}

func TestParseRequest_InvalidOSID(t *testing.T) {
	tests := []struct {
		name string
		id   string
	}{
		{"uppercase", "Arch"},
		{"spaces", "arch linux"},
		{"special chars", "arch@linux"},
		{"too long", strings.Repeat("a", 51)},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			jsonData := `{
				"version": "3",
				"system": {"architecture": "x86_64"},
				"os": {"architecture": "x86_64", "id": "` + tt.id + `"},
				"pacman": {"packages": ["pacman"]}
			}`

			_, err := ParseRequest(strings.NewReader(jsonData))
			if err == nil {
				t.Fatal("expected error for invalid os.id")
			}
			if !strings.Contains(err.Error(), "os.id") {
				t.Errorf("expected os.id error, got: %v", err)
			}
		})
	}
}

func TestParseRequest_ValidOSIDPatterns(t *testing.T) {
	validIDs := []string{"arch", "artix", "endeavouros", "garuda", "manjaro", "cachyos", "arch_linux", "my-os", "os.1.0"}

	for _, id := range validIDs {
		t.Run(id, func(t *testing.T) {
			jsonData := `{
				"version": "3",
				"system": {"architecture": "x86_64"},
				"os": {"architecture": "x86_64", "id": "` + id + `"},
				"pacman": {"packages": ["pacman"]}
			}`

			_, err := ParseRequest(strings.NewReader(jsonData))
			if err != nil {
				t.Fatalf("unexpected error for valid os.id %q: %v", id, err)
			}
		})
	}
}

func TestParseRequest_InvalidVersion(t *testing.T) {
	jsonData := `{
		"version": "2",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64"},
		"pacman": {"packages": ["pacman"]}
	}`

	_, err := ParseRequest(strings.NewReader(jsonData))
	if err == nil {
		t.Fatal("expected error for invalid version")
	}
	if !strings.Contains(err.Error(), "version") {
		t.Errorf("expected version error, got: %v", err)
	}
}

func TestParseRequest_MissingSystemArchitecture(t *testing.T) {
	jsonData := `{
		"version": "3",
		"system": {},
		"os": {"architecture": "x86_64"},
		"pacman": {"packages": ["pacman"]}
	}`

	_, err := ParseRequest(strings.NewReader(jsonData))
	if err == nil {
		t.Fatal("expected error for missing system architecture")
	}
	if !strings.Contains(err.Error(), "system.architecture") {
		t.Errorf("expected system.architecture error, got: %v", err)
	}
}

func TestParseRequest_EmptyPackages(t *testing.T) {
	jsonData := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64"},
		"pacman": {"packages": []}
	}`

	_, err := ParseRequest(strings.NewReader(jsonData))
	if err == nil {
		t.Fatal("expected error for empty packages")
	}
	if !strings.Contains(err.Error(), "at least 1 package") {
		t.Errorf("expected packages error, got: %v", err)
	}
}

func TestValidateArchitectures(t *testing.T) {
	tests := []struct {
		name       string
		systemArch string
		osArch     string
		wantErr    bool
	}{
		// Valid x86_64 combinations
		{"x86_64/x86_64", "x86_64", "x86_64", false},
		{"x86_64/i686", "x86_64", "i686", false},
		{"x86_64/i586", "x86_64", "i586", false},
		{"x86_64_v2/x86_64", "x86_64_v2", "x86_64", false},
		{"x86_64_v3/x86_64", "x86_64_v3", "x86_64", false},
		{"x86_64_v4/x86_64", "x86_64_v4", "x86_64", false},

		// Valid i686 combinations
		{"i686/i686", "i686", "i686", false},
		{"i686/i586", "i686", "i586", false},

		// Valid ARM combinations
		{"aarch64/aarch64", "aarch64", "aarch64", false},
		{"aarch64/armv7h", "aarch64", "armv7h", false},
		{"armv7/armv7h", "armv7", "armv7h", false},
		{"armv7/armv6h", "armv7", "armv6h", false},
		{"armv6/armv6h", "armv6", "armv6h", false},
		{"armv5/arm", "armv5", "arm", false},

		// Valid RISC-V
		{"riscv64/riscv64", "riscv64", "riscv64", false},

		// Valid LoongArch
		{"loong64/loongarch64", "loong64", "loongarch64", false},

		// Invalid combinations
		{"x86_64/aarch64", "x86_64", "aarch64", true},
		{"aarch64/x86_64", "aarch64", "x86_64", true},
		{"i686/x86_64", "i686", "x86_64", true},
		{"unknown/x86_64", "unknown", "x86_64", true},
		{"x86_64/unknown", "x86_64", "unknown", true},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			err := validateArchitectures(tt.systemArch, tt.osArch)
			if (err != nil) != tt.wantErr {
				t.Errorf("validateArchitectures(%s, %s) error = %v, wantErr %v",
					tt.systemArch, tt.osArch, err, tt.wantErr)
			}
		})
	}
}

func TestDeduplicatePackages(t *testing.T) {
	req := &Request{
		Pacman: PacmanInfo{
			Packages: []string{"Pacman", "LINUX", "pacman", "linux", "Base"},
		},
	}

	result := req.DeduplicatePackages()

	if len(result) != 3 {
		t.Errorf("expected 3 unique packages, got %d", len(result))
	}

	// All should be lowercase
	for _, pkg := range result {
		if pkg != strings.ToLower(pkg) {
			t.Errorf("expected lowercase package name, got %s", pkg)
		}
	}
}

func TestValidatePackageNameLength(t *testing.T) {
	longName := strings.Repeat("a", 200)
	jsonData := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64"},
		"pacman": {"packages": ["` + longName + `"]}
	}`

	_, err := ParseRequest(strings.NewReader(jsonData))
	if err == nil {
		t.Fatal("expected error for long package name")
	}
	if !strings.Contains(err.Error(), "maximum length") {
		t.Errorf("expected length error, got: %v", err)
	}
}

func TestValidateEmptyPackageName(t *testing.T) {
	jsonData := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64"},
		"pacman": {"packages": ["pacman", "", "linux"]}
	}`

	_, err := ParseRequest(strings.NewReader(jsonData))
	if err == nil {
		t.Fatal("expected error for empty package name")
	}
	if !strings.Contains(err.Error(), "cannot be empty") {
		t.Errorf("expected empty name error, got: %v", err)
	}
}
