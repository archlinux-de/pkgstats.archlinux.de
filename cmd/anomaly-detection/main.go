// Command anomaly-detection detects suspicious submission patterns in package statistics.
//
// Usage:
//
//	anomaly-detection -db ./pkgstats.db [-month 202601] [-expected-packages pkgstats,pacman]
//
// Exit codes:
//   - 0: No high-confidence anomalies detected
//   - 1: Minor anomalies detected (single mirror or architecture spike)
//   - 2: High-confidence anomalies detected (requires investigation)
package main

import (
	"context"
	"database/sql"
	"flag"
	"fmt"
	"math"
	"os"
	"regexp"
	"sort"
	"strings"
	"time"

	"pkgstats.archlinux.de/internal/database"
)

const (
	lookbackMonths                = 6
	minBaselineCount              = 100
	minCorrelationCount           = 1000
	growthThreshold               = 300.0
	extremeGrowthThreshold        = 1000.0
	basePackageDeviationThreshold = 1.5
	monthMultiplier               = 100
	exitCodeHighConfidence        = 2
	roundingFactor                = 100
	maxDisplayItems               = 10
	maxCorrelationDisplay         = 5
	maxCorrelationPackages        = 8
)

// Anomaly types.
type (
	GrowthAnomaly struct {
		Identifier    string
		Count         int
		BaselineAvg   float64
		GrowthPercent float64
	}

	Spike struct {
		Identifier string
		Count      int
	}

	CountCorrelation struct {
		Delta        int
		PackageCount int
		Packages     []string
	}

	PackageRatio struct {
		Name  string
		Count int
		Ratio float64
	}

	BasePackageResult struct {
		Median                 int
		Outliers               []PackageRatio
		PackagesAboveThreshold []PackageRatio
	}

	DetectionResult struct {
		CountCorrelations   []CountCorrelation
		NewPackageSpikes    []Spike
		MirrorAnomalies     []GrowthAnomaly
		NewMirrorSpikes     []Spike
		SystemArchAnomalies []GrowthAnomaly
		OSArchAnomalies     []GrowthAnomaly
		BasePackageResult   BasePackageResult
	}
)

func (r *BasePackageResult) HasAnomalies() bool {
	return len(r.Outliers) > 0 || len(r.PackagesAboveThreshold) > 0
}

func (r *DetectionResult) HasMirrorAnomalies() bool {
	return len(r.MirrorAnomalies) > 0 || len(r.NewMirrorSpikes) > 0
}

func (r *DetectionResult) HasArchitectureAnomalies() bool {
	return len(r.SystemArchAnomalies) > 0 || len(r.OSArchAnomalies) > 0
}

func (r *DetectionResult) HasExtremeMirrorGrowth() bool {
	for _, a := range r.MirrorAnomalies {
		if a.GrowthPercent > extremeGrowthThreshold {
			return true
		}
	}
	return false
}

func (r *DetectionResult) IsHighConfidence() bool {
	return r.BasePackageResult.HasAnomalies() ||
		(r.HasMirrorAnomalies() && r.HasArchitectureAnomalies()) ||
		r.HasExtremeMirrorGrowth()
}

func main() {
	dbPath := flag.String("db", "./pkgstats.db", "SQLite database path")
	monthFlag := flag.String("month", "", "Month to analyze (YYYYMM format, defaults to last month)")
	expectedPkgs := flag.String("expected-packages", "pkgstats,pacman", "Comma-separated list of expected base packages")
	flag.Parse()

	exitCode, err := run(*dbPath, *monthFlag, *expectedPkgs)
	if err != nil {
		fmt.Fprintf(os.Stderr, "Error: %v\n", err)
		os.Exit(1)
	}
	os.Exit(exitCode)
}

func run(dbPath, monthFlag, expectedPkgs string) (int, error) {
	ctx := context.Background()

	db, err := database.New(dbPath)
	if err != nil {
		return 1, fmt.Errorf("init database: %w", err)
	}
	defer func() { _ = db.Close() }()

	targetMonth, err := parseTargetMonth(monthFlag)
	if err != nil {
		return 1, err
	}

	baselineEnd := offsetMonth(targetMonth, -1)
	baselineStart := offsetMonth(targetMonth, -lookbackMonths)

	var expectedPackages []string
	if expectedPkgs != "" {
		expectedPackages = strings.Split(expectedPkgs, ",")
	}

	printHeader(targetMonth, baselineStart, baselineEnd)

	result, err := detect(ctx, db, targetMonth, baselineStart, baselineEnd, expectedPackages)
	if err != nil {
		return 1, fmt.Errorf("detect anomalies: %w", err)
	}

	renderResults(result)

	return determineExitCode(result), nil
}

