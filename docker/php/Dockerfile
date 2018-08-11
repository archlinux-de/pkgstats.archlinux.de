FROM archlinux/base

RUN pacman -Syu --noconfirm --needed php php-intl php-sqlite php-phpdbg php-apcu php-fpm composer geoip2-database git unzip
RUN chmod -R 1777 /run
ADD etc/ /etc
