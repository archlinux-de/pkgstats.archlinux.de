package web

import (
	"encoding/json"
	"net/http"
	"strconv"
	"time"
)

const (
	DefaultLimit    = 100
	MaxLimit        = 10000
	monthMultiplier = 100
)

// ParseMonthRange extracts startMonth and endMonth from query parameters.
func ParseMonthRange(r *http.Request) (startMonth, endMonth int) {
	now := time.Now()
	currentMonth := now.Year()*monthMultiplier + int(now.Month())

	startMonth = ParseIntParam(r, "startMonth", currentMonth)
	endMonth = ParseIntParam(r, "endMonth", currentMonth)

	// Treat 0 as "no constraint" to match PHP behavior where
	// startMonth=0/endMonth=0 means "no date filter"
	if endMonth <= 0 {
		endMonth = 999912
	}

	if startMonth > endMonth {
		startMonth, endMonth = endMonth, startMonth
	}

	return startMonth, endMonth
}

// ParseIntParam parses an integer query parameter with a default value.
func ParseIntParam(r *http.Request, key string, defaultValue int) int {
	s := r.URL.Query().Get(key)
	if s == "" {
		return defaultValue
	}

	v, err := strconv.Atoi(s)
	if err != nil {
		return defaultValue
	}

	return v
}

// NormalizePagination clamps limit and offset to valid ranges.
func NormalizePagination(limit, offset int) (int, int) {
	if limit > MaxLimit {
		limit = MaxLimit
	}

	if limit == 0 {
		limit = MaxLimit
	} else if limit < 1 {
		limit = 1
	}

	if offset < 0 {
		offset = 0
	}

	return limit, offset
}

// WriteEntityJSON writes a JSON response with CORS header for API entities.
func WriteEntityJSON(w http.ResponseWriter, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.Header().Set("Access-Control-Allow-Origin", "*")
	if err := json.NewEncoder(w).Encode(v); err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
	}
}
