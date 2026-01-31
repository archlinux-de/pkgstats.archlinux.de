package countries

import (
	"context"
	"database/sql"
	"fmt"
	"math"
)

const (
	codeLikeCondition   = ` AND code LIKE ?`
	popularityScale     = 10000
	popularityPrecision = 100
)

// Repository defines the interface for country data access.
type Repository interface {
	FindByCode(ctx context.Context, code string, startMonth, endMonth int) (*CountryPopularity, error)
	FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*CountryPopularityList, error)
	FindSeriesByCode(ctx context.Context, code string, startMonth, endMonth, limit, offset int) (*CountryPopularityList, error)
}

// SQLiteRepository implements Repository using SQLite.
type SQLiteRepository struct {
	db *sql.DB
}

// NewSQLiteRepository creates a new SQLiteRepository.
func NewSQLiteRepository(db *sql.DB) *SQLiteRepository {
	return &SQLiteRepository{db: db}
}

func (r *SQLiteRepository) FindByCode(ctx context.Context, code string, startMonth, endMonth int) (*CountryPopularity, error) {
	var count int
	query := `SELECT COALESCE(SUM(count), 0) FROM country WHERE code = ? AND month >= ? AND month <= ?`

	if err := r.db.QueryRowContext(ctx, query, code, startMonth, endMonth).Scan(&count); err != nil {
		return nil, fmt.Errorf("query country count: %w", err)
	}

	samples, err := r.getSamples(ctx, startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get samples: %w", err)
	}

	return &CountryPopularity{
		Code:       code,
		Samples:    samples,
		Count:      count,
		Popularity: calculatePopularity(count, samples),
		StartMonth: startMonth,
		EndMonth:   endMonth,
	}, nil
}

func (r *SQLiteRepository) FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*CountryPopularityList, error) {
	samples, err := r.getSamples(ctx, startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get samples: %w", err)
	}

	// Build query
	var sqlQuery string
	var countQuery string
	var args []any
	var countArgs []any

	sqlQuery = `
		SELECT code, SUM(count) as total_count
		FROM country
		WHERE month >= ? AND month <= ?`
	args = []any{startMonth, endMonth}
	countArgs = []any{startMonth, endMonth}

	if query != "" {
		sqlQuery += codeLikeCondition
		args = append(args, query+"%")
		countArgs = append(countArgs, query+"%")
	}

	sqlQuery += ` GROUP BY code ORDER BY total_count DESC, code ASC LIMIT ? OFFSET ?`
	args = append(args, limit, offset)

	countQuery = `SELECT COUNT(DISTINCT code) FROM country WHERE month >= ? AND month <= ?`
	if query != "" {
		countQuery += codeLikeCondition
	}

	var total int
	if err := r.db.QueryRowContext(ctx, countQuery, countArgs...).Scan(&total); err != nil {
		return nil, fmt.Errorf("count countries: %w", err)
	}

	rows, err := r.db.QueryContext(ctx, sqlQuery, args...)
	if err != nil {
		return nil, fmt.Errorf("query countries: %w", err)
	}
	defer func() { _ = rows.Close() }()

	var countries []CountryPopularity
	for rows.Next() {
		var code string
		var count int
		if err := rows.Scan(&code, &count); err != nil {
			return nil, fmt.Errorf("scan country: %w", err)
		}

		countries = append(countries, CountryPopularity{
			Code:       code,
			Samples:    samples,
			Count:      count,
			Popularity: calculatePopularity(count, samples),
			StartMonth: startMonth,
			EndMonth:   endMonth,
		})
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("iterate countries: %w", err)
	}

	if countries == nil {
		countries = []CountryPopularity{}
	}

	return &CountryPopularityList{
		Total:               total,
		Count:               len(countries),
		CountryPopularities: countries,
		Limit:               limit,
		Offset:              offset,
		Query:               &query,
	}, nil
}

func (r *SQLiteRepository) FindSeriesByCode(ctx context.Context, code string, startMonth, endMonth, limit, offset int) (*CountryPopularityList, error) {
	countQuery := `SELECT COUNT(*) FROM country WHERE code = ? AND month >= ? AND month <= ?`
	var total int
	if err := r.db.QueryRowContext(ctx, countQuery, code, startMonth, endMonth).Scan(&total); err != nil {
		return nil, fmt.Errorf("count series: %w", err)
	}

	samplesMap, err := r.getMonthlySamples(ctx, startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get monthly samples: %w", err)
	}

	sqlQuery := `
		SELECT month, count
		FROM country
		WHERE code = ? AND month >= ? AND month <= ?
		ORDER BY month ASC
		LIMIT ? OFFSET ?`

	rows, err := r.db.QueryContext(ctx, sqlQuery, code, startMonth, endMonth, limit, offset)
	if err != nil {
		return nil, fmt.Errorf("query series: %w", err)
	}
	defer func() { _ = rows.Close() }()

	var countries []CountryPopularity
	for rows.Next() {
		var month, count int
		if err := rows.Scan(&month, &count); err != nil {
			return nil, fmt.Errorf("scan series: %w", err)
		}

		samples := samplesMap[month]
		countries = append(countries, CountryPopularity{
			Code:       code,
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

	if countries == nil {
		countries = []CountryPopularity{}
	}

	return &CountryPopularityList{
		Total:               total,
		Count:               len(countries),
		CountryPopularities: countries,
		Limit:               limit,
		Offset:              offset,
		Query:               nil,
	}, nil
}

func (r *SQLiteRepository) getSamples(ctx context.Context, startMonth, endMonth int) (int, error) {
	monthlySamples, err := r.getMonthlySamples(ctx, startMonth, endMonth)
	if err != nil {
		return 0, err
	}

	var total int
	for _, count := range monthlySamples {
		total += count
	}
	return total, nil
}

func (r *SQLiteRepository) getMonthlySamples(ctx context.Context, startMonth, endMonth int) (map[int]int, error) {
	// Samples = sum of all country counts per month (total submissions)
	query := `
		SELECT month, SUM(count) as total
		FROM country
		WHERE month >= ? AND month <= ?
		GROUP BY month`

	rows, err := r.db.QueryContext(ctx, query, startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("query monthly samples: %w", err)
	}
	defer func() { _ = rows.Close() }()

	result := make(map[int]int)
	for rows.Next() {
		var month, count int
		if err := rows.Scan(&month, &count); err != nil {
			return nil, fmt.Errorf("scan monthly samples: %w", err)
		}
		result[month] = count
	}

	return result, rows.Err()
}

func calculatePopularity(count, samples int) float64 {
	if samples == 0 {
		return 0
	}
	return math.Round(float64(count)/float64(samples)*popularityScale) / popularityPrecision
}
