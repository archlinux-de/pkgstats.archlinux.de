package systemarchitectures

import (
	"context"
	"database/sql"
	"fmt"
	"math"
)

const (
	nameLikeCondition   = ` AND name LIKE ?`
	popularityScale     = 10000
	popularityPrecision = 100
)

// Repository defines the interface for system architecture data access.
type Repository interface {
	FindByName(ctx context.Context, name string, startMonth, endMonth int) (*SystemArchitecturePopularity, error)
	FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*SystemArchitecturePopularityList, error)
	FindSeriesByName(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*SystemArchitecturePopularityList, error)
}

// SQLiteRepository implements Repository using SQLite.
type SQLiteRepository struct {
	db *sql.DB
}

// NewSQLiteRepository creates a new SQLiteRepository.
func NewSQLiteRepository(db *sql.DB) *SQLiteRepository {
	return &SQLiteRepository{db: db}
}

func (r *SQLiteRepository) FindByName(ctx context.Context, name string, startMonth, endMonth int) (*SystemArchitecturePopularity, error) {
	var count int
	query := `SELECT COALESCE(SUM(count), 0) FROM system_architecture WHERE name = ? AND month >= ? AND month <= ?`

	if err := r.db.QueryRowContext(ctx, query, name, startMonth, endMonth).Scan(&count); err != nil {
		return nil, fmt.Errorf("query architecture count: %w", err)
	}

	samples, err := r.getSamples(ctx, startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get samples: %w", err)
	}

	return &SystemArchitecturePopularity{
		Name:       name,
		Samples:    samples,
		Count:      count,
		Popularity: calculatePopularity(count, samples),
		StartMonth: startMonth,
		EndMonth:   endMonth,
	}, nil
}

func (r *SQLiteRepository) FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*SystemArchitecturePopularityList, error) {
	samples, err := r.getSamples(ctx, startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get samples: %w", err)
	}

	var sqlQuery string
	var countQuery string
	var args []any
	var countArgs []any

	sqlQuery = `
		SELECT name, SUM(count) as total_count
		FROM system_architecture
		WHERE month >= ? AND month <= ?`
	args = []any{startMonth, endMonth}
	countArgs = []any{startMonth, endMonth}

	if query != "" {
		sqlQuery += nameLikeCondition
		args = append(args, query+"%")
		countArgs = append(countArgs, query+"%")
	}

	sqlQuery += ` GROUP BY name ORDER BY total_count DESC, name ASC LIMIT ? OFFSET ?`
	args = append(args, limit, offset)

	countQuery = `SELECT COUNT(DISTINCT name) FROM system_architecture WHERE month >= ? AND month <= ?`
	if query != "" {
		countQuery += nameLikeCondition
	}

	var total int
	if err := r.db.QueryRowContext(ctx, countQuery, countArgs...).Scan(&total); err != nil {
		return nil, fmt.Errorf("count architectures: %w", err)
	}

	rows, err := r.db.QueryContext(ctx, sqlQuery, args...)
	if err != nil {
		return nil, fmt.Errorf("query architectures: %w", err)
	}
	defer func() { _ = rows.Close() }()

	var archs []SystemArchitecturePopularity
	for rows.Next() {
		var name string
		var count int
		if err := rows.Scan(&name, &count); err != nil {
			return nil, fmt.Errorf("scan architecture: %w", err)
		}

		archs = append(archs, SystemArchitecturePopularity{
			Name:       name,
			Samples:    samples,
			Count:      count,
			Popularity: calculatePopularity(count, samples),
			StartMonth: startMonth,
			EndMonth:   endMonth,
		})
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("iterate architectures: %w", err)
	}

	if archs == nil {
		archs = []SystemArchitecturePopularity{}
	}

	return &SystemArchitecturePopularityList{
		Total:                          total,
		Count:                          len(archs),
		SystemArchitecturePopularities: archs,
		Limit:                          limit,
		Offset:                         offset,
		Query:                          &query,
	}, nil
}

func (r *SQLiteRepository) FindSeriesByName(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*SystemArchitecturePopularityList, error) {
	countQuery := `SELECT COUNT(*) FROM system_architecture WHERE name = ? AND month >= ? AND month <= ?`
	var total int
	if err := r.db.QueryRowContext(ctx, countQuery, name, startMonth, endMonth).Scan(&total); err != nil {
		return nil, fmt.Errorf("count series: %w", err)
	}

	samplesMap, err := r.getMonthlySamples(ctx, startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get monthly samples: %w", err)
	}

	sqlQuery := `
		SELECT month, count
		FROM system_architecture
		WHERE name = ? AND month >= ? AND month <= ?
		ORDER BY month ASC
		LIMIT ? OFFSET ?`

	rows, err := r.db.QueryContext(ctx, sqlQuery, name, startMonth, endMonth, limit, offset)
	if err != nil {
		return nil, fmt.Errorf("query series: %w", err)
	}
	defer func() { _ = rows.Close() }()

	var archs []SystemArchitecturePopularity
	for rows.Next() {
		var month, count int
		if err := rows.Scan(&month, &count); err != nil {
			return nil, fmt.Errorf("scan series: %w", err)
		}

		samples := samplesMap[month]
		archs = append(archs, SystemArchitecturePopularity{
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

	if archs == nil {
		archs = []SystemArchitecturePopularity{}
	}

	return &SystemArchitecturePopularityList{
		Total:                          total,
		Count:                          len(archs),
		SystemArchitecturePopularities: archs,
		Limit:                          limit,
		Offset:                         offset,
		Query:                          nil,
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
	query := `
		SELECT month, SUM(count) as total
		FROM system_architecture
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
