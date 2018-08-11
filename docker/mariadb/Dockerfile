FROM archlinux/base

RUN pacman -Syu --noconfirm mariadb mariadb-clients
ADD etc/ /etc
RUN mysql_install_db --user=mysql --basedir=/usr --datadir=/var/lib/mysql
