.EXPORT_ALL_VARIABLES:
.PHONY: all init start start-db stop clean rebuild install shell-php shell-node test test-db test-db-migrations test-coverage test-db-coverage test-ci ci-build ci-update ci-update-commit deploy

UID!=id -u
GID!=id -g
COMPOSE=UID=${UID} GID=${GID} docker-compose -f docker/docker-compose.yml -p pkgstats_archlinux_de
COMPOSE-RUN=${COMPOSE} run --rm -u ${UID}:${GID}
PHP-DB-RUN=${COMPOSE-RUN} php
PHP-RUN=${COMPOSE-RUN} --no-deps php
NODE-RUN=${COMPOSE-RUN} --no-deps encore
MARIADB-RUN=${COMPOSE-RUN} --no-deps mariadb

all: install

init: start
	${PHP-DB-RUN} bin/console cache:warmup
	${PHP-DB-RUN} bin/console doctrine:database:create
	${PHP-DB-RUN} bin/console doctrine:schema:create
	${PHP-DB-RUN} bin/console doctrine:migrations:version --add --all --no-interaction

start:
	${COMPOSE} up -d
	${MARIADB-RUN} mysqladmin -uroot --wait=10 ping

start-db:
	${COMPOSE} up -d mariadb
	${MARIADB-RUN} mysqladmin -uroot --wait=10 ping

stop:
	${COMPOSE} stop

clean:
	${COMPOSE} down -v
	git clean -fdqx -e .idea

rebuild: clean
	${COMPOSE} build --pull
	${MAKE} install
	${MAKE} init
	${MAKE} stop

install:
	${PHP-RUN} composer --no-interaction install
	${NODE-RUN} yarn install

shell-php:
	${PHP-DB-RUN} bash

shell-node:
	${NODE-RUN} bash

test:
	${PHP-RUN} vendor/bin/phpcs
	${NODE-RUN} node_modules/.bin/standard 'assets/js/**/*.js' '*.js'
	${NODE-RUN} node_modules/.bin/stylelint 'assets/css/**/*.scss' 'assets/css/**/*.css'
	${PHP-RUN} bin/console lint:yaml config
	${PHP-RUN} bin/console lint:twig templates
	${PHP-RUN} vendor/bin/phpunit

test-db: start-db
	${PHP-DB-RUN} vendor/bin/phpunit -c phpunit-db.xml

test-db-migrations: start-db
	${PHP-DB-RUN} vendor/bin/phpunit -c phpunit-db.xml tests/Migrations/

test-coverage:
	${PHP-RUN} phpdbg -qrr -d memory_limit=-1 vendor/bin/phpunit --coverage-html var/coverage

test-db-coverage: start-db
	${PHP-RUN} phpdbg -qrr -d memory_limit=-1 vendor/bin/phpunit --coverage-html var/coverage -c phpunit-db.xml

test-ci:
	${NODE-RUN} node_modules/.bin/encore production
	${MAKE} test
	${MAKE} test-db

ci-build: install
	${MAKE} test-ci
	if [ "$${TRAVIS_EVENT_TYPE}" = "cron" ]; then ${MAKE} ci-update; fi
	${PHP-RUN} bin/console security:check

ci-update-commit:
	git config --local user.name "$${GH_NAME}"
	git config --local user.email "$${GH_EMAIL}"
	git add -A
	git commit -m"Update dependencies"
	git remote add origin-push https://$${GH_USER}:$${GH_TOKEN}@github.com/$${TRAVIS_REPO_SLUG}.git
	git push --set-upstream origin-push "$${TRAVIS_BRANCH}"

ci-update:
	${PHP-RUN} composer --no-interaction update
	${PHP-RUN} rm -rf var/cache/*
	${NODE-RUN} yarn upgrade --latest
	${MAKE} test-ci
	git checkout "$${TRAVIS_BRANCH}"
	if ! git diff-index --quiet HEAD; then ${MAKE} ci-update-commit; fi

deploy:
	chmod o-x .
	composer --no-interaction install --prefer-dist --no-dev --optimize-autoloader
	yarn install
	bin/console cache:clear --no-debug --no-warmup
	yarn run encore production
	bin/console cache:warmup
	bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
	chmod o+x .
