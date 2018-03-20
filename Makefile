.PHONY: all init start stop restart clean rebuild install shell test ci-test deploy coverage

APP-RUN=docker-compose run --rm -u $$(id -u) app
DB-RUN=docker-compose run --rm db
COMPOSER=composer --no-interaction

all: init

init: start
	${APP-RUN} bin/console cache:warmup
	${APP-RUN} bin/console doctrine:database:create
	${APP-RUN} bin/console doctrine:schema:create

start: install
	docker-compose up -d
	${DB-RUN} mysqladmin -uroot --wait=10 ping

stop:
	docker-compose stop

restart:
	${MAKE} stop
	${MAKE} start

clean:
	docker-compose down -v
	git clean -fdqx -e .idea

rebuild: clean
	docker-compose build --no-cache --pull
	${MAKE}

install:
	${APP-RUN} ${COMPOSER} install
	${APP-RUN} yarn install

shell:
	${APP-RUN} bash

test:
	${APP-RUN} vendor/bin/phpcs
	${APP-RUN} node_modules/.bin/standard 'assets/js/**/*.js' '*.js'
	${APP-RUN} node_modules/.bin/stylelint 'assets/css/**/*.scss' 'assets/css/**/*.css'
	${APP-RUN} vendor/bin/phpunit

ci-test: init
	${MAKE} test
	${APP-RUN} vendor/bin/security-checker security:check
	${APP-RUN} node_modules/.bin/encore dev

coverage:
	${APP-RUN} phpdbg -qrr -d memory_limit=-1 vendor/bin/phpunit --coverage-html var/coverage

deploy:
	chmod o-x .
	composer --no-interaction install --no-dev --optimize-autoloader
	yarn install
	bin/console cache:clear --no-debug --no-warmup
	yarn run encore production
	bin/console cache:warmup
	chmod o+x .