func parseTargetMonth(monthFlag string) (int, error) {
	if monthFlag == "" {
		now := time.Now()
		prev := now.AddDate(0, -1, 0)
		return prev.Year()*monthMultiplier + int(prev.Month()), nil
	}

	matched, _ := regexp.MatchString(`^[0-9]{6}$`, monthFlag)
	if !matched {
		return 0, fmt.Errorf("month must be in YYYYMM format, got %q", monthFlag)
	}

	var month int
	if _, err := fmt.Sscanf(monthFlag, "%d", &month); err != nil {
		return 0, fmt.Errorf("parse month %q: %w", monthFlag, err)
	}
	return month, nil
}

func offsetMonth(yearMonth, months int) int {
	year := yearMonth / monthMultiplier
	month := yearMonth % monthMultiplier

	month += months
	for month <= 0 {
		year--
		month += 12
	}
	for month > 12 {
		year++
		month -= 12
	}

	return year*monthMultiplier + month
}

func printHeader(targetMonth, baselineStart, baselineEnd int) {
	fmt.Println("Anomaly Detection Report")
	fmt.Println("========================")
	fmt.Printf("Target month: %d\n", targetMonth)
	fmt.Printf("Baseline period: %d - %d (%d months)\n\n", baselineStart, baselineEnd, lookbackMonths)
}

func determineExitCode(result *DetectionResult) int {
	if result.IsHighConfidence() {
		return exitCodeHighConfidence
	}
	if result.HasMirrorAnomalies() || result.HasArchitectureAnomalies() {
		return 1
	}
	return 0
}

func detect(ctx context.Context, db *sql.DB, targetMonth, baselineStart, baselineEnd int, expectedPackages []string) (*DetectionResult, error) {
	previousMonth := offsetMonth(targetMonth, -1)

	countCorrelations, err := detectCountCorrelations(ctx, db, targetMonth, previousMonth)
	if err != nil {
		return nil, fmt.Errorf("count correlations: %w", err)
	}

	newPackageSpikes, err := detectNewSpikes(ctx, db, "package", "name", targetMonth, baselineStart)
	if err != nil {
		return nil, fmt.Errorf("new package spikes: %w", err)
	}

	mirrorAnomalies, err := detectGrowthAnomalies(ctx, db, "mirror", "url", targetMonth, baselineStart, baselineEnd)
	if err != nil {
		return nil, fmt.Errorf("mirror anomalies: %w", err)
	}

	newMirrorSpikes, err := detectNewSpikes(ctx, db, "mirror", "url", targetMonth, baselineStart)
	if err != nil {
		return nil, fmt.Errorf("new mirror spikes: %w", err)
	}

	systemArchAnomalies, err := detectGrowthAnomalies(ctx, db, "system_architecture", "name", targetMonth, baselineStart, baselineEnd)
	if err != nil {
		return nil, fmt.Errorf("system arch anomalies: %w", err)
	}

	osArchAnomalies, err := detectGrowthAnomalies(ctx, db, "operating_system_architecture", "name", targetMonth, baselineStart, baselineEnd)
	if err != nil {
		return nil, fmt.Errorf("os arch anomalies: %w", err)
	}

	basePackageResult, err := detectBasePackageAnomalies(ctx, db, targetMonth, expectedPackages)
	if err != nil {
		return nil, fmt.Errorf("base package anomalies: %w", err)
	}

	return &DetectionResult{
		CountCorrelations:   countCorrelations,
		NewPackageSpikes:    newPackageSpikes,
		MirrorAnomalies:     mirrorAnomalies,
		NewMirrorSpikes:     newMirrorSpikes,
		SystemArchAnomalies: systemArchAnomalies,
		OSArchAnomalies:     osArchAnomalies,
		BasePackageResult:   basePackageResult,
	}, nil
}

