package web

import (
	"net/http"
	"net/http/httptest"
	"testing"
	"time"
)

func currentMonth() int {
	now := time.Now()
	return now.Year()*monthMultiplier + int(now.Month())
}

func TestParseIntParam(t *testing.T) {
	tests := []struct {
		name      string
		url       string
		key       string
		def       int
		want      int
		wantError bool
	}{
		{"missing param uses default", "/test", "limit", 100, 100, false},
		{"empty param uses default", "/test?limit=", "limit", 100, 100, false},
		{"valid integer", "/test?limit=50", "limit", 100, 50, false},
		{"zero", "/test?limit=0", "limit", 100, 0, false},
		{"negative", "/test?limit=-5", "limit", 100, -5, false},
		{"non-numeric", "/test?limit=abc", "limit", 100, 0, true},
		{"float", "/test?limit=1.5", "limit", 100, 0, true},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			r := httptest.NewRequest(http.MethodGet, tt.url, nil)
			got, err := ParseIntParam(r, tt.key, tt.def)
			if tt.wantError {
				if err == nil {
					t.Error("expected error, got nil")
				}
			} else {
				if err != nil {
					t.Errorf("unexpected error: %v", err)
				}
				if got != tt.want {
					t.Errorf("got %d, want %d", got, tt.want)
				}
			}
		})
	}
}

func TestParsePagination(t *testing.T) {
	tests := []struct {
		name       string
		url        string
		wantLimit  int
		wantOffset int
		wantError  bool
	}{
		{"defaults", "/test", DefaultLimit, 0, false},
		{"explicit values", "/test?limit=50&offset=10", 50, 10, false},
		{"limit=0 maps to MaxLimit", "/test?limit=0", MaxLimit, 0, false},
		{"limit=1 minimum", "/test?limit=1", 1, 0, false},
		{"limit=MaxLimit", "/test?limit=10000", MaxLimit, 0, false},
		{"max offset", "/test?offset=100000", DefaultLimit, MaxOffset, false},

		// Errors
		{"limit=-1", "/test?limit=-1", 0, 0, true},
		{"limit exceeds max", "/test?limit=10001", 0, 0, true},
		{"limit=abc", "/test?limit=abc", 0, 0, true},
		{"offset=-1", "/test?offset=-1", 0, 0, true},
		{"offset exceeds max", "/test?offset=100001", 0, 0, true},
		{"offset=abc", "/test?offset=abc", 0, 0, true},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			r := httptest.NewRequest(http.MethodGet, tt.url, nil)
			limit, offset, err := ParsePagination(r)
			if tt.wantError {
				if err == nil {
					t.Error("expected error, got nil")
				}
			} else {
				if err != nil {
					t.Errorf("unexpected error: %v", err)
				}
				if limit != tt.wantLimit {
					t.Errorf("limit: got %d, want %d", limit, tt.wantLimit)
				}
				if offset != tt.wantOffset {
					t.Errorf("offset: got %d, want %d", offset, tt.wantOffset)
				}
			}
		})
	}
}

func TestParseQuery(t *testing.T) {
	tests := []struct {
		name      string
		url       string
		want      string
		wantError bool
	}{
		{"empty query", "/test", "", false},
		{"valid query", "/test?query=pacman", "pacman", false},
		{"alphanumeric", "/test?query=lib32", "lib32", false},
		{"with dots", "/test?query=xorg.fonts", "xorg.fonts", false},
		{"with plus", "/test?query=g%2B%2B", "g++", false},
		{"with at", "/test?query=python@3", "python@3", false},
		{"with colon", "/test?query=texlive:base", "texlive:base", false},
		{"with underscore", "/test?query=python_dateutil", "python_dateutil", false},
		{"with hyphen", "/test?query=xdg-utils", "xdg-utils", false},
		{"percent wildcard", "/test?query=%25", "", true},
		{"underscore start", "/test?query=_foo", "", true},
		{"space", "/test?query=foo+bar", "", true},
		{"asterisk", "/test?query=foo*", "", true},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			r := httptest.NewRequest(http.MethodGet, tt.url, nil)
			got, err := ParseQuery(r)
			if tt.wantError {
				if err == nil {
					t.Error("expected error, got nil")
				}
			} else {
				if err != nil {
					t.Errorf("unexpected error: %v", err)
				}
				if got != tt.want {
					t.Errorf("got %q, want %q", got, tt.want)
				}
			}
		})
	}
}

func TestParseMonthRange(t *testing.T) {
	cm := currentMonth()

	tests := []struct {
		name      string
		url       string
		wantStart int
		wantEnd   int
		wantError bool
	}{
		{"defaults to current month", "/test", cm, cm, false},
		{"explicit valid range", "/test?startMonth=202401&endMonth=202412", 202401, 202412, false},
		{"startMonth=0 no lower bound", "/test?startMonth=0&endMonth=202501", 0, 202501, false},
		{"endMonth=0 maps to current month", "/test?startMonth=202501&endMonth=0", 202501, cm, false},
		{"swapped months not auto-corrected", "/test?startMonth=202512&endMonth=202501", 202512, 202501, false},

		// Errors
		{"startMonth=abc", "/test?startMonth=abc&endMonth=202501", 0, 0, true},
		{"endMonth=abc", "/test?startMonth=202501&endMonth=abc", 0, 0, true},
		{"invalid month 13", "/test?startMonth=202513&endMonth=202501", 0, 0, true},
		{"invalid month 00", "/test?startMonth=202500&endMonth=202501", 0, 0, true},
		{"year too old", "/test?startMonth=200112&endMonth=202501", 0, 0, true},
		{"future endMonth", "/test?startMonth=202501&endMonth=209912", 0, 0, true},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			r := httptest.NewRequest(http.MethodGet, tt.url, nil)
			startMonth, endMonth, err := ParseMonthRange(r)
			if tt.wantError {
				if err == nil {
					t.Error("expected error, got nil")
				}
			} else {
				if err != nil {
					t.Errorf("unexpected error: %v", err)
				}
				if startMonth != tt.wantStart {
					t.Errorf("startMonth: got %d, want %d", startMonth, tt.wantStart)
				}
				if endMonth != tt.wantEnd {
					t.Errorf("endMonth: got %d, want %d", endMonth, tt.wantEnd)
				}
			}
		})
	}
}
