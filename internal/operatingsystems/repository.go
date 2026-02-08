package operatingsystems

import (
	"context"
	"database/sql"
	"fmt"
	"math"
)

const (
	idLikeCondition     = ` AND id LIKE ?`
	popularityScale     = 10000
	popularityPrecision = 100
)

// Repository defines the interface for operating system ID data access.
type Repository interface {
	FindByID(ctx context.Context, id string, startMonth, endMonth int) (*OperatingSystemIdPopularity, error)
	FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*OperatingSystemIdPopularityList, error)
	FindSeriesByID(ctx context.Context, id string, startMonth, endMonth, limit, offset int) (*OperatingSystemIdPopularityList, error)
}

// SQLiteRepository implements Repository using SQLite.
type SQLiteRepository struct {
	db *sql.DB
}

// NewSQLiteRepository creates a new SQLiteRepository.
func NewSQLiteRepository(db *sql.DB) *SQLiteRepository {
	return &SQLiteRepository{db: db}
}

func (r *SQLiteRepository) FindByID(ctx context.Context, id string, startMonth, endMonth int) (*OperatingSystemIdPopularity, error) {
	var count int
	query := `SELECT COALESCE(SUM(count), 0) FROM operating_system_id WHERE id = ? AND month >= ? AND month <= ?`

	if err := r.db.QueryRowContext(ctx, query, id, startMonth, endMonth).Scan(&count); err != nil {
		return nil, fmt.Errorf("query operating system id count: %w", err)
	}

	samples, err := r.getSamples(ctx, startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get samples: %w", err)
	}

	return &OperatingSystemIdPopularity{
		ID:         id,
		Samples:    samples,
		Count:      count,
		Popularity: calculatePopularity(count, samples),
		StartMonth: startMonth,
		EndMonth:   endMonth,
	}, nil
}

func (r *SQLiteRepository) FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*OperatingSystemIdPopularityList, error) {
	samples, err := r.getSamples(ctx, startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get samples: %w", err)
	}

	var sqlQuery string
	var countQuery string
	var args []any
	var countArgs []any

	sqlQuery = `
		SELECT id, SUM(count) as total_count
		FROM operating_system_id
		WHERE month >= ? AND month <= ?`
	args = []any{startMonth, endMonth}
	countArgs = []any{startMonth, endMonth}

	if query != "" {
		sqlQuery += idLikeCondition
		args = append(args, query+"%")
		countArgs = append(countArgs, query+"%")
	}

	sqlQuery += ` GROUP BY id ORDER BY total_count DESC, id ASC LIMIT ? OFFSET ?`
	args = append(args, limit, offset)

	countQuery = `SELECT COUNT(DISTINCT id) FROM operating_system_id WHERE month >= ? AND month <= ?`
	if query != "" {
		countQuery += idLikeCondition
	}

	var total int
	if err := r.db.QueryRowContext(ctx, countQuery, countArgs...).Scan(&total); err != nil {
		return nil, fmt.Errorf("count operating system ids: %w", err)
	}

	rows, err := r.db.QueryContext(ctx, sqlQuery, args...)
	if err != nil {
		return nil, fmt.Errorf("query operating system ids: %w", err)
	}
	defer func() { _ = rows.Close() }()

	var osIDs []OperatingSystemIdPopularity
	for rows.Next() {
		var id string
		var count int
		if err := rows.Scan(&id, &count); err != nil {
			return nil, fmt.Errorf("scan operating system id: %w", err)
		}

		osIDs = append(osIDs, OperatingSystemIdPopularity{
			ID:         id,
			Samples:    samples,
			Count:      count,
			Popularity: calculatePopularity(count, samples),
			StartMonth: startMonth,
			EndMonth:   endMonth,
		})
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("iterate operating system ids: %w", err)
	}

	if osIDs == nil {
		osIDs = []OperatingSystemIdPopularity{}
	}

	return &OperatingSystemIdPopularityList{
		Total:                         total,
		Count:                         len(osIDs),
		OperatingSystemIdPopularities: osIDs,
		Limit:                         limit,
		Offset:                        offset,
		Query:                         &query,
	}, nil
}

func (r *SQLiteRepository) FindSeriesByID(ctx context.Context, id string, startMonth, endMonth, limit, offset int) (*OperatingSystemIdPopularityList, error) {
	countQuery := `SELECT COUNT(*) FROM operating_system_id WHERE id = ? AND month >= ? AND month <= ?`
	var total int
	if err := r.db.QueryRowContext(ctx, countQuery, id, startMonth, endMonth).Scan(&total); err != nil {
		return nil, fmt.Errorf("count series: %w", err)
	}

	samplesMap, err := r.getMonthlySamples(ctx, startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get monthly samples: %w", err)
	}

	sqlQuery := `
		SELECT month, count
		FROM operating_system_id
		WHERE id = ? AND month >= ? AND month <= ?
		ORDER BY month ASC
		LIMIT ? OFFSET ?`

	rows, err := r.db.QueryContext(ctx, sqlQuery, id, startMonth, endMonth, limit, offset)
	if err != nil {
		return nil, fmt.Errorf("query series: %w", err)
	}
	defer func() { _ = rows.Close() }()

	var osIDs []OperatingSystemIdPopularity
	for rows.Next() {
		var month, count int
		if err := rows.Scan(&month, &count); err != nil {
			return nil, fmt.Errorf("scan series: %w", err)
		}

		samples := samplesMap[month]
		osIDs = append(osIDs, OperatingSystemIdPopularity{
			ID:         id,
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

	if osIDs == nil {
		osIDs = []OperatingSystemIdPopularity{}
	}

	return &OperatingSystemIdPopularityList{
		Total:                         total,
		Count:                         len(osIDs),
		OperatingSystemIdPopularities: osIDs,
		Limit:                         limit,
		Offset:                        offset,
		Query:                         nil,
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
		FROM operating_system_id
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