func detectCountCorrelations(ctx context.Context, db *sql.DB, targetMonth, previousMonth int) ([]CountCorrelation, error) {
	query := `
		WITH deltas AS (
			SELECT
				curr.name,
				curr.count - COALESCE(prev.count, 0) as delta
			FROM package curr
			LEFT JOIN package prev ON curr.name = prev.name AND prev.month = ?
			WHERE curr.month = ?
			  AND curr.count - COALESCE(prev.count, 0) >= ?
		)
		SELECT delta, GROUP_CONCAT(name) as packages, COUNT(*) as num_packages
		FROM deltas
		GROUP BY delta
		HAVING COUNT(*) >= 3
		ORDER BY delta DESC
		LIMIT 50`

	rows, err := db.QueryContext(ctx, query, previousMonth, targetMonth, minCorrelationCount)
	if err != nil {
		return nil, err
	}
	defer func() { _ = rows.Close() }()

	var results []CountCorrelation
	for rows.Next() {
		var delta, numPackages int
		var packages string
		if err := rows.Scan(&delta, &packages, &numPackages); err != nil {
			return nil, err
		}

		pkgList := strings.Split(packages, ",")
		sort.Strings(pkgList)

		results = append(results, CountCorrelation{
			Delta:        delta,
			PackageCount: numPackages,
			Packages:     pkgList,
		})
	}

	return results, rows.Err()
}

func detectNewSpikes(ctx context.Context, db *sql.DB, table, idColumn string, targetMonth, baselineStart int) ([]Spike, error) {
	//nolint:gosec // table/column names are hardcoded constants, not user input
	query := fmt.Sprintf(`
		SELECT t.%s as identifier, t.count
		FROM %s t
		WHERE t.month = ?
		  AND t.count >= ?
		  AND NOT EXISTS (
			  SELECT 1 FROM %s t2
			  WHERE t2.%s = t.%s AND t2.month >= ? AND t2.month < ?
		  )
		ORDER BY t.count DESC
		LIMIT 50`, idColumn, table, table, idColumn, idColumn)

	rows, err := db.QueryContext(ctx, query, targetMonth, minCorrelationCount, baselineStart, targetMonth)
	if err != nil {
		return nil, err
	}
	defer func() { _ = rows.Close() }()

	var results []Spike
	for rows.Next() {
		var identifier string
		var count int
		if err := rows.Scan(&identifier, &count); err != nil {
			return nil, err
		}
		results = append(results, Spike{Identifier: identifier, Count: count})
	}

	return results, rows.Err()
}

func detectGrowthAnomalies(ctx context.Context, db *sql.DB, table, idColumn string, targetMonth, baselineStart, baselineEnd int) ([]GrowthAnomaly, error) {
	//nolint:gosec // table/column names are hardcoded constants, not user input
	query := fmt.Sprintf(`
		WITH baseline AS (
			SELECT %s, AVG(count) as avg_count
			FROM %s
			WHERE month >= ? AND month <= ?
			GROUP BY %s
			HAVING COUNT(*) >= 3
		),
		target AS (
			SELECT %s, count FROM %s WHERE month = ?
		)
		SELECT
			t.%s as identifier,
			t.count as target_count,
			b.avg_count as baseline_avg,
			((CAST(t.count AS REAL) - b.avg_count) / b.avg_count) * 100 as growth_percent
		FROM target t
		JOIN baseline b ON t.%s = b.%s
		WHERE b.avg_count >= ?
		  AND ((CAST(t.count AS REAL) - b.avg_count) / b.avg_count) * 100 > ?
		ORDER BY growth_percent DESC
		LIMIT 50`,
		idColumn, table, idColumn,
		idColumn, table,
		idColumn,
		idColumn, idColumn)

	rows, err := db.QueryContext(ctx, query, baselineStart, baselineEnd, targetMonth, minBaselineCount, growthThreshold)
	if err != nil {
		return nil, err
	}
	defer func() { _ = rows.Close() }()

	var results []GrowthAnomaly
	for rows.Next() {
		var identifier string
		var count int
		var baselineAvg, growthPercent float64
		if err := rows.Scan(&identifier, &count, &baselineAvg, &growthPercent); err != nil {
			return nil, err
		}
		results = append(results, GrowthAnomaly{
			Identifier:    identifier,
			Count:         count,
			BaselineAvg:   math.Round(baselineAvg*roundingFactor) / roundingFactor,
			GrowthPercent: math.Round(growthPercent*roundingFactor) / roundingFactor,
		})
	}

	return results, rows.Err()
}

func detectBasePackageAnomalies(ctx context.Context, db *sql.DB, targetMonth int, expectedPackages []string) (BasePackageResult, error) {
	empty := BasePackageResult{}

	if len(expectedPackages) == 0 {
		return empty, nil
	}

	baseCounts, err := fetchExpectedPackageCounts(ctx, db, targetMonth, expectedPackages)
	if err != nil {
		return empty, err
	}

	if len(baseCounts) == 0 {
		return empty, nil
	}

	values := make([]int, 0, len(baseCounts))
	for _, v := range baseCounts {
		values = append(values, v)
	}

	median := calculateMedian(values)
	threshold := float64(median) * basePackageDeviationThreshold

	outliers := findBasePackageOutliers(baseCounts, median, threshold)
	aboveThreshold, err := findPackagesAboveBaseThreshold(ctx, db, targetMonth, median, threshold, expectedPackages)
	if err != nil {
		return empty, err
	}

	return BasePackageResult{
		Median:                 median,
		Outliers:               outliers,
		PackagesAboveThreshold: aboveThreshold,
	}, nil
}

