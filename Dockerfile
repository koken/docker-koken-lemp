FROM phusion/baseimage:0.9.12
MAINTAINER Brad Daily <brad@koken.me>

ENV HOME /root

# Use baseimage-docker's init system.
CMD ["/sbin/my_init"]

# Install required packages
# LANG=C.UTF-8 line is needed for ondrej/php5 repository
RUN \
	export LANG=C.UTF-8 && \
	add-apt-repository ppa:jon-severinsson/ffmpeg && \
	add-apt-repository ppa:ondrej/php5 && \
	add-apt-repository -y ppa:nginx/stable && \
	apt-get update && \
	apt-get -y install nginx mysql-server mysql-client php5-fpm php5-mysql php5-curl php5-mcrypt graphicsmagick ffmpeg pwgen curl unzip

# Configuration
RUN \
	sed -i -e"s/events\s{/events {\n\tuse epoll;/" /etc/nginx/nginx.conf && \
	sed -i -e"s/keepalive_timeout\s*65/keepalive_timeout 2;\n\tclient_max_body_size 100m;\n\tport_in_redirect off/" /etc/nginx/nginx.conf && \
	echo "daemon off;" >> /etc/nginx/nginx.conf && \
	sed -i -e "s/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/g" /etc/php5/fpm/php.ini && \
	sed -i -e "s/upload_max_filesize\s*=\s*2M/upload_max_filesize = 100M/g" /etc/php5/fpm/php.ini && \
	sed -i -e "s/post_max_size\s*=\s*8M/post_max_size = 101M/g" /etc/php5/fpm/php.ini && \
	sed -i -e "s/;daemonize\s*=\s*yes/daemonize = no/g" /etc/php5/fpm/php-fpm.conf && \
	sed -i -e "s/;pm.max_requests\s*=\s*500/pm.max_requests = 500/g" /etc/php5/fpm/pool.d/www.conf && \
	echo "env[KOKEN_HOST] = 'koken-docker-lemp'" >> /etc/php5/fpm/pool.d/www.conf

# nginx site conf
ADD ./conf/nginx-site.conf /etc/nginx/sites-available/default

# Create needed directories
RUN \
	mkdir -p /etc/service/nginx && \
	mkdir -p /etc/service/mysql && \
	mkdir -p /etc/service/php-fpm && \
	mkdir -p /etc/my_init.d

# Add runit files for each service
ADD ./services/nginx /etc/service/nginx/run
ADD ./services/mysql /etc/service/mysql/run
ADD ./services/php-fpm /etc/service/php-fpm/run

# Installation helpers
ADD ./php/index.php /installer.php
ADD ./php/pclzip.lib.php /pclzip.lib.php
ADD ./php/database.php /database.php
ADD ./php/user_setup.php /user_setup.php

# Startup script
ADD ./shell/start.sh /etc/my_init.d/001_koken.sh

# Cron
ADD ./shell/koken.sh /etc/cron.daily/koken

# Execute permissions where needed
RUN \
	chmod +x /etc/service/nginx/run && \
	chmod +x /etc/service/mysql/run && \
	chmod +x /etc/service/php-fpm/run && \
	chmod +x /etc/cron.daily/koken && \
	chmod +x /etc/my_init.d/001_koken.sh

# Expose 8080 to the host
EXPOSE 8080

# Disable SSH
RUN rm -rf /etc/service/sshd /etc/my_init.d/00_regen_ssh_host_keys.sh

# Clean up APT when done.
RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*
