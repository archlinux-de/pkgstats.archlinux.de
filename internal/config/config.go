package config

import "os"

type Config struct {
	Database      string
	GeoIPDatabase string
	Port          string
	Environment   string
}

func Load() Config {
	return Config{
		// @TODO add error handling
		Database:      getEnv("DATABASE", ""),
		GeoIPDatabase: getEnv("GEOIP_DATABASE", ""),
		Port:          getEnv("PORT", "8282"),
		Environment:   getEnv("ENVIRONMENT", ""),
	}
}

func getEnv(key, defaultValue string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return defaultValue
}
