FROM php:8.1-fpm-alpine

RUN apk add --no-cache git

COPY --from=mlocati/php-extension-installer:1.5 /usr/bin/install-php-extensions /usr/bin/
RUN install-php-extensions opcache apcu intl pdo_mysql sysvsem

COPY --from=composer:2.3 /usr/bin/composer /usr/bin/composer

ADD https://github.com/maxmind/MaxMind-DB/raw/main/test-data/GeoIP2-Country-Test.mmdb /usr/share/GeoIP/GeoLite2-Country.mmdb
RUN chmod 644 /usr/share/GeoIP/GeoLite2-Country.mmdb
