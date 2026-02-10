package database

import (
	"database/sql"
	"fmt"
	"sync"
	"time"
)

// MonthlySamplesCache caches the result of a monthly aggregation query
// (e.g. MAX(count) or SUM(count) grouped by month). Matches PHP's Doctrine
// result cache behavior: loads all months without filtering, caches until
// the start of next month, filters in Go.
type MonthlySamplesCache struct {
	db    *sql.DB
	query string

	mu     sync.RWMutex
	cache  map[int]int
	expiry time.Time
}

// NewMonthlySamplesCache creates a cache for the given aggregation query.
// The query must return exactly two columns: month (int) and value (int).
func NewMonthlySamplesCache(db *sql.DB, query string) *MonthlySamplesCache {
	return &MonthlySamplesCache{db: db, query: query}
}

// Get returns cached monthly samples filtered to the given month range.
func (c *MonthlySamplesCache) Get(startMonth, endMonth int) (map[int]int, error) {
	all, err := c.load()
	if err != nil {
		return nil, err
	}

	result := make(map[int]int)
	for month, value := range all {
		if month >= startMonth && month <= endMonth {
			result[month] = value
		}
	}

	return result, nil
}

func (c *MonthlySamplesCache) load() (map[int]int, error) {
	c.mu.RLock()
	if c.cache != nil && time.Now().Before(c.expiry) {
		cache := c.cache
		c.mu.RUnlock()
		return cache, nil
	}
	c.mu.RUnlock()

	c.mu.Lock()
	defer c.mu.Unlock()

	// Double-check after acquiring write lock
	if c.cache != nil && time.Now().Before(c.expiry) {
		return c.cache, nil
	}

	rows, err := c.db.Query(c.query)
	if err != nil {
		return nil, fmt.Errorf("query monthly samples: %w", err)
	}
	defer func() { _ = rows.Close() }()

	cache := make(map[int]int)
	for rows.Next() {
		var month, value int
		if err := rows.Scan(&month, &value); err != nil {
			return nil, fmt.Errorf("scan monthly samples: %w", err)
		}
		cache[month] = value
	}

	if err := rows.Err(); err != nil {
		return nil, err
	}

	c.cache = cache
	c.expiry = startOfNextMonth()

	return cache, nil
}

func startOfNextMonth() time.Time {
	now := time.Now()
	return time.Date(now.Year(), now.Month()+1, 1, 0, 0, 0, 0, now.Location())
}
