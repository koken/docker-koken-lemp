FROM phusion/baseimage:0.9.10
MAINTAINER Brad Daily <brad@koken.me>

ENV HOME /root

# Use baseimage-docker's init system.
CMD ["/sbin/my_init"]

# FFMpeg PPA
RUN echo "deb http://ppa.launchpad.net/jon-severinsson/ffmpeg/ubuntu trusty main" >> /etc/apt/sources.list
RUN echo "deb-src http://ppa.launchpad.net/jon-severinsson/ffmpeg/ubuntu trusty main" >> /etc/apt/sources.list
RUN apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 1DB8ADC1CFCA9579

RUN apt-get update
RUN apt-get -y upgrade

# Basic Requirements
RUN apt-get -y install mysql-server mysql-client nginx php5-fpm php5-mysql pwgen curl unzip

# Koken Requirements
RUN apt-get -y install php5-curl graphicsmagick php5-mcrypt ffmpeg

# mysql config
RUN sed -i -e"s/^bind-address\s*=\s*127.0.0.1/bind-address = 0.0.0.0/" /etc/mysql/my.cnf

# nginx config
RUN sed -i -e"s/events\s{/events {\n\tuse epoll;/" /etc/nginx/nginx.conf
RUN sed -i -e"s/keepalive_timeout\s*65/keepalive_timeout 2;\n\tclient_max_body_size 100m/" /etc/nginx/nginx.conf
RUN echo "daemon off;" >> /etc/nginx/nginx.conf

# php-fpm config
RUN sed -i -e "s/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/g" /etc/php5/fpm/php.ini
RUN sed -i -e "s/upload_max_filesize\s*=\s*2M/upload_max_filesize = 100M/g" /etc/php5/fpm/php.ini
RUN sed -i -e "s/post_max_size\s*=\s*8M/post_max_size = 101M/g" /etc/php5/fpm/php.ini
RUN sed -i -e "s/;daemonize\s*=\s*yes/daemonize = no/g" /etc/php5/fpm/php-fpm.conf
RUN sed -i -e "s/;pm.max_requests\s*=\s*500/pm.max_requests = 500/g" /etc/php5/fpm/pool.d/www.conf

# nginx site conf
ADD ./conf/nginx-site.conf /etc/nginx/sites-available/default

# nginx runit
RUN mkdir -p /etc/service/nginx
ADD ./services/nginx /etc/service/nginx/run
RUN chmod +x /etc/service/nginx/run

# mysql runit
RUN mkdir -p /etc/service/mysql
ADD ./services/mysql /etc/service/mysql/run
RUN chmod +x /etc/service/mysql/run

# php-fpm runit
RUN mkdir -p /etc/service/php-fpm
ADD ./services/php-fpm /etc/service/php-fpm/run
RUN chmod +x /etc/service/php-fpm/run

# Koken installer helpers
ADD ./php/index.php /installer.php
ADD ./php/pclzip.lib.php /pclzip.lib.php
ADD ./php/database.php /database.php
ADD ./php/user_setup.php /user_setup.php

# CRON
ADD ./shell/koken.sh /etc/cron.daily/koken
RUN chmod +x /etc/cron.daily/koken

# Initialization and Startup Script
RUN mkdir -p /etc/my_init.d
ADD ./shell/start.sh /etc/my_init.d/001_koken.sh
RUN chmod +x /etc/my_init.d/001_koken.sh

# private expose
EXPOSE 8080

# Clean up APT when done.
RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*
