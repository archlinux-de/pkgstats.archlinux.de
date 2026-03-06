package submit

import (
	"context"
	"database/sql"
	"fmt"
	"log/slog"
	"net/netip"
	"sync"
	"time"
)

const (
	defaultRateLimit    = 50
	defaultRateInterval = 7 * 24 * time.Hour // 7 days
)

type RateLimiter interface {
	Allow(ctx context.Context, key string) (bool, time.Time, error)
}

type SQLiteRateLimiter struct {
	db       *sql.DB
	limit    int
	interval time.Duration
	now      func() time.Time
}

func NewSQLiteRateLimiter(db *sql.DB) *SQLiteRateLimiter {
	return &SQLiteRateLimiter{
		db:       db,
		limit:    defaultRateLimit,
		interval: defaultRateInterval,
		now:      time.Now,
	}
}

func (r *SQLiteRateLimiter) Allow(ctx context.Context, key string) (bool, time.Time, error) {
	now := r.now()
	windowStart := now.Add(-r.interval)

	// Atomically insert only if under the limit
	result, err := r.db.ExecContext(ctx,
		`INSERT INTO rate_limit (key, timestamp)
		SELECT ?, ?
		WHERE (SELECT COUNT(*) FROM rate_limit WHERE key = ? AND timestamp > ?) < ?`,
		key, now.Unix(), key, windowStart.Unix(), r.limit,
	)
	if err != nil {
		return false, time.Time{}, fmt.Errorf("rate limit check: %w", err)
	}

	inserted, err := result.RowsAffected()
	if err != nil {
		return false, time.Time{}, fmt.Errorf("rate limit rows affected: %w", err)
	}

	// Cleanup old entries (best effort)
	if _, err := r.db.ExecContext(ctx, `DELETE FROM rate_limit WHERE timestamp < ?`, windowStart.Unix()); err != nil {
		slog.Warn("failed to cleanup old rate limit entries", "error", err)
	}

	if inserted == 0 {
		// Over the limit — find oldest entry to calculate retry-after
		var oldestTimestamp int64
		err := r.db.QueryRowContext(ctx,
			`SELECT MIN(timestamp) FROM rate_limit WHERE key = ? AND timestamp > ?`,
			key, windowStart.Unix(),
		).Scan(&oldestTimestamp)
		if err != nil {
			return false, time.Time{}, fmt.Errorf("query oldest timestamp: %w", err)
		}

		retryAfter := time.Unix(oldestTimestamp, 0).Add(r.interval)
		return false, retryAfter, nil
	}

	return true, time.Time{}, nil
}

type InMemoryRateLimiter struct {
	mu       sync.Mutex
	requests map[string][]time.Time
	limit    int
	interval time.Duration
	now      func() time.Time
}

func NewInMemoryRateLimiter() *InMemoryRateLimiter {
	return &InMemoryRateLimiter{
		requests: make(map[string][]time.Time),
		limit:    defaultRateLimit,
		interval: defaultRateInterval,
		now:      time.Now,
	}
}

func (r *InMemoryRateLimiter) Allow(_ context.Context, key string) (bool, time.Time, error) {
	r.mu.Lock()
	defer r.mu.Unlock()

	now := r.now()
	windowStart := now.Add(-r.interval)

	var validRequests []time.Time
	for _, t := range r.requests[key] {
		if t.After(windowStart) {
			validRequests = append(validRequests, t)
		}
	}
	r.requests[key] = validRequests

	if len(validRequests) >= r.limit {
		// Find oldest to calculate retry-after
		oldest := validRequests[0]
		for _, t := range validRequests[1:] {
			if t.Before(oldest) {
				oldest = t
			}
		}
		return false, oldest.Add(r.interval), nil
	}

	r.requests[key] = append(r.requests[key], now)
	return true, time.Time{}, nil
}

type NoopRateLimiter struct{}

func (NoopRateLimiter) Allow(_ context.Context, _ string) (bool, time.Time, error) {
	return true, time.Time{}, nil
}

// AnonymizeIP masks IPs for rate limiting to match Symfony's IpUtils::anonymize().
// IPv4: zeros last octet. IPv6: zeros last 80 bits.
func AnonymizeIP(ip netip.Addr) string {
	if !ip.IsValid() {
		return ""
	}

	if ip.Is4() {
		addr := ip.As4()
		addr[3] = 0
		return netip.AddrFrom4(addr).String()
	}

	addr := ip.As16()
	for i := 6; i < 16; i++ {
		addr[i] = 0
	}
	return netip.AddrFrom16(addr).String()
}
