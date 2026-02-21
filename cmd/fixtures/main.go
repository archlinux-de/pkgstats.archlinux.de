// Command fixtures generates test data for local development.
//
// Usage:
//
//	fixtures -db ./pkgstats.db [-months 3]
package main

import (
	"context"
	"database/sql"
	"flag"
	"fmt"
	"log"
	"math/rand/v2"
	"os"
	"time"

	"pkgstatsd/internal/database"
)

const (
	defaultMonths   = 3
	monthMultiplier = 100

	maxPackageCount     = 20000
	maxCountryCount     = 6000
	maxMirrorCount      = 5000
	maxSysArchCount     = 20000
	maxOSArchCount      = 20000
	maxOSIDCount        = 15000
	rngSeed1        int = 42
	rngSeed2        int = 42
)

// Sample package names (subset for testing).
var packageNames = []string{
	"pacman", "linux", "base", "systemd", "glibc", "bash", "coreutils",
	"filesystem", "gcc-libs", "ncurses", "readline", "zlib", "xz", "bzip2",
	"util-linux", "e2fsprogs", "shadow", "procps-ng", "psmisc", "findutils",
	"grep", "sed", "gawk", "diffutils", "less", "which", "file", "gettext",
	"licenses", "pacman-mirrorlist", "archlinux-keyring", "ca-certificates",
	"openssl", "curl", "wget", "git", "openssh", "sudo", "vim", "nano",
	"man-db", "man-pages", "texinfo", "groff", "perl", "python", "python-pip",
	"rust", "go", "nodejs", "npm", "docker", "docker-compose", "nginx",
	"firefox", "chromium", "thunderbird", "libreoffice-fresh", "gimp",
	"inkscape", "vlc", "mpv", "ffmpeg", "imagemagick", "htop", "neofetch",
	"zsh", "fish", "tmux", "screen", "rsync", "tar", "gzip", "unzip",
	"p7zip", "lz4", "zstd", "jq", "yq", "fzf", "ripgrep", "fd", "bat", "exa",
	"networkmanager", "wpa_supplicant", "iwd", "bluez", "pulseaudio", "pipewire",
	"mesa", "vulkan-icd-loader", "xorg-server", "xorg-xinit", "xorg-xrandr",
	"gnome-shell", "gnome-terminal", "nautilus", "gdm", "plasma-desktop",
	"kde-applications", "sddm", "i3-wm", "sway", "waybar", "alacritty", "kitty",
}

// ISO 3166-1 alpha-2 country codes (subset).
var countryCodes = []string{
	"DE", "US", "FR", "GB", "IT", "ES", "PL", "NL", "BE", "AT", "CH", "SE",
	"NO", "DK", "FI", "PT", "CZ", "RO", "HU", "IE", "GR", "SK", "BG", "HR",
	"SI", "LT", "LV", "EE", "CY", "LU", "MT", "UA", "RU", "BY", "MD", "RS",
	"BA", "MK", "AL", "ME", "XK", "TR", "IL", "SA", "AE", "QA", "KW", "BH",
	"OM", "JO", "LB", "SY", "IQ", "IR", "PK", "IN", "BD", "LK", "NP", "BT",
	"CN", "JP", "KR", "TW", "HK", "MO", "MN", "KP", "VN", "TH", "MY", "SG",
	"ID", "PH", "MM", "KH", "LA", "AU", "NZ", "FJ", "PG", "NC", "VU", "WS",
	"CA", "MX", "GT", "BZ", "SV", "HN", "NI", "CR", "PA", "CU", "JM", "HT",
	"DO", "PR", "BS", "BB", "TT", "BR", "AR", "CL", "CO", "VE", "PE", "EC",
	"BO", "PY", "UY", "GY", "SR", "GF", "ZA", "EG", "NG", "KE", "GH", "TZ",
}

// System architectures.
var systemArchitectures = []string{
	"x86_64", "i686", "aarch64", "armv7h", "armv6h", "riscv64",
}

// Operating system IDs (os-release ID values).
var operatingSystemIDs = []string{
	"arch", "artix", "endeavouros", "garuda", "manjaro", "cachyos",
	"parabola", "archarm", "blackarch", "archcraft",
}

