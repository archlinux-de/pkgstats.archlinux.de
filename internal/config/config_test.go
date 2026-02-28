package config

import (
	"slices"
	"testing"
)

func TestLoadExpectedPackages_Default(t *testing.T) {
	t.Setenv("EXPECTED_PACKAGES", "")

	packages, err := loadExpectedPackages()
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if !slices.Equal(packages, []string{"pkgstats", "pacman"}) {
		t.Errorf("expected default packages, got %v", packages)
	}
}

func TestLoadExpectedPackages_FromEnv(t *testing.T) {
	t.Setenv("EXPECTED_PACKAGES", `["pkgstats","pacman","linux"]`)

	packages, err := loadExpectedPackages()
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if !slices.Equal(packages, []string{"pkgstats", "pacman", "linux"}) {
		t.Errorf("expected [pkgstats pacman linux], got %v", packages)
	}
}

func TestLoadExpectedPackages_InvalidJSON(t *testing.T) {
	t.Setenv("EXPECTED_PACKAGES", "not-json")

	_, err := loadExpectedPackages()
	if err == nil {
		t.Error("expected error for invalid JSON, got nil")
	}
}
