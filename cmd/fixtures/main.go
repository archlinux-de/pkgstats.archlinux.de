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

	"pkgstats.archlinux.de/internal/database"
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

func main() {
	dbPath := flag.String("db", "./pkgstats.db", "SQLite database path")
	months := flag.Int("months", 3, "Number of months to generate (going back from current)")
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
	rng := rand.New(rand.NewPCG(42, 42))

	// Generate months (current month and going back)
	monthList := generateMonths(numMonths)
	log.Printf("Generating data for %d months: %v\n", len(monthList), monthList)

	// Generate fixtures
	start := time.Now()

	if err := generatePackages(ctx, db, rng, monthList); err != nil {
		return fmt.Errorf("generate packages: %w", err)
	}

	if err := generateCountries(ctx, db, rng, monthList); err != nil {
		return fmt.Errorf("generate countries: %w", err)
	}

	if err := generateMirrors(ctx, db, rng, monthList); err != nil {
		return fmt.Errorf("generate mirrors: %w", err)
	}

	if err := generateSystemArchitectures(ctx, db, rng, monthList); err != nil {
		return fmt.Errorf("generate system architectures: %w", err)
	}

	if err := generateOSArchitectures(ctx, db, rng, monthList); err != nil {
		return fmt.Errorf("generate OS architectures: %w", err)
	}

	log.Printf("Fixtures generated in %s\n", time.Since(start).Round(time.Millisecond))
	return nil
}

func generateMonths(count int) []int {
	now := time.Now()
	months := make([]int, count)
	for i := range count {
		t := now.AddDate(0, -i, 0)
		months[i] = t.Year()*100 + int(t.Month())
	}
	return months
}

func generatePackages(ctx context.Context, db *sql.DB, rng *rand.Rand, months []int) error {
	log.Println("Generating packages...")
	tx, err := db.BeginTx(ctx, nil)
	if err != nil {
		return err
	}
	defer func() { _ = tx.Rollback() }()

	stmt, err := tx.PrepareContext(ctx, "INSERT INTO package (name, month, count) VALUES (?, ?, ?)")
	if err != nil {
		return err
	}
	defer func() { _ = stmt.Close() }()

	count := 0
	for _, month := range months {
		for _, name := range packageNames {
			c := rng.IntN(20000) + 1
			if _, err := stmt.ExecContext(ctx, name, month, c); err != nil {
				return err
			}
			count++
		}
	}

	if err := tx.Commit(); err != nil {
		return err
	}
	log.Printf("  Inserted %d package records\n", count)
	return nil
}

func generateCountries(ctx context.Context, db *sql.DB, rng *rand.Rand, months []int) error {
	log.Println("Generating countries...")
	tx, err := db.BeginTx(ctx, nil)
	if err != nil {
		return err
	}
	defer func() { _ = tx.Rollback() }()

	stmt, err := tx.PrepareContext(ctx, "INSERT INTO country (code, month, count) VALUES (?, ?, ?)")
	if err != nil {
		return err
	}
	defer func() { _ = stmt.Close() }()

	count := 0
	for _, month := range months {
		for _, code := range countryCodes {
			c := rng.IntN(6000) + 1
			if _, err := stmt.ExecContext(ctx, code, month, c); err != nil {
				return err
			}
			count++
		}
	}

	if err := tx.Commit(); err != nil {
		return err
	}
	log.Printf("  Inserted %d country records\n", count)
	return nil
}

func generateMirrors(ctx context.Context, db *sql.DB, rng *rand.Rand, months []int) error {
	log.Println("Generating mirrors...")
	tx, err := db.BeginTx(ctx, nil)
	if err != nil {
		return err
	}
	defer func() { _ = tx.Rollback() }()

	stmt, err := tx.PrepareContext(ctx, "INSERT INTO mirror (url, month, count) VALUES (?, ?, ?)")
	if err != nil {
		return err
	}
	defer func() { _ = stmt.Close() }()

	count := 0
	for _, month := range months {
		for _, url := range mirrorURLs {
			c := rng.IntN(5000) + 1
			if _, err := stmt.ExecContext(ctx, url, month, c); err != nil {
				return err
			}
			count++
		}
	}

	if err := tx.Commit(); err != nil {
		return err
	}
	log.Printf("  Inserted %d mirror records\n", count)
	return nil
}

func generateSystemArchitectures(ctx context.Context, db *sql.DB, rng *rand.Rand, months []int) error {
	log.Println("Generating system architectures...")
	tx, err := db.BeginTx(ctx, nil)
	if err != nil {
		return err
	}
	defer func() { _ = tx.Rollback() }()

	stmt, err := tx.PrepareContext(ctx, "INSERT INTO system_architecture (name, month, count) VALUES (?, ?, ?)")
	if err != nil {
		return err
	}
	defer func() { _ = stmt.Close() }()

	count := 0
	for _, month := range months {
		for _, name := range systemArchitectures {
			c := rng.IntN(20000) + 1
			if _, err := stmt.ExecContext(ctx, name, month, c); err != nil {
				return err
			}
			count++
		}
	}

	if err := tx.Commit(); err != nil {
		return err
	}
	log.Printf("  Inserted %d system architecture records\n", count)
	return nil
}

func generateOSArchitectures(ctx context.Context, db *sql.DB, rng *rand.Rand, months []int) error {
	log.Println("Generating OS architectures...")
	tx, err := db.BeginTx(ctx, nil)
	if err != nil {
		return err
	}
	defer func() { _ = tx.Rollback() }()

	stmt, err := tx.PrepareContext(ctx, "INSERT INTO operating_system_architecture (name, month, count) VALUES (?, ?, ?)")
	if err != nil {
		return err
	}
	defer func() { _ = stmt.Close() }()

	// OS architectures are a subset
	osArchitectures := []string{"x86_64", "i686", "aarch64", "armv7h", "armv6h"}

	count := 0
	for _, month := range months {
		for _, name := range osArchitectures {
			c := rng.IntN(20000) + 1
			if _, err := stmt.ExecContext(ctx, name, month, c); err != nil {
				return err
			}
			count++
		}
	}

	if err := tx.Commit(); err != nil {
		return err
	}
	log.Printf("  Inserted %d OS architecture records\n", count)
	return nil
}
