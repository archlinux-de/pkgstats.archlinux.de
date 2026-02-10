package packages

import (
	"context"
	"database/sql"
	"fmt"
	"math"

	"pkgstats.archlinux.de/internal/database"
)

const (
	minPopularity       = 16
	nameLikeCondition   = ` AND name LIKE ?`
	popularityScale     = 10000
	popularityPrecision = 100
)

// Repository defines the interface for package data access.
type Repository interface {
	// FindByName returns popularity data for a single package.
	FindByName(ctx context.Context, name string, startMonth, endMonth int) (*PackagePopularity, error)

	// FindAll returns a paginated list of packages matching the query.
	FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*PackagePopularityList, error)

	// FindSeriesByName returns monthly popularity data for a package.
	FindSeriesByName(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*PackagePopularityList, error)
}

// SQLiteRepository implements Repository using SQLite.
type SQLiteRepository struct {
	db              *sql.DB
	monthlyMaxCache *database.MonthlySamplesCache
}

// NewSQLiteRepository creates a new SQLiteRepository.
func NewSQLiteRepository(db *sql.DB) *SQLiteRepository {
	return &SQLiteRepository{
		db:              db,
		monthlyMaxCache: database.NewMonthlySamplesCache(db, `SELECT month, MAX(count) FROM package GROUP BY month`),
	}
}

func (r *SQLiteRepository) FindByName(ctx context.Context, name string, startMonth, endMonth int) (*PackagePopularity, error) {
	// Get the package count
	var count int
	var query string
	var args []any

	if startMonth == endMonth {
		query = `SELECT COALESCE(SUM(count), 0) FROM package WHERE name = ? AND month = ?`
		args = []any{name, startMonth}
	} else {
		query = `SELECT COALESCE(SUM(count), 0) FROM package WHERE name = ? AND month >= ? AND month <= ?`
		args = []any{name, startMonth, endMonth}
	}

	if err := r.db.QueryRowContext(ctx, query, args...).Scan(&count); err != nil {
		return nil, fmt.Errorf("query package count: %w", err)
	}

	// Get samples (max count for the month range)
	samples, err := r.getMaxCount(ctx, startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get samples: %w", err)
	}

	popularity := calculatePopularity(count, samples)

	return &PackagePopularity{
		Name:       name,
		Samples:    samples,
		Count:      count,
		Popularity: popularity,
		StartMonth: startMonth,
		EndMonth:   endMonth,
	}, nil
}

func (r *SQLiteRepository) FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*PackagePopularityList, error) {
	// Get samples for the month range
	samples, err := r.getMaxCount(ctx, startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get samples: %w", err)
	}

	// Build query
	var sqlQuery string
	var countQuery string
	var args []any
	var countArgs []any

	if startMonth == endMonth {
		// Single month query
		sqlQuery = `
			SELECT name, count
			FROM package
			WHERE month = ? AND count >= ?`
		args = []any{startMonth, minPopularity}
		countArgs = []any{startMonth, minPopularity}

		if query != "" {
			sqlQuery += nameLikeCondition
			args = append(args, query+"%")
			countArgs = append(countArgs, query+"%")
		}

		sqlQuery += ` ORDER BY count DESC, name ASC LIMIT ? OFFSET ?`
		args = append(args, limit, offset)

		countQuery = `SELECT COUNT(*) FROM package WHERE month = ? AND count >= ?`
		if query != "" {
			countQuery += nameLikeCondition
		}
	} else {
		// Multi-month aggregation
		sqlQuery = `
			SELECT name, SUM(count) as total_count
			FROM package
			WHERE month >= ? AND month <= ?`
		args = []any{startMonth, endMonth}
		countArgs = []any{startMonth, endMonth}

		if query != "" {
			sqlQuery += nameLikeCondition
			args = append(args, query+"%")
			countArgs = append(countArgs, query+"%")
		}

		sqlQuery += ` GROUP BY name HAVING total_count >= ? ORDER BY total_count DESC, name ASC LIMIT ? OFFSET ?`
		args = append(args, minPopularity, limit, offset)

		countQuery = `
			SELECT COUNT(*) FROM (
				SELECT name FROM package
				WHERE month >= ? AND month <= ?`
		if query != "" {
			countQuery += nameLikeCondition
		}
		countQuery += ` GROUP BY name HAVING SUM(count) >= ?)`
		countArgs = append(countArgs, minPopularity)
	}

	// Get total count
	var total int
	if err := r.db.QueryRowContext(ctx, countQuery, countArgs...).Scan(&total); err != nil {
		return nil, fmt.Errorf("count packages: %w", err)
	}

	// Execute main query
	rows, err := r.db.QueryContext(ctx, sqlQuery, args...)
	if err != nil {
		return nil, fmt.Errorf("query packages: %w", err)
	}
	defer func() { _ = rows.Close() }()

	var packages []PackagePopularity
	for rows.Next() {
		var name string
		var count int
		if err := rows.Scan(&name, &count); err != nil {
			return nil, fmt.Errorf("scan package: %w", err)
		}

		packages = append(packages, PackagePopularity{
			Name:       name,
			Samples:    samples,
			Count:      count,
			Popularity: calculatePopularity(count, samples),
			StartMonth: startMonth,
			EndMonth:   endMonth,
		})
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("iterate packages: %w", err)
	}

	if packages == nil {
		packages = []PackagePopularity{}
	}

	return &PackagePopularityList{
		Total:               total,
		Count:               len(packages),
		PackagePopularities: packages,
		Limit:               limit,
		Offset:              offset,
		Query:               &query,
	}, nil
}