func fetchExpectedPackageCounts(ctx context.Context, db *sql.DB, targetMonth int, expectedPackages []string) (map[string]int, error) {
	placeholders := make([]string, len(expectedPackages))
	args := make([]any, 0, len(expectedPackages)+1)
	args = append(args, targetMonth)
	for i, pkg := range expectedPackages {
		placeholders[i] = "?"
		args = append(args, pkg)
	}

	query := fmt.Sprintf("SELECT name, count FROM package WHERE month = ? AND name IN (%s)", strings.Join(placeholders, ",")) //nolint:gosec // placeholders are "?" literals, not user input

	rows, err := db.QueryContext(ctx, query, args...)
	if err != nil {
		return nil, err
	}
	defer func() { _ = rows.Close() }()

	counts := make(map[string]int)
	for rows.Next() {
		var name string
		var count int
		if err := rows.Scan(&name, &count); err != nil {
			return nil, err
		}
		counts[name] = count
	}

	return counts, rows.Err()
}

func calculateMedian(values []int) int {
	sort.Ints(values)
	n := len(values)
	mid := n / 2 //nolint:mnd // standard median calculation

	if n%2 == 0 { //nolint:mnd // standard median calculation
		return (values[mid-1] + values[mid]) / 2 //nolint:mnd // standard median calculation
	}
	return values[mid]
}

func findBasePackageOutliers(baseCounts map[string]int, median int, threshold float64) []PackageRatio {
	var outliers []PackageRatio
	for name, count := range baseCounts {
		if float64(count) > threshold {
			outliers = append(outliers, PackageRatio{
				Name:  name,
				Count: count,
				Ratio: math.Round(float64(count)/float64(median)*roundingFactor) / roundingFactor,
			})
		}
	}
	sort.Slice(outliers, func(i, j int) bool {
		return outliers[i].Count > outliers[j].Count
	})
	return outliers
}

func findPackagesAboveBaseThreshold(ctx context.Context, db *sql.DB, targetMonth, median int, threshold float64, expectedPackages []string) ([]PackageRatio, error) {
	placeholders := make([]string, len(expectedPackages))
	args := make([]any, 0, len(expectedPackages)+2) //nolint:mnd // month + threshold args
	args = append(args, targetMonth, threshold)
	for i, pkg := range expectedPackages {
		placeholders[i] = "?"
		args = append(args, pkg)
	}

	//nolint:gosec // placeholders are "?" literals, not user input
	query := fmt.Sprintf(`
		SELECT name, count
		FROM package
		WHERE month = ? AND count > ? AND name NOT IN (%s)
		ORDER BY count DESC
		LIMIT 50`, strings.Join(placeholders, ","))

	rows, err := db.QueryContext(ctx, query, args...)
	if err != nil {
		return nil, err
	}
	defer func() { _ = rows.Close() }()

	var results []PackageRatio
	for rows.Next() {
		var name string
		var count int
		if err := rows.Scan(&name, &count); err != nil {
			return nil, err
		}
		results = append(results, PackageRatio{
			Name:  name,
			Count: count,
			Ratio: math.Round(float64(count)/float64(median)*roundingFactor) / roundingFactor,
		})
	}

	return results, rows.Err()
}

func renderResults(result *DetectionResult) {
	renderBasePackageAnomalies(&result.BasePackageResult)
	renderGrowthAnomalies("Mirror Anomalies", result.MirrorAnomalies)
	renderSpikes("New Mirror Spikes", result.NewMirrorSpikes)
	renderArchitectureAnomalies(result)

	if result.IsHighConfidence() {
		renderCountCorrelations(result.CountCorrelations)
		renderSpikes("New Package Spikes", result.NewPackageSpikes)
	}

	renderSummary(result)
}

