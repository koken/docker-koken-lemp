#!/bin/bash

#########################################################
# The following should be run only if Koken hasn't been #
# installed yet                                         #
#########################################################

if [ ! -f /usr/share/nginx/www/storage/configuration/database.php ] && [ ! -f /usr/share/nginx/www/database.php ]; then

  if [ ! -f /var/lib/mysql/ibdata1 ]; then
    mysql_install_db
  fi

  # Start MySQL and wait for it to become available
  /usr/bin/mysqld_safe > /dev/null 2>&1 &

  RET=1
  while [[ $RET -ne 0 ]]; do
      echo "=> Waiting for confirmation of MySQL service startup"
      sleep 2
      mysql -uroot -e "status" > /dev/null 2>&1
      RET=$?
  done

  # Generate Koken database and user credentials
  echo "=> Generating database and credentials"
  KOKEN_DB="koken"
  MYSQL_PASSWORD=`pwgen -c -n -1 12`
  KOKEN_PASSWORD=`pwgen -c -n -1 12`

  mysqladmin -u root password $MYSQL_PASSWORD
  mysql -uroot -p$MYSQL_PASSWORD -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' IDENTIFIED BY '$MYSQL_PASSWORD' WITH GRANT OPTION; FLUSH PRIVILEGES;"
  mysql -uroot -p$MYSQL_PASSWORD -e "CREATE DATABASE koken; GRANT ALL PRIVILEGES ON koken.* TO 'koken'@'localhost' IDENTIFIED BY '$KOKEN_PASSWORD'; FLUSH PRIVILEGES;"

  mysqladmin -uroot -p$MYSQL_PASSWORD shutdown

  echo "=> Setting up Koken"
  # Setup webroot
  rm -rf /usr/share/nginx/www/*
  mkdir -p /usr/share/nginx/www

  # Move install helpers into place
  mv /installer.php /usr/share/nginx/www/installer.php
  mv /user_setup.php /usr/share/nginx/www/user_setup.php

  # Configure Koken database connection
  sed -e "s/___PWD___/$KOKEN_PASSWORD/" /database.php > /usr/share/nginx/www/database.php
  chown www-data:www-data /usr/share/nginx/www/
  chmod -R 755 /usr/share/nginx/www
fi

################################################################
# The following should be run anytime the container is booted, #
# incase host is resized                                       #
################################################################

# Set PHP pools to take up to 1/2 of total system memory total, split between the two pools.
# 30MB per process is conservative estimate, is usually less than that
PHP_MAX=$(expr $(grep 'MemTotal' /proc/meminfo | sed -e 's/MemTotal://' -e 's/ kB//') / 1024 / 2 / 30 / 2)
sed -i -e"s/pm.max_children = 5/pm.max_children = $PHP_MAX/" /etc/php5/fpm/pool.d/www.conf
sed -i -e"s/pm.max_children = 5/pm.max_children = $PHP_MAX/" /etc/php5/fpm/pool.d/images.conf

# Set nginx worker processes to equal number of CPU cores
sed -i -e"s/worker_processes\s*4/worker_processes $(cat /proc/cpuinfo | grep processor | wc -l)/" /etc/nginx/nginx.conf
