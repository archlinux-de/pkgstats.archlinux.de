package config

import (
	"encoding/json"
	"errors"
	"fmt"
	"os"
)

var defaultExpectedPackages = []string{"pkgstats", "pacman"}

type Config struct {
	Database         string
	GeoIPDatabase    string
	Port             string
	ExpectedPackages []string
}

func Load() (Config, error) {
	expectedPackages, err := loadExpectedPackages()
	if err != nil {
		return Config{}, err
	}

	cfg := Config{
		Database:         getEnv("DATABASE", ""),
		GeoIPDatabase:    getEnv("GEOIP_DATABASE", ""),
		Port:             getEnv("PORT", "8282"),
		ExpectedPackages: expectedPackages,
	}

	if cfg.Database == "" {
		return Config{}, errors.New("DATABASE environment variable is required")
	}

	return cfg, nil
}

func loadExpectedPackages() ([]string, error) {
	envVal := os.Getenv("EXPECTED_PACKAGES")
	if envVal == "" {
		return defaultExpectedPackages, nil
	}

	var packages []string
	if err := json.Unmarshal([]byte(envVal), &packages); err != nil {
		return nil, fmt.Errorf("invalid EXPECTED_PACKAGES: %w", err)
	}

	return packages, nil
}

func getEnv(key, defaultValue string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return defaultValue
}
