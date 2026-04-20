set quiet

export CGO_ENABLED := '0'
export PORT := '8282'
export DATABASE := 'tmp/pkgstats.db'
export GEOIP_DATABASE := 'tmp/GeoIP2-Country.mmdb'

[private]
default:
    just --list

# first-time setup: install dependencies, build and generate fixtures
init: install build-assets build-templates fixtures

# install dependencies and test data
install:
    curl -sf 'https://raw.githubusercontent.com/maxmind/MaxMind-DB/main/test-data/GeoIP2-Country-Test.mmdb' -o '{{ GEOIP_DATABASE }}'
    go mod download
    pnpm install

# compile frontend assets
build-assets:
    pnpm run build

# generate Go code and templ templates
build-templates:
    go generate ./...
    go tool templ generate

# build the production binary
build: build-assets build-templates
    go build -tags production -o pkgstatsd -ldflags="-s -w" -trimpath

# run the application locally
run:
    go run -tags development .

# open the local dev server in the default browser
open:
    xdg-open 'http://localhost:{{ PORT }}'

# watch for template and Go changes and rebuild automatically
[parallel]
dev: dev-assets dev-server

[private]
dev-assets:
    pnpm exec vite build --watch

[private]
dev-server:
    air

# run all tests
test:
    go test ./...

# run all linters
lint:
    pnpm run lint
    golangci-lint run
    just --fmt --unstable --check

# validate the OpenAPI spec
check-spec:
    #!/usr/bin/env bash
    set -euo pipefail
    spec=$(mktemp --suffix=.json)
    trap 'rm -f "$spec"' EXIT
    go run ./cmd/spec > "$spec"
    vacuum lint -bx -r vacuum.conf.yaml "$spec"

# auto-format all code
fmt:
    pnpm run format
    go tool templ fmt .
    golangci-lint fmt
    just --fmt --unstable

# remove all untracked and ignored files
clean:
    git clean -fdqx -e .idea

# remove untracked files, reinstall dependencies, rebuild and load fixtures
rebuild: clean install build-assets build-templates fixtures

# list outdated direct dependencies
outdated:
    pnpm outdated
    go list -u -m -json all | jq -r 'select(.Update and (.Indirect | not)) | "\(.Path): \(.Version) -> \(.Update.Version)"'

# audit dependencies for known vulnerabilities
audit:
    pnpm audit --prod

# update Go toolchain and module dependencies
update-go:
    go mod edit -go=$(go env GOVERSION | sed 's/go//; s/-.*//')
    go get -u ./...
    go mod tidy
    go get -u -t all
    go mod tidy

# update pnpm dependencies
update-pnpm:
    pnpm update --latest

# update all dependencies to latest versions
update: update-go update-pnpm

# generate test coverage report
coverage:
    #!/usr/bin/env bash
    set -euo pipefail
    go test -coverpkg=./... -coverprofile coverage.out ./...
    go tool cover -func=coverage.out
    # go tool cover -html=coverage.out

# generate Go fixtures for local development
fixtures:
    go run ./cmd/fixtures

# detect anomalies in submission data
detect-anomalies:
    go run . detect-anomalies
