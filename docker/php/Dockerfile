FROM archlinux/base

RUN pacman -Syu --noconfirm --needed php php-intl php-sqlite php-phpdbg php-apcu php-fpm composer geoipupdate git unzip
RUN echo 'de_DE.UTF-8 UTF-8' >> /etc/locale.gen && locale-gen
RUN chmod -R 1777 /run
ADD etc/ /etc

ADD https://github.com/maxmind/MaxMind-DB/raw/master/test-data/GeoIP2-Country-Test.mmdb /usr/share/GeoIP/GeoLite2-Country.mmdb
RUN chmod 644 /usr/share/GeoIP/GeoLite2-Country.mmdb
