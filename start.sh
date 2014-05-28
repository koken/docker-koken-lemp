#!/bin/bash
if [ ! -f /usr/share/nginx/www/storage/configuration/database.php ]; then
  # If SSH is needed
  # SSH_USERPASS=`pwgen -c -n -1 8`
  # mkdir /home/user
  # useradd -G sudo -d /home/user user
  # chown user /home/user
  # echo user:$SSH_USERPASS | chpasswd
  # echo ssh user password: $SSH_USERPASS

  # Setup webroot
  rm -rf /usr/share/nginx/www
  mkdir -p /usr/share/nginx/www

  # Download core.zip / elementary.zip to save time for the end user.
  curl -o /usr/share/nginx/www/core.zip https://s3.amazonaws.com/install.koken.me/releases/latest.zip
  curl -o /usr/share/nginx/www/elementary.zip https://koken-store.s3.amazonaws.com/plugins/be1cb2d9-ed05-2d81-85b4-23282832eb84.zip

  # Move install helpers into place
  mv /index.html /usr/share/nginx/www/index.html
  mv /installer.php /usr/share/nginx/www/installer.php
  mv /pclzip.lib.php /usr/share/nginx/www/pclzip.lib.php

  chown -R www-data:www-data /usr/share/nginx/www
  chmod -R 755 /usr/share/nginx/www

  #mysql has to be started this way as it doesn't work to call from /etc/init.d
  /usr/bin/mysqld_safe &
  sleep 10s
  # Here we generate random passwords (thank you pwgen!). The first two are for mysql users, the last batch for random keys in wp-config.php
  KOKEN_DB="koken"
  MYSQL_PASSWORD=`pwgen -c -n -1 12`
  KOKEN_PASSWORD=`pwgen -c -n -1 12`
  #This is so the passwords show up in logs.
  echo mysql root password: $MYSQL_PASSWORD
  echo koken password: $KOKEN_PASSWORD
  echo $MYSQL_PASSWORD > /mysql-root-pw.txt
  echo $KOKEN_PASSWORD > /koken-db-pw.txt

  sed -e "s/___PWD___/$KOKEN_PASSWORD/" /database.php > /usr/share/nginx/www/database.php

  chown www-data:www-data /usr/share/nginx/www/

  mysqladmin -u root password $MYSQL_PASSWORD
  mysql -uroot -p$MYSQL_PASSWORD -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' IDENTIFIED BY '$MYSQL_PASSWORD' WITH GRANT OPTION; FLUSH PRIVILEGES;"
  mysql -uroot -p$MYSQL_PASSWORD -e "CREATE DATABASE koken; GRANT ALL PRIVILEGES ON koken.* TO 'koken'@'localhost' IDENTIFIED BY '$KOKEN_PASSWORD'; FLUSH PRIVILEGES;"
  killall mysqld

  # Remove placeholder page
  rm /usr/share/nginx/www/index.html
fi

# start all the services
/usr/local/bin/supervisord -n
