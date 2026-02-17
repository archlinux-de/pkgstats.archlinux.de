package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"net/http"
	"net/url"
	"os"
	"strings"
)

const monthParams = "startMonth=202501&endMonth=202501"

var mirrorPath = "/api/mirrors/" + url.PathEscape("https://geo.mirror.pkgbuild.com/")

// endpoints covers all 6 entities (list, get, series) plus parameter edge cases.
var endpoints = []string{
	// --- packages: list, get, series ---
	"/api/packages?limit=2&" + monthParams,
	"/api/packages/pacman?" + monthParams,
	"/api/packages/pacman/series?limit=2&" + monthParams,

	// --- countries ---
	"/api/countries?limit=2&" + monthParams,
	"/api/countries/DE?" + monthParams,
	"/api/countries/DE/series?limit=2&" + monthParams,

	// --- mirrors (URL-encoded path segment) ---
	"/api/mirrors?limit=2&" + monthParams,
	mirrorPath + "?" + monthParams,
	mirrorPath + "/series?limit=2&" + monthParams,

	// --- system-architectures ---
	"/api/system-architectures?limit=2&" + monthParams,
	"/api/system-architectures/x86_64?" + monthParams,
	"/api/system-architectures/x86_64/series?limit=2&" + monthParams,

	// --- operating-system-architectures ---
	"/api/operating-system-architectures?limit=2&" + monthParams,
	"/api/operating-system-architectures/x86_64?" + monthParams,
	"/api/operating-system-architectures/x86_64/series?limit=2&" + monthParams,

	// --- operating-systems ---
	"/api/operating-systems?limit=2&" + monthParams,
	"/api/operating-systems/arch?" + monthParams,
	"/api/operating-systems/arch/series?limit=2&" + monthParams,

	// --- parameter edge cases (using packages as representative entity) ---

	// offset pagination
	"/api/packages?limit=2&offset=1&" + monthParams,

	// query filter
	"/api/packages?limit=2&query=pac&" + monthParams,

	// multi-month range
	"/api/packages?limit=2&startMonth=202301&endMonth=202312",
	"/api/packages/pacman/series?limit=5&startMonth=202301&endMonth=202312",

	// limit=0 (maps to MaxLimit)
	"/api/packages?limit=0&" + monthParams,

	// query on mirrors (QueryContains=true uses LIKE %query%)
	"/api/mirrors?limit=2&query=pkgbuild&" + monthParams,

	// offset on series
	"/api/countries/DE/series?limit=2&offset=1&" + monthParams,
}

func main() {
	reference := flag.String("reference", "https://pkgstats.archlinux.de", "reference server base URL")
	target := flag.String("target", "http://localhost:8182", "target server base URL")
	flag.Parse()

	if err := run(*reference, *target); err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(1)
	}
}

func run(reference, target string) error {
	reference = strings.TrimRight(reference, "/")
	target = strings.TrimRight(target, "/")

	var failures int

	for _, endpoint := range endpoints {
		refData, err := fetchJSON(reference + endpoint)
		if err != nil {
			fmt.Printf("FAIL %s\n  error fetching reference: %v\n", endpoint, err)
			failures++
			continue
		}

		targetData, err := fetchJSON(target + endpoint)
		if err != nil {
			fmt.Printf("FAIL %s\n  error fetching target: %v\n", endpoint, err)
			failures++
			continue
		}

		diffs := compareJSON("", refData, targetData)
		if len(diffs) == 0 {
			fmt.Printf("PASS %s\n", endpoint)
		} else {
			fmt.Printf("FAIL %s\n", endpoint)
			for _, d := range diffs {
				fmt.Printf("  %s\n", d)
			}
			failures++
		}
	}

	if failures > 0 {
		return fmt.Errorf("%d endpoint(s) failed", failures)
	}

	fmt.Println("\nAll endpoints match.")
	return nil
}

func fetchJSON(rawURL string) (any, error) {
	resp, err := http.Get(rawURL) //nolint:gosec
	if err != nil {
		return nil, err
	}
	defer func() { _ = resp.Body.Close() }()

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("HTTP %d", resp.StatusCode)
	}

	dec := json.NewDecoder(resp.Body)
	dec.UseNumber()

	var data any
	if err := dec.Decode(&data); err != nil {
		return nil, fmt.Errorf("decoding JSON: %w", err)
	}

	return data, nil
}

func compareJSON(path string, a, b any) []string {
	var diffs []string

	switch av := a.(type) {
	case map[string]any:
		bv, ok := b.(map[string]any)
		if !ok {
			return append(diffs, fmt.Sprintf("%s: type mismatch: object vs %T", path, b))
		}
		for key := range av {
			childPath := path + "." + key
			if _, exists := bv[key]; !exists {
				diffs = append(diffs, childPath+": missing in target")
				continue
			}
			diffs = append(diffs, compareJSON(childPath, av[key], bv[key])...)
		}
		for key := range bv {
			if _, exists := av[key]; !exists {
				childPath := path + "." + key
				diffs = append(diffs, childPath+": extra in target")
			}
		}

	case []any:
		bv, ok := b.([]any)
		if !ok {
			return append(diffs, fmt.Sprintf("%s: type mismatch: array vs %T", path, b))
		}
		if len(av) != len(bv) {
			diffs = append(diffs, fmt.Sprintf("%s: array length %d vs %d", path, len(av), len(bv)))
			return diffs
		}
		for i := range av {
			childPath := fmt.Sprintf("%s[%d]", path, i)
			diffs = append(diffs, compareJSON(childPath, av[i], bv[i])...)
		}

	default:
		if fmt.Sprintf("%v", a) != fmt.Sprintf("%v", b) {
			diffs = append(diffs, fmt.Sprintf("%s: %v != %v", path, a, b))
		}
	}

	return diffs
}
