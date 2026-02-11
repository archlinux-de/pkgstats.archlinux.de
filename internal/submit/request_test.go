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

func TestParseRequest_TooManyPackages(t *testing.T) {
	pkgs := make([]string, 20001)
	for i := range pkgs {
		pkgs[i] = `"pkg` + strings.Repeat("a", 5) + `"`
	}
	jsonData := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {"architecture": "x86_64"},
		"pacman": {"packages": [` + strings.Join(pkgs, ",") + `]}
	}`

	_, err := ParseRequest(strings.NewReader(jsonData))
	if err == nil {
		t.Fatal("expected error for too many packages")
	}
	if !strings.Contains(err.Error(), "at most") {
		t.Errorf("expected max packages error, got: %v", err)
	}
}

func TestParseRequest_MissingOSArchitecture(t *testing.T) {
	jsonData := `{
		"version": "3",
		"system": {"architecture": "x86_64"},
		"os": {},
		"pacman": {"packages": ["pacman"]}
	}`

	_, err := ParseRequest(strings.NewReader(jsonData))
	if err == nil {
		t.Fatal("expected error for missing OS architecture")
	}
	if !strings.Contains(err.Error(), "os.architecture") {
		t.Errorf("expected os.architecture error, got: %v", err)
	}
}

func TestParseRequest_InvalidPackageName(t *testing.T) {
	tests := []struct {
		name    string
		pkgName string
	}{
		{"starts with dash", "-pkgstats"},
		{"starts with dot", ".hidden"},
		{"starts with at", "@scope"},
		{"contains space", "my package"},
		{"contains slash", "foo/bar"},
		{"contains hash", "pkg#1"},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			jsonData := `{
				"version": "3",
				"system": {"architecture": "x86_64"},
				"os": {"architecture": "x86_64"},
				"pacman": {"packages": ["` + tt.pkgName + `"]}
			}`

			_, err := ParseRequest(strings.NewReader(jsonData))
			if err == nil {
				t.Fatalf("expected error for invalid package name %q", tt.pkgName)
			}
			if !strings.Contains(err.Error(), "invalid package name") {
				t.Errorf("expected invalid package name error, got: %v", err)
			}
		})
	}
}

func TestParseRequest_ValidPackageNames(t *testing.T) {
	validNames := []string{
		"pacman",
		"linux",
		"base",
		"lib32-glibc",
		"python-numpy",
		"ttf-dejavu",
		"r8168-lts",
		"xorg-server",
		"c-ares",
		"gcc10",
		"lib32-mesa",
		"package+extra",
		"foo:bar",
		"name@version",
		"dotted.name",
	}

	for _, name := range validNames {
		t.Run(name, func(t *testing.T) {
			jsonData := `{
				"version": "3",
				"system": {"architecture": "x86_64"},
				"os": {"architecture": "x86_64"},
				"pacman": {"packages": ["` + name + `"]}
			}`

			_, err := ParseRequest(strings.NewReader(jsonData))
			if err != nil {
				t.Fatalf("unexpected error for valid package name %q: %v", name, err)
			}
		})
	}
}

func TestParseRequest_InvalidJSON(t *testing.T) {
	_, err := ParseRequest(strings.NewReader(`{invalid json`))
	if err == nil {
		t.Fatal("expected error for invalid JSON")
	}
	if !strings.Contains(err.Error(), "invalid JSON") {
		t.Errorf("expected JSON error, got: %v", err)
	}
}

func TestParseRequest_UnsupportedVersions(t *testing.T) {
	versions := []string{"", "a", "42", "3.0.0", "3.1", "2.4", "2", "1", "4", "0"}

	for _, v := range versions {
		t.Run("version_"+v, func(t *testing.T) {
			jsonData := `{
				"version": "` + v + `",
				"system": {"architecture": "x86_64"},
				"os": {"architecture": "x86_64"},
				"pacman": {"packages": ["pacman"]}
			}`

			_, err := ParseRequest(strings.NewReader(jsonData))
			if err == nil {
				t.Fatalf("expected error for version %q", v)
			}
			if !strings.Contains(err.Error(), "version") {
				t.Errorf("expected version error, got: %v", err)
			}
		})
	}
}

func TestValidateArchitectures_AllValid(t *testing.T) {
	tests := []struct {
		systemArch string
		osArch     string
	}{
		// x86_64 system -> x86_64, i686, i586
		{"x86_64", "x86_64"},
		{"x86_64", "i686"},
		{"x86_64", "i586"},
		{"x86_64_v2", "x86_64"},
		{"x86_64_v2", "i686"},
		{"x86_64_v2", "i586"},
		{"x86_64_v3", "x86_64"},
		{"x86_64_v3", "i686"},
		{"x86_64_v3", "i586"},
		{"x86_64_v4", "x86_64"},
		{"x86_64_v4", "i686"},
		{"x86_64_v4", "i586"},

		// i686 system -> i686, i586
		{"i686", "i686"},
		{"i686", "i586"},

		// i586 system -> i586
		{"i586", "i586"},

		// ARM combinations
		{"aarch64", "aarch64"},
		{"aarch64", "armv7h"},
		{"aarch64", "armv6h"},
		{"aarch64", "armv7l"},
		{"aarch64", "armv6l"},
		{"aarch64", "arm"},
		{"aarch64", "armv5tel"},
		{"armv7", "armv7h"},
		{"armv7", "armv6h"},
		{"armv7", "armv7l"},
		{"armv7", "armv6l"},
		{"armv7", "arm"},
		{"armv7", "armv5tel"},
		{"armv6", "armv6h"},
		{"armv6", "armv6l"},
		{"armv6", "arm"},
		{"armv6", "armv5tel"},
		{"armv5", "arm"},
		{"armv5", "armv5tel"},

		// RISC-V
		{"riscv64", "riscv64"},

		// LoongArch
		{"loong64", "loongarch64"},
	}

	for _, tt := range tests {
		t.Run(tt.systemArch+"/"+tt.osArch, func(t *testing.T) {
			if err := validateArchitectures(tt.systemArch, tt.osArch); err != nil {
				t.Errorf("expected valid, got error: %v", err)
			}
		})
	}
}

func TestValidateArchitectures_AllInvalid(t *testing.T) {
	tests := []struct {
		systemArch string
		osArch     string
	}{
		// Invalid system architectures
		{"unknown", "x86_64"},
		{"arm", "arm"},
		{"armv7h", "armv7h"},
		{"loongarch64", "loongarch64"},

		// Invalid OS architectures
		{"x86_64", "aarch64"},
		{"x86_64", "riscv64"},
		{"aarch64", "x86_64"},
		{"i686", "x86_64"},
		{"i586", "i686"},
		{"riscv64", "x86_64"},
	}

	for _, tt := range tests {
		t.Run(tt.systemArch+"/"+tt.osArch, func(t *testing.T) {
			if err := validateArchitectures(tt.systemArch, tt.osArch); err == nil {
				t.Error("expected error for invalid architecture combination")
			}
		})
	}
}

func TestValidateExpectedPackages(t *testing.T) {
	tests := []struct {
		name       string
		packages   []string
		expected   []string
		maxMissing float64
		wantErr    bool
	}{
		{"both present", []string{"pacman", "pkgstats", "linux"}, []string{"pkgstats", "pacman"}, 0.35, false},
		{"case insensitive", []string{"Pacman", "PkgStats", "linux"}, []string{"pkgstats", "pacman"}, 0.35, false},
		{"all missing", []string{"linux", "base"}, []string{"pkgstats", "pacman"}, 0.35, true},
		{"one missing within threshold", []string{"pacman", "linux"}, []string{"pkgstats", "pacman"}, 0.50, false},
		{"one missing exceeds threshold", []string{"linux", "base"}, []string{"pkgstats", "pacman"}, 0.35, true},
		{"empty expected", []string{"linux"}, []string{}, 0.35, false},
		{"threshold edge exactly equal", []string{"pacman", "linux"}, []string{"pkgstats", "pacman"}, 0.5, false},
		{"threshold edge just over", []string{"linux", "base"}, []string{"pkgstats", "pacman", "base"}, 0.33, true},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			err := ValidateExpectedPackages(tt.packages, tt.expected, tt.maxMissing)
			if (err != nil) != tt.wantErr {
				t.Errorf("ValidateExpectedPackages() error = %v, wantErr %v", err, tt.wantErr)
			}
		})
	}
}
