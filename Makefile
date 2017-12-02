.PHONY: all init start stop restart clean rebuild composer-update shell test ci-test deploy assets coverage

APP-RUN=docker-compose run --rm -u $$(id -u) app
DB-RUN=docker-compose run --rm db
COMPOSER=composer --no-interaction

all: init

init: start
	${APP-RUN} bin/console doctrine:database:create
	${APP-RUN} bin/console doctrine:schema:create

start: vendor assets
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

composer-update:
	${APP-RUN} ${COMPOSER} update

composer.lock: composer.json
	${APP-RUN} ${COMPOSER} update nothing

vendor: composer.lock
	${APP-RUN} ${COMPOSER} install

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

assets:
	${APP-RUN} yarn install
	${APP-RUN} yarn run encore dev

coverage:
	${APP-RUN} php -d zend_extension=xdebug.so vendor/bin/phpunit --coverage-html var/coverage

deploy:
	chmod o-x .
	APP_ENV=prod composer --no-interaction install --no-dev --optimize-autoloader
	yarn install
	APP_ENV=prod bin/console cache:clear --env=prod --no-debug --no-warmup
	yarn run encore production
	APP_ENV=prod bin/console cache:warmup --env=prod
	chmod o+x .
