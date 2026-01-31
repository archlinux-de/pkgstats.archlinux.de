#!/bin/bash
# API Compatibility Test
# Compares PHP and Go API responses to ensure they match
#
# Usage: ./test-api-compat.sh [php_port] [go_port]
#
# Prerequisites:
# - PHP backend running (just start)
# - Go backend running with same data (DATABASE=./pkgstats-test.db PORT=8081 just go-run)

set -euo pipefail

PHP_PORT="${1:-8180}"
GO_PORT="${2:-8081}"
PHP_BASE="http://localhost:$PHP_PORT"
GO_BASE="http://localhost:$GO_PORT"

PASSED=0
FAILED=0
FAILURES=""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m' # No Color

compare() {
    local endpoint=$1
    local php_response go_response

    php_response=$(curl -sf "$PHP_BASE$endpoint" 2>/dev/null) || {
        echo -e "${RED}✗${NC} $endpoint (PHP request failed)"
        FAILED=$((FAILED + 1))
        FAILURES="$FAILURES\n  $endpoint: PHP request failed"
        return
    }

    go_response=$(curl -sf "$GO_BASE$endpoint" 2>/dev/null) || {
        echo -e "${RED}✗${NC} $endpoint (Go request failed)"
        FAILED=$((FAILED + 1))
        FAILURES="$FAILURES\n  $endpoint: Go request failed"
        return
    }

    # Normalize JSON (sort keys, consistent formatting)
    php_normalized=$(echo "$php_response" | jq -S . 2>/dev/null) || php_normalized="$php_response"
    go_normalized=$(echo "$go_response" | jq -S . 2>/dev/null) || go_normalized="$go_response"

    # For XML responses (sitemap), normalize whitespace and quotes
    if [[ "$endpoint" == *.xml ]]; then
        # Normalize ports, whitespace, and quote styles for XML comparison
        php_normalized=$(echo "$php_response" | sed "s/$PHP_PORT/PORT/g" | tr -d '\n' | sed 's/> *</></g' | sed "s/'/\"/g")
        go_normalized=$(echo "$go_response" | sed "s/$GO_PORT/PORT/g" | tr -d '\n' | sed 's/> *</></g' | sed "s/'/\"/g")
    fi

    if [ "$php_normalized" = "$go_normalized" ]; then
        echo -e "${GREEN}✓${NC} $endpoint"
        PASSED=$((PASSED + 1))
    else
        echo -e "${RED}✗${NC} $endpoint"
        FAILED=$((FAILED + 1))

        # Show diff
        diff_output=$(diff <(echo "$php_normalized") <(echo "$go_normalized") | head -20)
        FAILURES="$FAILURES\n  $endpoint:\n$diff_output"
    fi
}

echo "API Compatibility Test"
echo "======================"
echo "PHP: $PHP_BASE"
echo "Go:  $GO_BASE"
echo ""

# Check servers are running
if ! curl -sf "$PHP_BASE/" > /dev/null 2>&1; then
    echo "Error: PHP server not running at $PHP_BASE"
    exit 1
fi

if ! curl -sf "$GO_BASE/" > /dev/null 2>&1; then
    echo "Error: Go server not running at $GO_BASE"
    exit 1
fi

# Use a fixed month range for deterministic comparison
START_MONTH=202112
END_MONTH=202201

echo "Testing with months: $START_MONTH - $END_MONTH"
echo ""

echo "--- Packages ---"
compare "/api/packages?startMonth=$START_MONTH&endMonth=$START_MONTH&limit=10"
compare "/api/packages?startMonth=$START_MONTH&endMonth=$START_MONTH&limit=5&offset=5"
compare "/api/packages?startMonth=$START_MONTH&endMonth=$START_MONTH&query=linux"
compare "/api/packages?startMonth=$START_MONTH&endMonth=$START_MONTH&query=pac"
compare "/api/packages?startMonth=$START_MONTH&endMonth=$END_MONTH&limit=10"
compare "/api/packages/pacman?startMonth=$START_MONTH&endMonth=$START_MONTH"
compare "/api/packages/pacman?startMonth=$START_MONTH&endMonth=$END_MONTH"
compare "/api/packages/linux?startMonth=$START_MONTH&endMonth=$START_MONTH"
compare "/api/packages/pacman/series?startMonth=$START_MONTH&endMonth=$END_MONTH"
compare "/api/packages/pacman/series?startMonth=$START_MONTH&endMonth=$END_MONTH&limit=1"

echo ""
echo "--- Countries ---"
compare "/api/countries?startMonth=$START_MONTH&endMonth=$START_MONTH&limit=10"
compare "/api/countries?startMonth=$START_MONTH&endMonth=$START_MONTH&query=DE"
compare "/api/countries?startMonth=$START_MONTH&endMonth=$START_MONTH&limit=5&offset=5"
compare "/api/countries/DE?startMonth=$START_MONTH&endMonth=$START_MONTH"
compare "/api/countries/DE?startMonth=$START_MONTH&endMonth=$END_MONTH"
compare "/api/countries/US?startMonth=$START_MONTH&endMonth=$START_MONTH"
compare "/api/countries/DE/series?startMonth=$START_MONTH&endMonth=$END_MONTH"
compare "/api/countries/DE/series?startMonth=$START_MONTH&endMonth=$END_MONTH&limit=1"

echo ""
echo "--- Mirrors ---"
compare "/api/mirrors?startMonth=$START_MONTH&endMonth=$START_MONTH&limit=10"
compare "/api/mirrors?startMonth=$START_MONTH&endMonth=$START_MONTH&query=localhost"
compare "/api/mirrors?startMonth=$START_MONTH&endMonth=$START_MONTH&limit=5&offset=5"

echo ""
echo "--- System Architectures ---"
compare "/api/system-architectures?startMonth=$START_MONTH&endMonth=$START_MONTH&limit=10"
compare "/api/system-architectures?startMonth=$START_MONTH&endMonth=$START_MONTH&query=x86"
compare "/api/system-architectures/x86_64?startMonth=$START_MONTH&endMonth=$START_MONTH"
compare "/api/system-architectures/x86_64?startMonth=$START_MONTH&endMonth=$END_MONTH"
compare "/api/system-architectures/x86_64/series?startMonth=$START_MONTH&endMonth=$END_MONTH"

echo ""
echo "--- Sitemap ---"
compare "/sitemap.xml"

echo ""
echo "======================"
echo -e "Results: ${GREEN}$PASSED passed${NC}, ${RED}$FAILED failed${NC}"

if [ $FAILED -gt 0 ]; then
    echo -e "\nFailures:$FAILURES"
    exit 1
fi

echo ""
echo "All tests passed!"
