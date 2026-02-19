set quiet := true

export PORT := '8182'
export DATABASE := 'tmp/pkgstats.db'
export GEOIP_DATABASE := 'tmp/GeoIP2-Country.mmdb'
export ENVIRONMENT := 'development'

[private]
default:
    just --list

install:
    curl -sf 'https://raw.githubusercontent.com/maxmind/MaxMind-DB/main/test-data/GeoIP2-Country-Test.mmdb' -o '{{ GEOIP_DATABASE }}'
    go mod download
    pnpm install

build-assets:
    pnpm run build

build-templates:
    go generate ./...
    go tool templ generate

build: build-assets build-templates
    CGO_ENABLED=0 go build -o pkgstatsd -ldflags="-s -w" -trimpath

run:
    go run .

test:
    go test ./...

lint:
    pnpm run lint
    golangci-lint run
    just --fmt --unstable --check

fmt:
    pnpm run format
    go tool templ fmt .
    golangci-lint fmt
    just --fmt --unstable

clean:
    git clean -fdqx

update:
    pnpm update --latest
    #sed -E '/^go\s+[0-9\.]+$/d' -i go.mod
    go get -u -t all
    go mod tidy

coverage:
    #!/usr/bin/env bash
    set -euo pipefail
    COVER_FILE=$(mktemp)
    go test -coverpkg=./... -coverprofile "$COVER_FILE" ./...
    go tool cover -html="$COVER_FILE"
    rm -f "$COVER_FILE"

# generate Go fixtures for local development
fixtures months="3":
    go run ./cmd/fixtures -db '{{ DATABASE }}' -months {{ months }}

anomaly-detection:
    go run ./cmd/anomaly-detection

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
    go run ./cmd/migrate-data -mariadb 'root@tcp(localhost:3306)/pkgstats_archlinux_de' -sqlite '{{ DATABASE }}'

    docker rm -f pkgstats-migrate-mariadb
