export UID := `id -u`
export GID := `id -g`

COMPOSE := 'docker-compose -f docker/app.yml ' + `[ "${CI-}" != "true" ] && echo '-f docker/dev.yml' || echo ''` + ' -p ' + env_var('PROJECT_NAME')
COMPOSE-RUN := COMPOSE + ' run --rm'
PHP-DB-RUN := COMPOSE-RUN + ' api'
PHP-RUN := COMPOSE-RUN + ' --no-deps api'
NODE-RUN := COMPOSE-RUN + ' --no-deps -e DISABLE_OPENCOLLECTIVE=true app'
MARIADB-RUN := COMPOSE-RUN + ' --no-deps mariadb'

default:
	just --list

init: start
	{{PHP-DB-RUN}} bin/console cache:warmup
	{{PHP-DB-RUN}} bin/console doctrine:database:create
	{{PHP-DB-RUN}} bin/console doctrine:schema:create
	{{PHP-DB-RUN}} bin/console doctrine:migrations:sync-metadata-storage --no-interaction
	{{PHP-DB-RUN}} bin/console doctrine:migrations:version --add --all --no-interaction

start:
	{{COMPOSE}} up -d
	{{MARIADB-RUN}} mysqladmin -uroot -hmariadb --wait=10 ping
	@echo URL: http://localhost:${PORT}

start-db:
	{{COMPOSE}} up -d mariadb
	{{MARIADB-RUN}} mysqladmin -uroot -hmariadb --wait=10 ping

stop:
	{{COMPOSE}} stop

# Load a (gzipped) database backup for local testing
import-db-dump file name='pkgstats_archlinux_de': start
	{{MARIADB-RUN}} mysqladmin -uroot -hmariadb drop -f {{name}} || true
	{{MARIADB-RUN}} mysqladmin -uroot -hmariadb create {{name}}
	zcat {{file}} | {{MARIADB-RUN}} mysql -uroot -hmariadb {{name}}
	{{PHP-DB-RUN}} bin/console doctrine:migrations:sync-metadata-storage --no-interaction
	{{PHP-DB-RUN}} bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

clean:
	{{COMPOSE}} down -v
	git clean -fdqx -e .idea

rebuild: clean
	{{COMPOSE}} build --pull --parallel
	just install
	just init
	just stop

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

rector *args:
	{{COMPOSE-RUN}} rector {{args}}

node *args='-h':
	{{NODE-RUN}} node {{args}}

yarn *args='-h':
	{{NODE-RUN}} yarn {{args}}

jest *args:
	{{NODE-RUN}} node_modules/.bin/jest {{args}}

cypress-run *args:
	{{COMPOSE}} -f docker/cypress-run.yml run     --rm --no-deps cypress run  --project tests/e2e --browser chrome --headless {{args}}

cypress-open:
	xhost +local:root
	{{COMPOSE}} -f docker/cypress-open.yml run -d --rm --no-deps cypress open --project tests/e2e

test:
	{{PHP-RUN}} composer validate
	{{PHP-RUN}} vendor/bin/phpcs
	{{NODE-RUN}} node_modules/.bin/eslint src --ext js --ext vue
	{{NODE-RUN}} node_modules/.bin/stylelint 'src/assets/css/**/*.scss' 'src/assets/css/**/*.css' 'src/**/*.vue'
	{{NODE-RUN}} node_modules/.bin/jest
	{{PHP-RUN}} bin/console lint:container
	{{PHP-RUN}} bin/console lint:yaml config
	{{PHP-RUN}} bin/console lint:twig templates
	{{NODE-RUN}} yarn build --dest $(mktemp -d)
	{{PHP-RUN}} php -dmemory_limit=-1 vendor/bin/phpstan analyse
	{{PHP-RUN}} vendor/bin/phpunit

test-e2e:
	if [ "${CI-}" = "true" ]; then just init; fi
	just cypress-run

test-db: start-db
	{{PHP-DB-RUN}} vendor/bin/phpunit -c phpunit-db.xml

test-db-migrations: start-db
	{{PHP-DB-RUN}} vendor/bin/phpunit -c phpunit-db.xml --testsuite 'Doctrine Migrations Test'

test-coverage:
	{{NODE-RUN}} node_modules/.bin/jest --coverage --coverageDirectory var/coverage/jest
	{{PHP-RUN}} phpdbg -qrr -d memory_limit=-1 vendor/bin/phpunit --coverage-html var/coverage/phpunit

test-db-coverage: start-db
	{{PHP-RUN}} phpdbg -qrr -d memory_limit=-1 vendor/bin/phpunit --coverage-html var/coverage -c phpunit-db.xml

test-security:
	{{PHP-RUN}} bin/console security:check
	{{NODE-RUN}} yarn audit --groups dependencies

fix-code-style:
	{{PHP-RUN}} vendor/bin/phpcbf || true
	{{NODE-RUN}} node_modules/.bin/eslint src --fix --ext js --ext vue
	{{NODE-RUN}} node_modules/.bin/stylelint --fix 'src/assets/css/**/*.scss' 'src/assets/css/**/*.css' 'src/**/*.vue'

_update-cypress-image:
	#!/usr/bin/env sh
	CYPRESS_VERSION=$(curl -sSf 'https://hub.docker.com/v2/repositories/cypress/included/tags/?page_size=1' | jq -r '."results"[]["name"]')
	sed -E "s#(cypress/included:)[0-9.]+#\1${CYPRESS_VERSION}#g" -i docker/cypress-*.yml

update:
	{{PHP-RUN}} composer --no-interaction update
	{{PHP-RUN}} composer --no-interaction update --lock --no-scripts
	{{NODE-RUN}} yarn upgrade --non-interactive --latest
	# Downgrade plugin as it would require Webpack 5
	{{NODE-RUN}} yarn upgrade "compression-webpack-plugin@~6.1.1" "copy-webpack-plugin@~6.4.0"
	just _update-cypress-image

deploy:
	cd app && yarn install --non-interactive --frozen-lockfile
	cd app && yarn build --no-clean
	cd app && find dist -type f -mtime +30 -delete
	cd app && find dist -type d -empty -delete
	cd api && composer --no-interaction install --prefer-dist --no-dev --optimize-autoloader
	cd api && composer dump-env prod
	systemctl restart php-fpm@pkgstats.service
	cd api && bin/console doctrine:migrations:sync-metadata-storage --no-interaction
	cd api && bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

deploy-permissions:
	cd api && sudo setfacl -dR -m u:php-www:rwX -m u:deployer:rwX var
	cd api && sudo setfacl -R -m u:php-www:rwX -m u:deployer:rwX var

# vim: set ft=make :
