package submit

import (
	"context"
	"database/sql"
	"fmt"
	"net/netip"
	"sync"
	"time"
)

const (
	defaultRateLimit    = 50
	defaultRateInterval = 7 * 24 * time.Hour // 7 days
)

// RateLimiter defines the interface for rate limiting.
type RateLimiter interface {
	Allow(ctx context.Context, key string) (bool, time.Time, error)
}

// SQLiteRateLimiter implements RateLimiter using SQLite storage.
type SQLiteRateLimiter struct {
	db       *sql.DB
	limit    int
	interval time.Duration
	now      func() time.Time
}

// NewSQLiteRateLimiter creates a new SQLite-backed rate limiter.
func NewSQLiteRateLimiter(db *sql.DB) *SQLiteRateLimiter {
	return &SQLiteRateLimiter{
		db:       db,
		limit:    defaultRateLimit,
		interval: defaultRateInterval,
		now:      time.Now,
	}
}

// Allow checks if a request from the given key should be allowed.
// Returns (allowed, retryAfter, error).
func (r *SQLiteRateLimiter) Allow(ctx context.Context, key string) (bool, time.Time, error) {
	now := r.now()
	windowStart := now.Add(-r.interval)

	// Count requests in the sliding window
	var count int
	err := r.db.QueryRowContext(ctx,
		`SELECT COUNT(*) FROM rate_limit WHERE key = ? AND timestamp > ?`,
		key, windowStart.Unix(),
	).Scan(&count)
	if err != nil {
		return false, time.Time{}, fmt.Errorf("query rate limit: %w", err)
	}

	if count >= r.limit {
		// Find oldest entry to calculate retry-after
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

	// Record this request
	_, err = r.db.ExecContext(ctx,
		`INSERT INTO rate_limit (key, timestamp) VALUES (?, ?)`,
		key, now.Unix(),
	)
	if err != nil {
		return false, time.Time{}, fmt.Errorf("insert rate limit: %w", err)
	}

	// Cleanup old entries (best effort)
	go func() {
		_, _ = r.db.Exec(`DELETE FROM rate_limit WHERE timestamp < ?`, windowStart.Unix())
	}()

	return true, time.Time{}, nil
}

// InMemoryRateLimiter implements RateLimiter using in-memory storage.
// Used for testing and development.
type InMemoryRateLimiter struct {
	mu       sync.Mutex
	requests map[string][]time.Time
	limit    int
	interval time.Duration
	now      func() time.Time
}

// NewInMemoryRateLimiter creates a new in-memory rate limiter.
func NewInMemoryRateLimiter() *InMemoryRateLimiter {
	return &InMemoryRateLimiter{
		requests: make(map[string][]time.Time),
		limit:    defaultRateLimit,
		interval: defaultRateInterval,
		now:      time.Now,
	}
}

// Allow checks if a request from the given key should be allowed.
func (r *InMemoryRateLimiter) Allow(_ context.Context, key string) (bool, time.Time, error) {
	r.mu.Lock()
	defer r.mu.Unlock()

	now := r.now()
	windowStart := now.Add(-r.interval)

	// Filter to only requests within the window
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

	// Record this request
	r.requests[key] = append(r.requests[key], now)
	return true, time.Time{}, nil
}

// NoopRateLimiter always allows requests. Used for testing.
type NoopRateLimiter struct{}

// Allow always returns true.
func (NoopRateLimiter) Allow(_ context.Context, _ string) (bool, time.Time, error) {
	return true, time.Time{}, nil
}

// AnonymizeIP anonymizes an IP address for rate limiting.
// IPv4: Zero last octet (192.168.1.100 → 192.168.1.0)
// IPv6: Zero last 80 bits (2a02:fb00::1 → 2a02::)
// Matches Symfony's IpUtils::anonymize() behavior.
func AnonymizeIP(ip netip.Addr) string {
	if !ip.IsValid() {
		return ""
	}

	if ip.Is4() {
		// IPv4: zero last octet
		addr := ip.As4()
		addr[3] = 0
		return netip.AddrFrom4(addr).String()
	}

	// IPv6: zero last 80 bits (keep first 48 bits = 6 bytes)
	addr := ip.As16()
	for i := 6; i < 16; i++ {
		addr[i] = 0
	}
	return netip.AddrFrom16(addr).String()
}
