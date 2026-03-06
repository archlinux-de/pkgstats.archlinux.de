set quiet := true

export CGO_ENABLED := '0'
export PORT := '8282'
export DATABASE := 'tmp/pkgstats.db'
export GEOIP_DATABASE := 'tmp/GeoIP2-Country.mmdb'
export ENVIRONMENT := 'development'

[private]
default:
    just --list

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
    go run -tags production .

# run all tests
test:
    go test ./...

# run all linters
lint:
    pnpm run lint
    golangci-lint run
    just --fmt --unstable --check

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

# run data migration from a mariadb dump
migrate dump:
    #!/usr/bin/env bash
    set -euo pipefail

    docker rm -f pkgstats-migrate-mariadb || true

    docker run --rm --detach --name pkgstats-migrate-mariadb \
        --env MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 \
        --env MARIADB_DATABASE=pkgstats_archlinux_de \
        -p 3306:3306 \
        --volume '{{ absolute_path(dump) }}:/docker-entrypoint-initdb.d/{{ file_name(dump) }}:ro' \
        --tmpfs '/var/lib/mysql' \
        mariadb:12

    until docker logs pkgstats-migrate-mariadb 2>&1 | grep -q "MariaDB init process done. Ready for start up."; do
        sleep 1
    done

    docker exec pkgstats-migrate-mariadb mariadb-admin ping --wait > /dev/null 2>&1

    rm -f '{{ DATABASE }}'
    go run ./cmd/migrate-data -mariadb 'root@tcp(localhost:3306)/pkgstats_archlinux_de'

    docker rm -f pkgstats-migrate-mariadb
