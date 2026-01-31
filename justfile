set dotenv-load := true

export UID := `id -u`
export GID := `id -g`
export COMPOSE_PROFILES := if env_var_or_default("CI", "0") == "true" { "test" } else { "dev" }

COMPOSE := 'docker compose -f docker/app.yml -p ' + env_var('PROJECT_NAME')
COMPOSE-RUN := COMPOSE + ' run --rm'
PHP-DB-RUN := COMPOSE-RUN + ' api'
PHP-RUN := COMPOSE-RUN + ' --no-deps api'
NODE-RUN := COMPOSE-RUN + ' --no-deps app'
MARIADB-RUN := COMPOSE-RUN + ' -T --no-deps mariadb'

# default command run when entering "just" only
default:
	just --list

# load fixture data and startup all services defined in docker/app.yml
init: start
	{{PHP-DB-RUN}} bin/console cache:warmup
	{{PHP-DB-RUN}} bin/console doctrine:database:drop --force --if-exists
	{{PHP-DB-RUN}} bin/console doctrine:database:create
	{{PHP-DB-RUN}} bin/console doctrine:schema:create
	{{PHP-DB-RUN}} bin/console doctrine:migrations:sync-metadata-storage --no-interaction
	{{PHP-DB-RUN}} bin/console doctrine:migrations:version --add --all --no-interaction
	{{PHP-DB-RUN}} php -dmemory_limit=-1 bin/console doctrine:fixtures:load -n

# start all services defined in docker/app.yml
start:
	{{COMPOSE}} up -d
	@echo URL: http://localhost:${PORT}

# start mariadb service defined in docker/app.yml
start-db:
	{{COMPOSE}} up -d mariadb

# stop all services defined in docker/app.yml
stop:
	{{COMPOSE}} stop

# Load a (gzipped) database backup for local testing
import-db-dump file name='pkgstats_archlinux_de': start
	{{MARIADB-RUN}} mariadb-admin -uroot -hmariadb --skip-ssl drop -f {{name}} || true
	{{MARIADB-RUN}} mariadb-admin -uroot -hmariadb --skip-ssl create {{name}}
	zcat {{file}} | {{MARIADB-RUN}} mariadb -uroot -hmariadb --skip-ssl {{name}}
	{{PHP-DB-RUN}} bin/console doctrine:migrations:sync-metadata-storage --no-interaction
	{{PHP-DB-RUN}} bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# remove containers and untracked files
clean:
	{{COMPOSE}} rm -vsf
	git clean -fdqx -e .idea

# remove containers & untracked files, rebuild all containers, install dependencies and run "just init"
rebuild: clean
	{{COMPOSE}} -f docker/cypress-run.yml -f docker/cypress-open.yml build --pull
	just install
	just init

# install php & js dependencies
install:
	{{PHP-RUN}} composer --no-interaction install
	{{NODE-RUN}} pnpm install --frozen-lockfile

# short for "docker compose -f docker/app.yml -p {{args}}"
compose *args:
	{{COMPOSE}} {{args}}

# short for "docker compose -f docker/app.yml -p run --rm {{args}}"
compose-run *args:
	{{COMPOSE-RUN}} {{args}}

# short for "short for "docker compose -f docker/app.yml -p run --rm php {{args}}"
php *args='-h':
	{{PHP-RUN}} php {{args}}

# run composer inside a php container
composer *args:
	{{PHP-RUN}} composer {{args}}

# list outdated php dependencies
composer-outdated: (composer "install") (composer "outdated --direct --strict")

# list outdated js dependencies
pnpm-outdated: (pnpm "install --frozen-lockfile") (pnpm "outdated")

# list outdated dependencies
outdated: composer-outdated pnpm-outdated

# direct access to Sf console commands
console *args:
	{{PHP-RUN}} bin/console {{args}}

# run phpunit inside a php container
phpunit *args:
	{{PHP-RUN}} php -dmemory_limit=-1 vendor/bin/phpunit {{args}}

# run phpstan inside a php container
phpstan *args:
	{{PHP-RUN}} php -dmemory_limit=-1 vendor/bin/phpstan {{args}}

# run rector inside the php container
rector *args:
	{{PHP-RUN}} php -dmemory_limit=-1 vendor/bin/rector {{args}}

# run node inside a node container
node *args='-h':
	{{NODE-RUN}} node {{args}}

# run pnpm inside a php container
pnpm *args='-h':
	{{NODE-RUN}} pnpm {{args}}

# run jest inside a node container
jest *args:
	{{NODE-RUN}} node_modules/.bin/jest --passWithNoTests {{args}}

# run cypress-run command
cypress *args:
	{{COMPOSE}} -f docker/cypress-run.yml run --rm --no-deps --entrypoint cypress cypress-run {{args}}

# run cypress tests in CLI
cypress-run *args:
	{{COMPOSE}} -f docker/cypress-run.yml run --rm --no-deps cypress-run --headless --browser chrome --project tests/e2e {{args}}

