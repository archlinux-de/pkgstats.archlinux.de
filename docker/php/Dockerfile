FROM archlinux/base

RUN pacman -Syu --noconfirm --needed php php-intl php-sqlite php-phpdbg php-apcu php-fpm composer geoip2-database git unzip
RUN echo 'de_DE.UTF-8 UTF-8' >> /etc/locale.gen && locale-gen
RUN chmod -R 1777 /run
ADD etc/ /etc