func renderBasePackageAnomalies(result *BasePackageResult) {
	if !result.HasAnomalies() {
		return
	}

	fmt.Println("Base Package Anomalies")
	fmt.Println("----------------------")
	fmt.Printf("Base package median: %d\n", result.Median)

	if len(result.Outliers) > 0 {
		fmt.Println("\nERROR: Base packages exceeding threshold - HIGHLY suspicious:")
		fmt.Printf("  %-40s %15s %10s\n", "Package", "Count", "Ratio")
		for _, p := range result.Outliers {
			fmt.Printf("  %-40s %15d %9.2fx\n", p.Name, p.Count, p.Ratio)
		}
	}

	if len(result.PackagesAboveThreshold) > 0 {
		fmt.Println("\nWARNING: Non-base packages exceeding base threshold:")
		fmt.Printf("  %-40s %15s %10s\n", "Package", "Count", "Ratio")
		limit := min(maxDisplayItems, len(result.PackagesAboveThreshold))
		for _, p := range result.PackagesAboveThreshold[:limit] {
			fmt.Printf("  %-40s %15d %9.2fx\n", p.Name, p.Count, p.Ratio)
		}
	}
	fmt.Println()
}

func renderGrowthAnomalies(title string, anomalies []GrowthAnomaly) {
	if len(anomalies) == 0 {
		return
	}

	fmt.Println(title)
	fmt.Println(strings.Repeat("-", len(title)))
	fmt.Printf("  %-60s %15s %15s %12s\n", "Identifier", "Count", "Baseline Avg", "Growth %")
	for _, a := range anomalies {
		fmt.Printf("  %-60s %15d %15.0f %+11.1f%%\n", a.Identifier, a.Count, a.BaselineAvg, a.GrowthPercent)
	}
	fmt.Println()
}

func renderSpikes(title string, spikes []Spike) {
	if len(spikes) == 0 {
		return
	}

	fmt.Println(title)
	fmt.Println(strings.Repeat("-", len(title)))
	fmt.Printf("  %-60s %15s\n", "Identifier", "Count")
	limit := min(maxDisplayItems, len(spikes))
	for _, s := range spikes[:limit] {
		fmt.Printf("  %-60s %15d\n", s.Identifier, s.Count)
	}
	fmt.Println()
}

func renderArchitectureAnomalies(result *DetectionResult) {
	if !result.HasArchitectureAnomalies() {
		return
	}

	fmt.Println("Architecture Anomalies")
	fmt.Println("----------------------")
	fmt.Printf("  %-10s %-30s %15s %15s %12s\n", "Type", "Architecture", "Count", "Baseline Avg", "Growth %")
	for _, a := range result.SystemArchAnomalies {
		fmt.Printf("  %-10s %-30s %15d %15.0f %+11.1f%%\n", "system", a.Identifier, a.Count, a.BaselineAvg, a.GrowthPercent)
	}
	for _, a := range result.OSArchAnomalies {
		fmt.Printf("  %-10s %-30s %15d %15.0f %+11.1f%%\n", "os", a.Identifier, a.Count, a.BaselineAvg, a.GrowthPercent)
	}
	fmt.Println()
}

func renderCountCorrelations(correlations []CountCorrelation) {
	if len(correlations) == 0 {
		return
	}

	fmt.Println("Suspicious Count Correlations")
	fmt.Println("-----------------------------")
	limit := min(maxCorrelationDisplay, len(correlations))
	for _, c := range correlations[:limit] {
		pkgs := c.Packages
		if len(pkgs) > maxCorrelationPackages {
			pkgs = append(pkgs[:maxCorrelationPackages], "...")
		}
		fmt.Printf("  Delta +%d: %d packages - %s\n", c.Delta, c.PackageCount, strings.Join(pkgs, ", "))
	}
	fmt.Println()
}

func renderSummary(result *DetectionResult) {
	switch {
	case result.IsHighConfidence():
		typeCount := 0
		if result.HasMirrorAnomalies() {
			typeCount++
		}
		if result.HasArchitectureAnomalies() {
			typeCount++
		}
		if result.BasePackageResult.HasAnomalies() {
			typeCount++
		}
		fmt.Printf("ERROR: High-confidence anomalies detected (%d types) - requires investigation\n", typeCount)
	case result.HasMirrorAnomalies() || result.HasArchitectureAnomalies():
		fmt.Println("WARNING: Minor anomalies detected (single mirror or architecture spike - may be legitimate)")
	default:
		fmt.Println("OK: No high-confidence anomalies detected")
	}

	baseCount := len(result.BasePackageResult.Outliers) + len(result.BasePackageResult.PackagesAboveThreshold)
	mirrorCount := len(result.MirrorAnomalies) + len(result.NewMirrorSpikes)
	archCount := len(result.SystemArchAnomalies) + len(result.OSArchAnomalies)

	fmt.Printf("  Base package anomalies: %d\n", baseCount)
	fmt.Printf("  Mirror anomalies: %d\n", mirrorCount)
	fmt.Printf("  Architecture anomalies: %d\n", archCount)
}
