package config

import (
	"errors"
	"os"
)

type Config struct {
	Database      string
	GeoIPDatabase string
	Port          string
	Environment   string
}

func Load() (Config, error) {
	cfg := Config{
		Database:      getEnv("DATABASE", ""),
		GeoIPDatabase: getEnv("GEOIP_DATABASE", ""),
		Port:          getEnv("PORT", "8282"),
		Environment:   getEnv("ENVIRONMENT", "production"),
	}

	if cfg.Database == "" {
		return Config{}, errors.New("DATABASE environment variable is required")
	}

	return cfg, nil
}

func (c Config) IsDevelopment() bool {
	return c.Environment == "development" || c.Environment == "test"
}

func getEnv(key, defaultValue string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return defaultValue
}
