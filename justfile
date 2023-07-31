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

default:
	just --list

init: start
	{{PHP-DB-RUN}} bin/console cache:warmup
	{{PHP-DB-RUN}} bin/console doctrine:database:drop --force --if-exists
	{{PHP-DB-RUN}} bin/console doctrine:database:create
	{{PHP-DB-RUN}} bin/console doctrine:schema:create
	{{PHP-DB-RUN}} bin/console doctrine:migrations:sync-metadata-storage --no-interaction
	{{PHP-DB-RUN}} bin/console doctrine:migrations:version --add --all --no-interaction
	{{PHP-DB-RUN}} bin/console doctrine:fixtures:load -n

start:
	{{COMPOSE}} up -d
	{{MARIADB-RUN}} mariadb-admin -uroot -hmariadb --wait=10 ping
	@echo URL: http://localhost:${PORT}

start-db:
	{{COMPOSE}} up -d mariadb
	{{MARIADB-RUN}} mariadb-admin -uroot -hmariadb --wait=10 ping

stop:
	{{COMPOSE}} stop

# Load a (gzipped) database backup for local testing
import-db-dump file name='pkgstats_archlinux_de': start
	{{MARIADB-RUN}} mariadb-admin -uroot -hmariadb drop -f {{name}} || true
	{{MARIADB-RUN}} mariadb-admin -uroot -hmariadb create {{name}}
	zcat {{file}} | {{MARIADB-RUN}} mariadb -uroot -hmariadb {{name}}
	{{PHP-DB-RUN}} bin/console doctrine:migrations:sync-metadata-storage --no-interaction
	{{PHP-DB-RUN}} bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

clean:
	{{COMPOSE}} rm -vsf
	git clean -fdqx -e .idea

rebuild: clean
	{{COMPOSE}} -f docker/cypress-run.yml -f docker/cypress-open.yml build --pull
	just install
	just init

install:
	{{PHP-RUN}} composer --no-interaction install
	{{NODE-RUN}} yarn install --non-interactive --frozen-lockfile

compose *args:
	{{COMPOSE}} {{args}}

compose-run *args:
	{{COMPOSE-RUN}} {{args}}

php *args='-h':
	{{PHP-RUN}} php {{args}}

composer *args:
	{{PHP-RUN}} composer {{args}}

composer-outdated: (composer "install") (composer "outdated --direct --strict")

console *args:
	{{PHP-RUN}} bin/console {{args}}

phpunit *args:
	{{PHP-RUN}} vendor/bin/phpunit {{args}}

phpstan *args:
	{{PHP-RUN}} php -dmemory_limit=-1 vendor/bin/phpstan {{args}}

node *args='-h':
	{{NODE-RUN}} node {{args}}

yarn *args='-h':
	{{NODE-RUN}} yarn {{args}}

jest *args:
	{{NODE-RUN}} node_modules/.bin/jest --passWithNoTests {{args}}

cypress *args:
	{{COMPOSE}} -f docker/cypress-run.yml run --rm --no-deps --entrypoint cypress cypress-run {{args}}

cypress-run *args:
	{{COMPOSE}} -f docker/cypress-run.yml run --rm --no-deps cypress-run --headless --browser chrome --project tests/e2e {{args}}

cypress-open *args:
	Xephyr :${PORT} -screen 1920x1080 -resizeable -name Cypress -title "Cypress - {{ env_var('PROJECT_NAME') }}" -terminate -no-host-grab -extension MIT-SHM -extension XTEST -nolisten tcp &
	DISPLAY=:${PORT} DISPLAY_SOCKET=/tmp/.X11-unix/X${PORT%%:*} {{COMPOSE}} -f docker/cypress-open.yml run --rm --no-deps cypress-open --project tests/e2e --e2e {{args}}

