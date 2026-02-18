package popularity

import (
	"context"
	"database/sql"
	"fmt"
	"math"

	"pkgstats.archlinux.de/internal/database"
)

const (
	popularityScale     = 10000
	popularityPrecision = 100
)

// Config defines the table/column mapping for a popularity entity.
type Config struct {
	Table         string // e.g. "country"
	Column        string // e.g. "code"
	QueryContains bool   // true for mirrors (uses %query%), false for prefix (query%)
}

// ItemFunc creates an entity-specific item from generic popularity data.
type ItemFunc[T any] func(identifier string, samples, count int, popularity float64, startMonth, endMonth int) T

// ListFunc creates an entity-specific list from generic popularity data.
type ListFunc[L any, T any] func(total, count int, items []T, limit, offset int, query *string) L

// Repository is a generic popularity repository.
type Repository[T any, L any] struct {
	db           *sql.DB
	cfg          Config
	samplesCache *database.MonthlySamplesCache
	newItem      ItemFunc[T]
	newList      ListFunc[L, T]
}

// NewRepository creates a new generic popularity repository.
func NewRepository[T any, L any](db *sql.DB, cfg Config, newItem ItemFunc[T], newList ListFunc[L, T]) *Repository[T, L] {
	samplesQuery := fmt.Sprintf(`SELECT month, SUM(count) FROM %s GROUP BY month`, cfg.Table)

	return &Repository[T, L]{
		db:           db,
		cfg:          cfg,
		samplesCache: database.NewMonthlySamplesCache(db, samplesQuery),
		newItem:      newItem,
		newList:      newList,
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

// FindByIdentifier returns popularity data for a single identifier.
func (r *Repository[T, L]) FindByIdentifier(ctx context.Context, identifier string, startMonth, endMonth int) (*T, error) {
	var count int

	mClause, mArgs := monthRange(startMonth, endMonth)
	//nolint:gosec
	query := fmt.Sprintf(
		`SELECT COALESCE(SUM(count), 0) FROM %s WHERE %s = ? AND `+mClause,
		r.cfg.Table, r.cfg.Column,
	)

	if err := r.db.QueryRowContext(ctx, query, append([]any{identifier}, mArgs...)...).Scan(&count); err != nil {
		return nil, fmt.Errorf("query %s count: %w", r.cfg.Table, err)
	}

	samples, err := r.getSamples(startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get samples: %w", err)
	}

	item := r.newItem(identifier, samples, count, calculatePopularity(count, samples), startMonth, endMonth)

	return &item, nil
}

// FindAll returns a paginated list of items matching the query.
func (r *Repository[T, L]) FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*L, error) {
	samples, err := r.getSamples(startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get samples: %w", err)
	}

	mClause, mArgs := monthRange(startMonth, endMonth)
	likeCondition := fmt.Sprintf(` AND %s LIKE ?`, r.cfg.Column)

	//nolint:gosec
	sqlQuery := fmt.Sprintf(`
		SELECT %s, SUM(count) as total_count
		FROM %s
		WHERE `+mClause,
		r.cfg.Column, r.cfg.Table,
	)
	args := append([]any{}, mArgs...)
	countArgs := append([]any{}, mArgs...)

	if query != "" {
		sqlQuery += likeCondition
		pattern := r.queryPattern(query)
		args = append(args, pattern)
		countArgs = append(countArgs, pattern)
	}

	sqlQuery += fmt.Sprintf(` GROUP BY %s ORDER BY total_count DESC, %s ASC LIMIT ? OFFSET ?`,
		r.cfg.Column, r.cfg.Column,
	)
	args = append(args, limit, offset)

	//nolint:gosec
	countQuery := fmt.Sprintf(`SELECT COUNT(DISTINCT %s) FROM %s WHERE `+mClause,
		r.cfg.Column, r.cfg.Table,
	)
	if query != "" {
		countQuery += likeCondition
	}

	var total int
	if err := r.db.QueryRowContext(ctx, countQuery, countArgs...).Scan(&total); err != nil {
		return nil, fmt.Errorf("count %s: %w", r.cfg.Table, err)
	}

	rows, err := r.db.QueryContext(ctx, sqlQuery, args...)
	if err != nil {
		return nil, fmt.Errorf("query %s: %w", r.cfg.Table, err)
	}
	defer func() { _ = rows.Close() }()

	var items []T
	for rows.Next() {
		var identifier string
		var count int
		if err := rows.Scan(&identifier, &count); err != nil {
			return nil, fmt.Errorf("scan %s: %w", r.cfg.Table, err)
		}

		items = append(items, r.newItem(identifier, samples, count, calculatePopularity(count, samples), startMonth, endMonth))
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("iterate %s: %w", r.cfg.Table, err)
	}

	if items == nil {
		items = make([]T, 0)
	}

	list := r.newList(total, len(items), items, limit, offset, &query)

	return &list, nil
}

// FindSeries returns monthly popularity data for an identifier.
func (r *Repository[T, L]) FindSeries(ctx context.Context, identifier string, startMonth, endMonth, limit, offset int) (*L, error) {
	mClause, mArgs := monthRange(startMonth, endMonth)

	//nolint:gosec
	countQuery := fmt.Sprintf(`SELECT COUNT(*) FROM %s WHERE %s = ? AND `+mClause,
		r.cfg.Table, r.cfg.Column,
	)

	var total int
	if err := r.db.QueryRowContext(ctx, countQuery, append([]any{identifier}, mArgs...)...).Scan(&total); err != nil {
		return nil, fmt.Errorf("count series: %w", err)
	}

	samplesMap, err := r.getMonthlySamples(startMonth, endMonth)
	if err != nil {
		return nil, fmt.Errorf("get monthly samples: %w", err)
	}

	//nolint:gosec
	sqlQuery := fmt.Sprintf(`SELECT month, count FROM %s WHERE %s = ? AND `+mClause+` ORDER BY month ASC LIMIT ? OFFSET ?`,
		r.cfg.Table, r.cfg.Column,
	)

	rows, err := r.db.QueryContext(ctx, sqlQuery, append(append([]any{identifier}, mArgs...), limit, offset)...)
	if err != nil {
		return nil, fmt.Errorf("query series: %w", err)
	}
	defer func() { _ = rows.Close() }()

	var items []T
	for rows.Next() {
		var month, count int
		if err := rows.Scan(&month, &count); err != nil {
			return nil, fmt.Errorf("scan series: %w", err)
		}

		samples := samplesMap[month]
		items = append(items, r.newItem(identifier, samples, count, calculatePopularity(count, samples), month, month))
	}

	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("iterate series: %w", err)
	}

	if items == nil {
		items = make([]T, 0)
	}

	list := r.newList(total, len(items), items, limit, offset, nil)

	return &list, nil
}

func (r *Repository[T, L]) queryPattern(query string) string {
	if r.cfg.QueryContains {
		return "%" + query + "%"
	}

	return query + "%"
}

func (r *Repository[T, L]) getSamples(startMonth, endMonth int) (int, error) {
	monthlySamples, err := r.getMonthlySamples(startMonth, endMonth)
	if err != nil {
		return 0, err
	}

	var total int
	for _, count := range monthlySamples {
		total += count
	}

	return total, nil
}

func (r *Repository[T, L]) getMonthlySamples(startMonth, endMonth int) (map[int]int, error) {
	return r.samplesCache.Get(startMonth, endMonth)
}

func calculatePopularity(count, samples int) float64 {
	if samples == 0 {
		return 0
	}

	return math.Round(float64(count)/float64(samples)*popularityScale) / popularityPrecision
}
