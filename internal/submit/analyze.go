package submit

import (
	"context"
	"database/sql"
	"encoding/json"
	"flag"
	"fmt"
	"net/netip"
	"os"
	"sort"
	"time"

	"pkgstatsd/internal/config"
	"pkgstatsd/internal/database"
)

const minReplayPackageObservations = 10000

type replayGroup struct {
	Network                      string
	PayloadHash                  string
	Reports                      int
	PackageCount                 int
	ExtraPackageObservations     int
	AggregatePackageObservations int
}

// RunAnalyzeLog executes the analyze-submission-log subcommand. It reports
// exact payload replays from the same anonymized network that have a material
// impact on the selected month's package aggregates.
func RunAnalyzeLog(args []string, cfg config.Config) int {
	fs := flag.NewFlagSet("analyze-submission-log", flag.ExitOnError)
	monthFlag := fs.Int("month", currentMonth(), "Month to analyze (YYYYMM format)")
	_ = fs.Parse(args)

	if !validMonth(*monthFlag) {
		fmt.Fprintf(os.Stderr, "Error: month must be in YYYYMM format, got %d\n", *monthFlag)
		return 1
	}

	db, err := database.New(cfg.Database)
	if err != nil {
		fmt.Fprintf(os.Stderr, "Error: %v\n", err)
		return 1
	}
	defer func() { _ = db.Close() }()

	groups, total, err := findMaterialReplays(context.Background(), db, *monthFlag)
	if err != nil {
		fmt.Fprintf(os.Stderr, "Error: %v\n", err)
		return 1
	}

	printReplayReport(*monthFlag, total, groups)
	return 0
}

func currentMonth() int {
	now := time.Now()
	return now.Year()*monthMultiplier + int(now.Month())
}

func validMonth(month int) bool {
	return month >= 100001 && month%monthMultiplier >= 1 && month%monthMultiplier <= 12
}

func findMaterialReplays(ctx context.Context, db *sql.DB, month int) ([]replayGroup, int, error) {
	var total int
	if err := db.QueryRowContext(ctx,
		`SELECT COALESCE(SUM(count), 0) FROM package WHERE month = ?`, month,
	).Scan(&total); err != nil {
		return nil, 0, fmt.Errorf("query aggregate package observations: %w", err)
	}

	rows, err := db.QueryContext(ctx,
		`SELECT ip, payload_hash, payload FROM submission_log WHERE month = ?`, month,
	)
	if err != nil {
		return nil, 0, fmt.Errorf("query submission log: %w", err)
	}
	defer func() { _ = rows.Close() }()

	groups := make(map[string]*replayGroup)
	for rows.Next() {
		var ip, hash, payload string
		if err := rows.Scan(&ip, &hash, &payload); err != nil {
			return nil, 0, fmt.Errorf("scan submission log row: %w", err)
		}

		addr, err := netip.ParseAddr(ip)
		if err != nil {
			continue
		}

		var request Request
		if err := json.Unmarshal([]byte(payload), &request); err != nil {
			return nil, 0, fmt.Errorf("parse logged payload %s: %w", hash, err)
		}
		packageCount := len(request.DeduplicatePackages())

		network := AnonymizeIP(addr)
		key := network + "\x00" + hash
		group, ok := groups[key]
		if !ok {
			group = &replayGroup{
				Network:      network,
				PayloadHash:  hash,
				PackageCount: packageCount,
			}
			groups[key] = group
		}
		group.Reports++
	}
	if err := rows.Err(); err != nil {
		return nil, 0, fmt.Errorf("iterate submission log: %w", err)
	}

	material := make([]replayGroup, 0)
	for _, group := range groups {
		group.ExtraPackageObservations = (group.Reports - 1) * group.PackageCount
		group.AggregatePackageObservations = total
		if group.ExtraPackageObservations >= minReplayPackageObservations {
			material = append(material, *group)
		}
	}
	sort.Slice(material, func(i, j int) bool {
		return material[i].ExtraPackageObservations > material[j].ExtraPackageObservations
	})

	return material, total, nil
}

func printReplayReport(month, total int, groups []replayGroup) {
	fmt.Println("Submission Log Replay Report")
	fmt.Println("============================")
	fmt.Printf("Month: %d\n", month)
	fmt.Printf("Aggregate package observations: %d\n\n", total)

	if len(groups) == 0 {
		fmt.Printf("No exact replays added at least %d package observations.\n", minReplayPackageObservations)
		return
	}

	fmt.Printf("Exact replays adding at least %d package observations:\n", minReplayPackageObservations)
	fmt.Println("Network\tPayload\tReports\tExtra observations\tMonthly share")
	for _, group := range groups {
		share := 0.0
		if total > 0 {
			share = float64(group.ExtraPackageObservations) / float64(total) * 100
		}
		fmt.Printf("%s\t%s\t%d\t%d\t%.3f%%\n",
			group.Network,
			shortHash(group.PayloadHash),
			group.Reports,
			group.ExtraPackageObservations,
			share,
		)
	}
}

func shortHash(hash string) string {
	const hashPrefixLength = 12
	if len(hash) <= hashPrefixLength {
		return hash
	}
	return hash[:hashPrefixLength]
}
