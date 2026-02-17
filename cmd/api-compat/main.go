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

type testCase struct {
	endpoint   string
	wantStatus int // 0 means 200 + compare JSON bodies
}

func jsonTest(endpoint string) testCase {
	return testCase{endpoint: endpoint}
}

func statusTest(endpoint string, status int) testCase { //nolint:unparam
	return testCase{endpoint: endpoint, wantStatus: status}
}

// tests covers all 6 entities (list, get, series), parameter edge cases, and error cases.
var tests = []testCase{
	// --- packages: list, get, series ---
	jsonTest("/api/packages?limit=2&" + monthParams),
	jsonTest("/api/packages/pacman?" + monthParams),
	jsonTest("/api/packages/pacman/series?limit=2&" + monthParams),

	// --- countries ---
	jsonTest("/api/countries?limit=2&" + monthParams),
	jsonTest("/api/countries/DE?" + monthParams),
	jsonTest("/api/countries/DE/series?limit=2&" + monthParams),

	// --- mirrors (URL-encoded path segment) ---
	jsonTest("/api/mirrors?limit=2&" + monthParams),
	jsonTest(mirrorPath + "?" + monthParams),
	jsonTest(mirrorPath + "/series?limit=2&" + monthParams),

	// --- system-architectures ---
	jsonTest("/api/system-architectures?limit=2&" + monthParams),
	jsonTest("/api/system-architectures/x86_64?" + monthParams),
	jsonTest("/api/system-architectures/x86_64/series?limit=2&" + monthParams),

	// --- operating-system-architectures ---
	jsonTest("/api/operating-system-architectures?limit=2&" + monthParams),
	jsonTest("/api/operating-system-architectures/x86_64?" + monthParams),
	jsonTest("/api/operating-system-architectures/x86_64/series?limit=2&" + monthParams),

	// --- operating-systems ---
	jsonTest("/api/operating-systems?limit=2&" + monthParams),
	jsonTest("/api/operating-systems/arch?" + monthParams),
	jsonTest("/api/operating-systems/arch/series?limit=2&" + monthParams),

	// --- parameter edge cases ---

	// offset pagination
	jsonTest("/api/packages?limit=2&offset=1&" + monthParams),

	// query filter
	jsonTest("/api/packages?limit=2&query=pac&" + monthParams),

	// multi-month range
	jsonTest("/api/packages?limit=2&startMonth=202301&endMonth=202312"),
	jsonTest("/api/packages/pacman/series?limit=5&startMonth=202301&endMonth=202312"),

	// limit=0 (maps to MaxLimit)
	jsonTest("/api/packages?limit=0&" + monthParams),

	// query on mirrors (QueryContains=true uses LIKE %query%)
	jsonTest("/api/mirrors?limit=2&query=pkgbuild&" + monthParams),

	// offset on series
	jsonTest("/api/countries/DE/series?limit=2&offset=1&" + monthParams),

	// nonexistent identifier (valid request, returns zero count)
	jsonTest("/api/packages/nonexistent_pkg_xyz?" + monthParams),
	jsonTest("/api/countries/XX?" + monthParams),
	jsonTest("/api/packages/nonexistent_pkg_xyz/series?limit=2&" + monthParams),

	// swapped months (startMonth > endMonth, returns empty results)
	jsonTest("/api/packages?limit=2&startMonth=202501&endMonth=202401"),

	// empty query string (treated as no filter)
	jsonTest("/api/packages?limit=2&query=&" + monthParams),

	// startMonth=0 (no lower bound)
	jsonTest("/api/packages?limit=2&startMonth=0&endMonth=202501"),

	// endMonth=0 (no upper bound, returns all data up to now)
	jsonTest("/api/packages/pacman?startMonth=202501&endMonth=0"),

	// max valid offset (boundary)
	jsonTest("/api/packages?limit=2&offset=100000&" + monthParams),

	// --- error cases (expect matching HTTP status codes) ---

	// invalid limit
	statusTest("/api/packages?limit=-1&"+monthParams, http.StatusBadRequest),
	statusTest("/api/packages?limit=abc&"+monthParams, http.StatusBadRequest),

	// invalid startMonth / endMonth
	statusTest("/api/packages?startMonth=abc&endMonth=202501&limit=2", http.StatusBadRequest),
	statusTest("/api/packages?limit=2&startMonth=202513&endMonth=202501", http.StatusBadRequest),
	statusTest("/api/packages?limit=2&startMonth=202501&endMonth=209912", http.StatusBadRequest),

	// invalid offset
	statusTest("/api/packages?offset=-5&limit=2&"+monthParams, http.StatusBadRequest),
	statusTest("/api/packages?offset=100001&limit=2&"+monthParams, http.StatusBadRequest),

	// invalid query (SQL wildcards rejected by package name regex)
	statusTest("/api/packages?limit=2&query=%25&"+monthParams, http.StatusBadRequest),
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

	for _, tc := range tests {
		if tc.wantStatus != 0 {
			failures += runStatusTest(reference, target, tc)
		} else {
			failures += runJSONTest(reference, target, tc)
		}
	}

	if failures > 0 {
		return fmt.Errorf("%d endpoint(s) failed", failures)
	}

	fmt.Println("\nAll endpoints match.")
	return nil
}

func runJSONTest(reference, target string, tc testCase) int {
	refData, err := fetchJSON(reference + tc.endpoint)
	if err != nil {
		fmt.Printf("FAIL %s\n  error fetching reference: %v\n", tc.endpoint, err)
		return 1
	}

	targetData, err := fetchJSON(target + tc.endpoint)
	if err != nil {
		fmt.Printf("FAIL %s\n  error fetching target: %v\n", tc.endpoint, err)
		return 1
	}

	diffs := compareJSON("", refData, targetData)
	if len(diffs) == 0 {
		fmt.Printf("PASS %s\n", tc.endpoint)
		return 0
	}

	fmt.Printf("FAIL %s\n", tc.endpoint)
	for _, d := range diffs {
		fmt.Printf("  %s\n", d)
	}

	return 1
}

func runStatusTest(reference, target string, tc testCase) int {
	refStatus, err := fetchStatus(reference + tc.endpoint)
	if err != nil {
		fmt.Printf("FAIL %s\n  error fetching reference: %v\n", tc.endpoint, err)
		return 1
	}

	targetStatus, err := fetchStatus(target + tc.endpoint)
	if err != nil {
		fmt.Printf("FAIL %s\n  error fetching target: %v\n", tc.endpoint, err)
		return 1
	}

	if refStatus != tc.wantStatus {
		fmt.Printf("FAIL %s\n  reference returned %d, expected %d\n", tc.endpoint, refStatus, tc.wantStatus)
		return 1
	}

	if targetStatus != tc.wantStatus {
		fmt.Printf("FAIL %s\n  target returned %d, expected %d\n", tc.endpoint, targetStatus, tc.wantStatus)
		return 1
	}

	fmt.Printf("PASS %s [%d]\n", tc.endpoint, tc.wantStatus)
	return 0
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

func fetchStatus(rawURL string) (int, error) {
	resp, err := http.Get(rawURL) //nolint:gosec
	if err != nil {
		return 0, err
	}
	defer func() { _ = resp.Body.Close() }()

	return resp.StatusCode, nil
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
