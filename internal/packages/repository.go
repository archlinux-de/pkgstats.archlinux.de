package packages

import (
	"context"
	"database/sql"
	"fmt"

	"pkgstatsd/internal/database"
	"pkgstatsd/internal/popularity"
)

const (
	minPopularity     = 16
	nameLikeCondition = ` AND name LIKE ?`
)

type Repository interface {
	FindByName(ctx context.Context, name string, startMonth, endMonth int) (*PackagePopularity, error)
	FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*PackagePopularityList, error)
	FindSeriesByName(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*PackagePopularityList, error)
}

type SQLiteRepository struct {
	db              *sql.DB
	monthlyMaxCache *database.MonthlySamplesCache
}

func NewSQLiteRepository(db *sql.DB) *SQLiteRepository {
	return &SQLiteRepository{
		db:              db,
		monthlyMaxCache: database.NewMonthlySamplesCache(db, `SELECT month, MAX(count) FROM package GROUP BY month`),
	}
}

// monthRange returns the SQL WHERE fragment and bound args for a month range.
// When startMonth is 0, no lower bound is applied (all history up to endMonth).
func monthRange(startMonth, endMonth int) (clause string, args []any) {
	if startMonth == 0 {
		return "month <= ?", []any{endMonth}
	}

	return "month >= ? AND month <= ?", []any{startMonth, endMonth}
}

func (r *SQLiteRepository) FindByName(ctx context.Context, name string, startMonth, endMonth int) (*PackagePopularity, error) {
	var count int
	var query string
	var args []any

	if startMonth == endMonth {
		query = `SELECT COALESCE(SUM(count), 0) FROM package WHERE name = ? AND month = ?`
		args = []any{name, startMonth}
	} else {
		mClause, mArgs := monthRange(startMonth, endMonth)
		query = `SELECT COALESCE(SUM(count), 0) FROM package WHERE name = ? AND ` + mClause
		args = append([]any{name}, mArgs...)
	}

	//nolint:gosec // Query is safely constructed using fixed strings from monthRange and parameterized arguments
	if err := r.db.QueryRowContext(ctx, query, args...).Scan(&count); err != nil {
		return nil, fmt.Errorf("query package count: %w", err)
	}

	samples, err := r.getMaxCount(ctx, startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get samples: %w", err)
	}

	return &PackagePopularity{
		Name:       name,
		Samples:    samples,
		Count:      count,
		Popularity: popularity.CalculatePopularity(count, samples),
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
		mClause, mArgs := monthRange(startMonth, endMonth)
		sqlQuery = `
			SELECT name, SUM(count) as total_count
			FROM package
			WHERE ` + mClause
		args = mArgs
		countArgs = append([]any{}, mArgs...)

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
				WHERE ` + mClause
		if query != "" {
			countQuery += nameLikeCondition
		}
		countQuery += ` GROUP BY name HAVING SUM(count) >= ?)`
		countArgs = append(countArgs, minPopularity)
	}

	var total int
	if err := r.db.QueryRowContext(ctx, countQuery, countArgs...).Scan(&total); err != nil { //nolint:gosec // query is built from hardcoded strings and ? placeholders
		return nil, fmt.Errorf("count packages: %w", err)
	}

	rows, err := r.db.QueryContext(ctx, sqlQuery, args...) //nolint:gosec // query is built from hardcoded strings and ? placeholders
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
			Popularity: popularity.CalculatePopularity(count, samples),
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
	mClause, mArgs := monthRange(startMonth, endMonth)

	countQuery := `SELECT COUNT(*) FROM package WHERE name = ? AND ` + mClause
	var total int
	//nolint:gosec // countQuery is safely constructed using fixed strings from monthRange and parameterized arguments
	if err := r.db.QueryRowContext(ctx, countQuery, append([]any{name}, mArgs...)...).Scan(&total); err != nil {
		return nil, fmt.Errorf("count series: %w", err)
	}

	samplesMap, err := r.getMonthlyMaxCounts(ctx, startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get monthly samples: %w", err)
	}

	//nolint:gosec // sqlQuery is safely constructed using fixed strings from monthRange and parameterized arguments
	sqlQuery := `SELECT month, count FROM package WHERE name = ? AND ` + mClause + ` ORDER BY month ASC LIMIT ? OFFSET ?`

	//nolint:gosec // Safe execution of the securely constructed sqlQuery using parameterized arguments
	rows, err := r.db.QueryContext(ctx, sqlQuery, append(append([]any{name}, mArgs...), limit, offset)...)
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
			Popularity: popularity.CalculatePopularity(count, samples),
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
	return r.monthlyMaxCache.Get(ctx, startMonth, endMonth)
}
