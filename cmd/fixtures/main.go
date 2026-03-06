package main

import (
	"context"
	"database/sql"
	"fmt"
	"log"
	"math"
	"math/rand/v2"
	"os"
	"time"

	"pkgstatsd/internal/config"
	"pkgstatsd/internal/database"
	"pkgstatsd/internal/ui/fun"
)

const (
	defaultMonths   = 100
	monthMultiplier = 100

	rngSeed1 int = 42
	rngSeed2 int = 42

	defaultBasePop = 0.005
	minPop         = 0.001
	maxPop         = 0.99
	noiseScale     = 0.10
)

type entityConfig struct {
	name    string
	basePop float64
	trend   float64
}

type fixtureTable struct {
	name       string
	sql        string
	entities   []entityConfig
	maxSamples int
}

func main() {
	cfg, err := config.Load()
	if err != nil {
		log.Fatal(err)
	}

	if err := run(cfg.Database); err != nil {
		log.Fatal(err)
	}
}

// buildPackageList merges configuredPackages with all packages from
// fun.Categories, assigning small defaults to any missing ones.
func buildPackageList() []entityConfig {
	seen := make(map[string]bool, len(configuredPackages))
	result := make([]entityConfig, 0, len(configuredPackages))

	for name, cfg := range configuredPackages {
		cfg.name = name
		result = append(result, cfg)
		seen[name] = true
	}

	for _, cat := range fun.Categories {
		for _, pkg := range cat.Packages {
			if !seen[pkg] {
				result = append(result, entityConfig{name: pkg, basePop: defaultBasePop})
				seen[pkg] = true
			}
		}
	}

	return result
}

func run(dbPath string) error {
	ctx := context.Background()

	_ = os.Remove(dbPath)

	db, err := database.New(dbPath)
	if err != nil {
		return fmt.Errorf("init database: %w", err)
	}
	defer func() { _ = db.Close() }()

	rng := rand.New(rand.NewPCG(uint64(rngSeed1), uint64(rngSeed2))) //nolint:gosec // intentionally deterministic

	monthList := generateMonths(defaultMonths)
	start := time.Now()

	tables := []fixtureTable{
		{"packages", "INSERT INTO package (name, month, count) VALUES (?, ?, ?)", buildPackageList(), 20000},
		{"countries", "INSERT INTO country (code, month, count) VALUES (?, ?, ?)", countries, 6000},
		{"mirrors", "INSERT INTO mirror (url, month, count) VALUES (?, ?, ?)", mirrors, 5000},
		{"system architectures", "INSERT INTO system_architecture (name, month, count) VALUES (?, ?, ?)", systemArchitectures, 20000},
		{"OS architectures", "INSERT INTO operating_system_architecture (name, month, count) VALUES (?, ?, ?)", osArchitectures, 20000},
		{"operating system IDs", "INSERT INTO operating_system_id (id, month, count) VALUES (?, ?, ?)", operatingSystemIDs, 15000},
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

func realisticCount(rng *rand.Rand, entity entityConfig, maxSamples, monthIndex int) int {
	pop := entity.basePop + entity.trend*float64(monthIndex)
	pop = math.Max(minPop, math.Min(maxPop, pop))

	noise := (rng.Float64()*2 - 1) * entity.basePop * noiseScale
	pop = math.Max(minPop, math.Min(maxPop, pop+noise))

	return max(1, int(pop*float64(maxSamples)))
}

func generateFixtures(ctx context.Context, db *sql.DB, rng *rand.Rand, months []int, table fixtureTable) error {
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

	// Iterate oldest-first so monthIndex 0 = oldest
	for i := len(months) - 1; i >= 0; i-- {
		monthIndex := len(months) - 1 - i
		for _, entity := range table.entities {
			c := realisticCount(rng, entity, table.maxSamples, monthIndex)
			if _, err := stmt.ExecContext(ctx, entity.name, months[i], c); err != nil {
				return err
			}
		}
	}

	return tx.Commit()
}
