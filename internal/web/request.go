package web

import (
	"encoding/json"
	"errors"
	"fmt"
	"net/http"
	"regexp"
	"strconv"
	"time"
)

const (
	DefaultLimit    = 100
	MaxLimit        = 10000
	MaxOffset       = 100000
	monthMultiplier = 100
	minYear         = 2002
)

// ParseIntParam parses an integer query parameter with a default value.
// Returns an error if the parameter is present but not a valid integer.
func ParseIntParam(r *http.Request, key string, defaultValue int) (int, error) {
	s := r.URL.Query().Get(key)
	if s == "" {
		return defaultValue, nil
	}

	v, err := strconv.Atoi(s)
	if err != nil {
		return 0, fmt.Errorf("invalid value for %s: %q", key, s)
	}

	return v, nil
}

// GetCurrentMonth returns the last complete month as an integer (YYYYMM).
func GetCurrentMonth() int {
	now := time.Now()
	lastMonth := now.AddDate(0, -1, 0)
	return lastMonth.Year()*monthMultiplier + int(lastMonth.Month())
}

// GetActualCurrentMonth returns the actual current month as an integer (YYYYMM).
func GetActualCurrentMonth() int {
	now := time.Now()
	return now.Year()*monthMultiplier + int(now.Month())
}

// ParseMonthRange extracts and validates startMonth and endMonth from query parameters.
func ParseMonthRange(r *http.Request) (startMonth, endMonth int, err error) {
	defaultMonth := GetCurrentMonth()
	actualMonth := GetActualCurrentMonth()

	startMonth, err = ParseIntParam(r, "startMonth", defaultMonth)
	if err != nil {
		return 0, 0, err
	}

	endMonth, err = ParseIntParam(r, "endMonth", defaultMonth)
	if err != nil {
		return 0, 0, err
	}

	// endMonth=0 maps to default month (matching PHP behavior)
	if endMonth == 0 {
		endMonth = defaultMonth
	}

	// Validate month format (startMonth=0 means "no lower bound")
	if startMonth != 0 {
		if err := validateMonth(startMonth, actualMonth); err != nil {
			return 0, 0, fmt.Errorf("invalid startMonth: %w", err)
		}
	}

	if err := validateMonth(endMonth, actualMonth); err != nil {
		return 0, 0, fmt.Errorf("invalid endMonth: %w", err)
	}

	return startMonth, endMonth, nil
}

// validateMonth checks that a yearMonth value is a valid YYYYMM date.
func validateMonth(yearMonth, currentMonth int) error {
	year := yearMonth / monthMultiplier
	month := yearMonth % monthMultiplier

	if year < minYear {
		return errors.New("year must be >= 2002")
	}

	if month < 1 || month > 12 {
		return errors.New("month must be 01-12")
	}

	if yearMonth > currentMonth {
		return errors.New("must not be in the future")
	}

	return nil
}

// ParsePagination parses and validates limit and offset query parameters.
func ParsePagination(r *http.Request) (limit, offset int, err error) {
	limit, err = ParseIntParam(r, "limit", DefaultLimit)
	if err != nil {
		return 0, 0, err
	}

	offset, err = ParseIntParam(r, "offset", 0)
	if err != nil {
		return 0, 0, err
	}

	// limit=0 maps to MaxLimit (matching PHP behavior)
	if limit == 0 {
		limit = MaxLimit
	}

	if limit < 1 || limit > MaxLimit {
		return 0, 0, fmt.Errorf("limit must be between 1 and %d", MaxLimit)
	}

	if offset < 0 || offset > MaxOffset {
		return 0, 0, fmt.Errorf("offset must be between 0 and %d", MaxOffset)
	}

	return limit, offset, nil
}

var queryRegexp = regexp.MustCompile(`^[a-zA-Z0-9][a-zA-Z0-9@:.+_-]*$`)

// ParseQuery extracts and validates the query parameter.
func ParseQuery(r *http.Request) (string, error) {
	query := r.URL.Query().Get("query")
	if query != "" && !queryRegexp.MatchString(query) {
		return "", errors.New("invalid query parameter")
	}

	return query, nil
}

// WriteEntityJSON writes a JSON response for API entities.
func WriteEntityJSON(w http.ResponseWriter, v any) {
	w.Header().Set("Content-Type", "application/json")
	if err := json.NewEncoder(w).Encode(v); err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
	}
}
