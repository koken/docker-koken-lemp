FROM phusion/baseimage:0.9.21
MAINTAINER Brad Daily <brad@koken.me>

ENV HOME /root

# Use baseimage-docker's init system.
CMD ["/sbin/my_init"]

# Install required packages
# LANG=C.UTF-8 line is needed for ondrej/php7 repository
RUN \
	export LANG=C.UTF-8 && \
	export DEBIAN_FRONTEND=noninteractive && \
	add-apt-repository ppa:mc3man/xerus-media && \
	add-apt-repository ppa:ondrej/php && \
	add-apt-repository -y ppa:nginx/stable && \
	#add-apt-repository -y ppa:rwky/graphicsmagick && \
	apt-get update && \
	apt-get -y install nginx mysql-server mysql-client php7.1-fpm php7.1-mysql php7.1-curl php7.1-intl php7.1-mbstring php7.1-mcrypt graphicsmagick ffmpeg pwgen wget unzip

# Configuration
RUN \
	echo "sql_mode=STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION" >> /etc/mysql/mysql.conf.d/mysqld.cnf && \
	sed -i -e"s/events\s{/events {\n\tuse epoll;/" /etc/nginx/nginx.conf && \
	sed -i -e"s/keepalive_timeout\s*65/keepalive_timeout 2;\n\tclient_max_body_size 100m;\n\tport_in_redirect off/" /etc/nginx/nginx.conf && \
	echo "daemon off;" >> /etc/nginx/nginx.conf && \
	sed -i -e "s/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/g" /etc/php/7.1/fpm/php.ini && \
	sed -i -e "s/upload_max_filesize\s*=\s*2M/upload_max_filesize = 100M/g" /etc/php/7.1/fpm/php.ini && \
	sed -i -e "s/post_max_size\s*=\s*8M/post_max_size = 101M/g" /etc/php/7.1/fpm/php.ini && \
	sed -i -e "s/;daemonize\s*=\s*yes/daemonize = no/g" /etc/php/7.1/fpm/php-fpm.conf && \
	sed -i -e "s/;pm.max_requests\s*=\s*500/pm.max_requests = 500/g" /etc/php/7.1/fpm/pool.d/www.conf && \
	echo "env[KOKEN_HOST] = 'koken-docker-lemp'" >> /etc/php/7.1/fpm/pool.d/www.conf && \
	cp /etc/php/7.1/fpm/pool.d/www.conf /etc/php/7.1/fpm/pool.d/images.conf && \
	sed -i -e "s/\[www\]/[images]/" /etc/php/7.1/fpm/pool.d/images.conf && \
	sed -i -e "s#listen\s*=\s*/run/php/php7\.1-fpm\.sock#listen = /run/php/php7.1-fpm-images.sock#" /etc/php/7.1/fpm/pool.d/images.conf && \
	service php7.1-fpm start && \
	mkdir -p /var/run/mysqld && \
	chown mysql:mysql /var/run/mysqld

# nginx site conf
ADD ./conf/nginx-site.conf /etc/nginx/sites-available/default

# Add runit files for each service
ADD ./services/nginx /etc/service/nginx/run
ADD ./services/mysql /etc/service/mysql/run
ADD ./services/php-fpm /etc/service/php-fpm/run
ADD ./services/koken /etc/service/koken/run

# Installation helpers
ADD ./php/index.php /installer.php
ADD ./php/database.php /database.php
ADD ./php/user_setup.php /user_setup.php

# Cron
ADD ./shell/koken.sh /etc/cron.daily/koken

# Startup script
ADD ./shell/start.sh /etc/my_init.d/001_koken.sh

# Execute permissions where needed
RUN \
	chmod +x /etc/service/nginx/run && \
	chmod +x /etc/service/mysql/run && \
	chmod +x /etc/service/php-fpm/run && \
	chmod +x /etc/service/koken/run && \
	chmod +x /etc/cron.daily/koken && \
	chmod +x /etc/my_init.d/001_koken.sh

# Data volumes
VOLUME ["/usr/share/nginx/www", "/var/lib/mysql"]

# Expose 8080 to the host
EXPOSE 8080

# Disable SSH
RUN rm -rf /etc/service/sshd /etc/my_init.d/00_regen_ssh_host_keys.sh

# Clean up APT when done.
RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*
