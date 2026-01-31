package packages

import (
	"context"
	"database/sql"
	"fmt"
	"math"
)

const (
	minPopularity       = 16
	nameLikeCondition   = ` AND name LIKE ?`
	popularityScale     = 10000
	popularityPrecision = 100
)

type Repository interface {
	FindByName(ctx context.Context, name string, startMonth, endMonth int) (*PackagePopularity, error)
	FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*PackagePopularityList, error)
	FindSeriesByName(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*PackagePopularityList, error)
}

type SQLiteRepository struct {
	db *sql.DB
}

func NewSQLiteRepository(db *sql.DB) *SQLiteRepository {
	return &SQLiteRepository{db: db}
}

func (r *SQLiteRepository) FindByName(ctx context.Context, name string, startMonth, endMonth int) (*PackagePopularity, error) {
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
	samples, err := r.getMaxCount(ctx, startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get samples: %w", err)
	}

	var sqlQuery string
	var countQuery string
	var args []any
	var countArgs []any

	if startMonth == endMonth {
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

	var total int
	if err := r.db.QueryRowContext(ctx, countQuery, countArgs...).Scan(&total); err != nil {
		return nil, fmt.Errorf("count packages: %w", err)
	}

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
	countQuery := `
		SELECT COUNT(*) FROM package
		WHERE name = ? AND month >= ? AND month <= ?`
	var total int
	if err := r.db.QueryRowContext(ctx, countQuery, name, startMonth, endMonth).Scan(&total); err != nil {
		return nil, fmt.Errorf("count series: %w", err)
	}

	samplesMap, err := r.getMonthlyMaxCounts(ctx, startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get monthly samples: %w", err)
	}

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

func (r *SQLiteRepository) getMonthlyMaxCounts(ctx context.Context, startMonth, endMonth int) (map[int]int, error) {
	query := `
		SELECT month, MAX(count) as max_count
		FROM package
		WHERE month >= ? AND month <= ?
		GROUP BY month`

	rows, err := r.db.QueryContext(ctx, query, startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("query monthly max: %w", err)
	}
	defer func() { _ = rows.Close() }()

	result := make(map[int]int)
	for rows.Next() {
		var month, count int
		if err := rows.Scan(&month, &count); err != nil {
			return nil, fmt.Errorf("scan monthly max: %w", err)
		}
		result[month] = count
	}

	return result, rows.Err()
}

func calculatePopularity(count, samples int) float64 {
	if samples == 0 {
		return 0
	}
	// Round to 2 decimal places
	return math.Round(float64(count)/float64(samples)*popularityScale) / popularityPrecision
}