# open cypress GUI to interactively run tests
cypress-open *args:
	Xephyr :${PORT} -screen 1920x1080 -resizeable -name Cypress -title "Cypress - {{ env_var('PROJECT_NAME') }}" -terminate -no-host-grab -extension MIT-SHM -extension XTEST -nolisten tcp &
	DISPLAY=:${PORT} DISPLAY_SOCKET=/tmp/.X11-unix/X${PORT%%:*} {{COMPOSE}} -f docker/cypress-open.yml run --rm --no-deps cypress-open --project tests/e2e --e2e {{args}}

# execute all php tests (validate composer, linting, phpunit)
test-php:
	{{PHP-RUN}} composer validate
	{{PHP-RUN}} vendor/bin/phpcs
	{{PHP-RUN}} bin/console lint:container
	{{PHP-RUN}} bin/console lint:yaml --parse-tags config
	{{PHP-RUN}} bin/console lint:twig templates
	{{PHP-RUN}} php -dmemory_limit=-1 vendor/bin/phpstan analyse
	{{PHP-RUN}} php -dmemory_limit=-1 vendor/bin/rector --dry-run
	{{PHP-RUN}} php -dmemory_limit=-1 vendor/bin/phpunit

# execute all js tests (linting, jest, build project)
test-js:
	{{NODE-RUN}} node_modules/.bin/eslint
	{{NODE-RUN}} node_modules/.bin/stylelint 'src/assets/css/**/*.scss' 'src/assets/css/**/*.css' 'src/**/*.vue'
	{{NODE-RUN}} node_modules/.bin/jest --passWithNoTests
	{{NODE-RUN}} pnpm run build --output-path $(mktemp -d)

# run all tests
test: test-php test-js

# run e2e tests
test-e2e:
	#!/usr/bin/env bash
	set -e
	if [ "${CI-}" = "true" ]; then
		git clean -xdf app/dist
		just init
		just pnpm run build
		CYPRESS_baseUrl=http://nginx:8081 just cypress-run
	else
		just cypress-run
	fi

# run database tests
test-db *args: start-db
	{{PHP-DB-RUN}} vendor/bin/phpunit -c phpunit-db.xml {{args}}

# test db migrations
test-db-migrations *args: start-db
	{{PHP-DB-RUN}} vendor/bin/phpunit -c phpunit-db.xml --testsuite 'Doctrine Migrations Test' {{args}}

# use jest and phpunit to generate code coverage reports
test-coverage:
	{{NODE-RUN}} node_modules/.bin/jest --passWithNoTests --coverage --coverageDirectory var/coverage/jest
	{{PHP-RUN}} php -d extension=pcov -d memory_limit=-1 vendor/bin/phpunit --coverage-html var/coverage/phpunit

# run phpunit to generate db code coverage report
test-db-coverage: start-db
	{{PHP-RUN}} php -d extension=pcov -d memory_limit=-1 vendor/bin/phpunit --coverage-html var/coverage -c phpunit-db.xml

# run composer and pnpm audit commands
test-security: (composer "audit")
	{{NODE-RUN}} pnpm audit --prod

# run phpcbf, eslint and stylelint to fix cs
fix-code-style:
	{{PHP-RUN}} vendor/bin/phpcbf || true
	{{NODE-RUN}} node_modules/.bin/eslint --fix
	{{NODE-RUN}} node_modules/.bin/stylelint --fix=strict 'src/assets/css/**/*.scss' 'src/assets/css/**/*.css' 'src/**/*.vue'

# update all project dependencies
update:
	{{PHP-RUN}} composer --no-interaction update
	{{PHP-RUN}} composer --no-interaction update --lock --no-scripts
	{{NODE-RUN}} pnpm update --latest

# runs on server after deploy; install deps, build assets, run migrations
deploy:
	cd app && pnpm install --frozen-lockfile --prod
	cd app && NODE_OPTIONS=--no-experimental-webstorage pnpm run build
	cd app && find dist -type f -atime +512 -delete # needs to be above the highest TTL
	cd app && find dist -type d -empty -delete
	cd api && composer --no-interaction install --prefer-dist --no-dev --optimize-autoloader --classmap-authoritative
	cd api && composer dump-env prod
	systemctl restart php-fpm@pkgstats.service
	cd api && bin/console doctrine:migrations:sync-metadata-storage --no-interaction
	cd api && bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# runs on server after deploy; sets correct permissions
deploy-permissions:
	cd api && sudo setfacl -dR -m u:php-pkgstats:rwX -m u:deployer:rwX var
	cd api && sudo setfacl -R -m u:php-pkgstats:rwX -m u:deployer:rwX var

# Go targets

# build Go binary
go-build:
	go build -trimpath -ldflags="-s -w" -o bin/pkgstatsd .

# run Go tests
go-test *args:
	go test ./... {{args}}

# run Go linter
go-lint:
	golangci-lint run

# format Go code
go-fmt:
	golangci-lint run --fix
	gofumpt -w .

# generate test coverage report
go-coverage:
	go test -coverprofile=coverage.out ./...
	go tool cover -html=coverage.out -o coverage.html

# run Go server locally
go-run:
	go run .

# build data migration tool
go-migrate-build:
	go build -trimpath -ldflags="-s -w" -o bin/migrate-data ./cmd/migrate-data

# run data migration (requires -mariadb flag)
go-migrate *args:
	go run ./cmd/migrate-data {{args}}

# vim: set ft=make :