func (r *SQLiteRepository) FindSeriesByName(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*PackagePopularityList, error) {
	// Get total count
	countQuery := `
		SELECT COUNT(*) FROM package
		WHERE name = ? AND month >= ? AND month <= ?`
	var total int
	if err := r.db.QueryRowContext(ctx, countQuery, name, startMonth, endMonth).Scan(&total); err != nil {
		return nil, fmt.Errorf("count series: %w", err)
	}

	// Get monthly samples (max count per month)
	samplesMap, err := r.getMonthlyMaxCounts(ctx, startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get monthly samples: %w", err)
	}

	// Query monthly data
	sqlQuery := `
		SELECT month, count
		FROM package
		WHERE name = ? AND month >= ? AND month <= ?
		ORDER BY month ASC
		LIMIT ? OFFSET ?`

	rows, err := r.db.QueryContext(ctx, sqlQuery, name, startMonth, endMonth, limit, offset)
	if err != nil {
		return nil, fmt.Errorf("query series: %w", err)
	}
	defer func() { _ = rows.Close() }()

	var packages []PackagePopularity
	for rows.Next() {
		var month, count int
		if err := rows.Scan(&month, &count); err != nil {
			return nil, fmt.Errorf("scan series: %w", err)
		}

		samples := samplesMap[month]
		packages = append(packages, PackagePopularity{
			Name:       name,
			Samples:    samples,
			Count:      count,
			Popularity: calculatePopularity(count, samples),
			StartMonth: month,
			EndMonth:   month,
		})
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("iterate series: %w", err)
	}

	if packages == nil {
		packages = []PackagePopularity{}
	}

	return &PackagePopularityList{
		Total:               total,
		Count:               len(packages),
		PackagePopularities: packages,
		Limit:               limit,
		Offset:              offset,
		Query:               nil,
	}, nil
}

func (r *SQLiteRepository) getMaxCount(ctx context.Context, startMonth, endMonth int) (int, error) {
	monthlyCounts, err := r.getMonthlyMaxCounts(ctx, startMonth, endMonth)
	if err != nil {
		return 0, err
	}

	var total int
	for _, count := range monthlyCounts {
		total += count
	}
	return total, nil
}

func (r *SQLiteRepository) getMonthlyMaxCounts(_ context.Context, startMonth, endMonth int) (map[int]int, error) {
	return r.monthlyMaxCache.Get(startMonth, endMonth)
}

func calculatePopularity(count, samples int) float64 {
	if samples == 0 {
		return 0
	}
	// Round to 2 decimal places
	return math.Round(float64(count)/float64(samples)*popularityScale) / popularityPrecision
}