// Mirror URLs (fictional for testing).
var mirrorURLs = []string{
	"https://mirror.rackspace.com/archlinux/",
	"https://mirrors.kernel.org/archlinux/",
	"https://mirror.leaseweb.net/archlinux/",
	"https://ftp.halifax.rwth-aachen.de/archlinux/",
	"https://mirror.pseudoform.org/",
	"https://archlinux.uk.mirror.allworldit.com/archlinux/",
	"https://mirror.cyberbits.eu/archlinux/",
	"https://arch.mirror.constant.com/",
	"https://mirror.osbeck.com/archlinux/",
	"https://america.mirror.pkgbuild.com/",
	"https://europe.mirror.pkgbuild.com/",
	"https://asia.mirror.pkgbuild.com/",
	"https://geo.mirror.pkgbuild.com/",
}

// fixtureTable defines a table to populate with fixture data.
type fixtureTable struct {
	name     string
	sql      string
	values   []string
	maxCount int
}

func main() {
	dbPath := flag.String("db", "./pkgstats.db", "SQLite database path")
	months := flag.Int("months", defaultMonths, "Number of months to generate (going back from current)")
	flag.Parse()

	if err := run(*dbPath, *months); err != nil {
		log.Fatal(err)
	}
}

func run(dbPath string, numMonths int) error {
	ctx := context.Background()

	// Remove existing database
	_ = os.Remove(dbPath)

	// Initialize database with migrations
	log.Printf("Initializing database at %s...\n", dbPath)
	db, err := database.New(dbPath)
	if err != nil {
		return fmt.Errorf("init database: %w", err)
	}
	defer func() { _ = db.Close() }()

	// Use seeded random for reproducibility (matches PHP's mt_srand(42))
	rng := rand.New(rand.NewPCG(uint64(rngSeed1), uint64(rngSeed2))) //nolint:gosec // intentionally deterministic for fixtures

	// Generate months (current month and going back)
	monthList := generateMonths(numMonths)
	log.Printf("Generating data for %d months: %v\n", len(monthList), monthList)

	// Generate fixtures
	start := time.Now()

	// OS architectures are a subset of system architectures
	osArchitectures := []string{"x86_64", "i686", "aarch64", "armv7h", "armv6h"}

	tables := []fixtureTable{
		{"packages", "INSERT INTO package (name, month, count) VALUES (?, ?, ?)", packageNames, maxPackageCount},
		{"countries", "INSERT INTO country (code, month, count) VALUES (?, ?, ?)", countryCodes, maxCountryCount},
		{"mirrors", "INSERT INTO mirror (url, month, count) VALUES (?, ?, ?)", mirrorURLs, maxMirrorCount},
		{"system architectures", "INSERT INTO system_architecture (name, month, count) VALUES (?, ?, ?)", systemArchitectures, maxSysArchCount},
		{"OS architectures", "INSERT INTO operating_system_architecture (name, month, count) VALUES (?, ?, ?)", osArchitectures, maxOSArchCount},
		{"operating system IDs", "INSERT INTO operating_system_id (id, month, count) VALUES (?, ?, ?)", operatingSystemIDs, maxOSIDCount},
	}

	for _, table := range tables {
		if err := generateFixtures(ctx, db, rng, monthList, table); err != nil {
			return fmt.Errorf("generate %s: %w", table.name, err)
		}
	}

	log.Printf("Fixtures generated in %s\n", time.Since(start).Round(time.Millisecond))
	return nil
}

func generateMonths(count int) []int {
	now := time.Now()
	months := make([]int, count)
	for i := range count {
		t := now.AddDate(0, -i, 0)
		months[i] = t.Year()*monthMultiplier + int(t.Month())
	}
	return months
}

func generateFixtures(ctx context.Context, db *sql.DB, rng *rand.Rand, months []int, table fixtureTable) error {
	log.Printf("Generating %s...\n", table.name)
	tx, err := db.BeginTx(ctx, nil)
	if err != nil {
		return err
	}
	defer func() { _ = tx.Rollback() }()

	stmt, err := tx.PrepareContext(ctx, table.sql)
	if err != nil {
		return err
	}
	defer func() { _ = stmt.Close() }()

	count := 0
	for _, month := range months {
		for _, value := range table.values {
			c := rng.IntN(table.maxCount) + 1
			if _, err := stmt.ExecContext(ctx, value, month, c); err != nil {
				return err
			}
			count++
		}
	}

	if err := tx.Commit(); err != nil {
		return err
	}
	log.Printf("  Inserted %d %s records\n", count, table.name)
	return nil
}