test-php:
	{{PHP-RUN}} composer validate
	{{PHP-RUN}} vendor/bin/phpcs
	{{PHP-RUN}} bin/console lint:container
	{{PHP-RUN}} bin/console lint:yaml --parse-tags config
	{{PHP-RUN}} bin/console lint:twig templates
	{{PHP-RUN}} php -dmemory_limit=-1 vendor/bin/phpstan analyse
	{{PHP-RUN}} vendor/bin/phpunit

test-js:
	{{NODE-RUN}} node_modules/.bin/eslint '*.js' src tests --ext js --ext vue
	{{NODE-RUN}} node_modules/.bin/stylelint 'src/assets/css/**/*.scss' 'src/assets/css/**/*.css' 'src/**/*.vue'
	{{NODE-RUN}} node_modules/.bin/jest --passWithNoTests
	{{NODE-RUN}} yarn build --output-path $(mktemp -d)

test: test-php test-js

test-e2e:
	#!/usr/bin/env bash
	set -e
	if [ "${CI-}" = "true" ]; then
		git clean -xdf app/dist
		just init
		just yarn build
		CYPRESS_baseUrl=http://nginx:8081 just cypress-run
	else
		just cypress-run
	fi

test-db *args: start-db
	{{PHP-DB-RUN}} vendor/bin/phpunit -c phpunit-db.xml {{args}}

test-db-migrations *args: start-db
	{{PHP-DB-RUN}} vendor/bin/phpunit -c phpunit-db.xml --testsuite 'Doctrine Migrations Test' {{args}}

test-coverage:
	{{NODE-RUN}} node_modules/.bin/jest --passWithNoTests --coverage --coverageDirectory var/coverage/jest
	{{PHP-RUN}} php -d zend_extension=xdebug -d xdebug.mode=coverage -d memory_limit=-1 vendor/bin/phpunit --coverage-html var/coverage/phpunit

test-db-coverage: start-db
	{{PHP-RUN}} php -d zend_extension=xdebug -d xdebug.mode=coverage -d memory_limit=-1 vendor/bin/phpunit --coverage-html var/coverage -c phpunit-db.xml

test-security: (composer "audit")
	{{NODE-RUN}} yarn audit --groups dependencies

fix-code-style:
	{{PHP-RUN}} vendor/bin/phpcbf || true
	{{NODE-RUN}} node_modules/.bin/eslint '*.js' src tests --ext js --ext vue --fix
	{{NODE-RUN}} node_modules/.bin/stylelint --fix 'src/assets/css/**/*.scss' 'src/assets/css/**/*.css' 'src/**/*.vue'

_update-cypress-image:
	#!/usr/bin/env bash
	set -e
	CYPRESS_VERSION=$(curl -sSf 'https://hub.docker.com/v2/repositories/cypress/included/tags/?page_size=1' | jq -r '."results"[]["name"]')
	sed -E "s#(cypress/included:).+#\1${CYPRESS_VERSION}#g" -i docker/cypress-*.yml

update:
	{{PHP-RUN}} composer --no-interaction update
	{{PHP-RUN}} composer --no-interaction update --lock --no-scripts
	{{NODE-RUN}} yarn upgrade --non-interactive --latest
	just _update-cypress-image

deploy:
	cd app && yarn install --non-interactive --frozen-lockfile --production
	cd app && yarn build
	cd app && find dist -type f -atime +512 -delete # needs to be above the highest TTL
	cd app && find dist -type d -empty -delete
	cd api && composer --no-interaction install --prefer-dist --no-dev --optimize-autoloader --classmap-authoritative
	cd api && composer dump-env prod
	systemctl restart php-fpm@pkgstats.service
	cd api && bin/console doctrine:migrations:sync-metadata-storage --no-interaction
	cd api && bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

deploy-permissions:
	cd api && sudo setfacl -dR -m u:php-pkgstats:rwX -m u:deployer:rwX var
	cd api && sudo setfacl -R -m u:php-pkgstats:rwX -m u:deployer:rwX var

# vim: set ft=make :
