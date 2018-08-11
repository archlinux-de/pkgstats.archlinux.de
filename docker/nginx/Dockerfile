FROM archlinux/base

RUN pacman -Syu --noconfirm --needed nginx
RUN chmod -R 1777 /var/lib/nginx /run
ADD etc/ /etc
