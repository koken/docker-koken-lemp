#!/bin/bash
if [ ! -f /usr/share/nginx/www/storage/configuration/database.php ]; then

  if [ ! -f /var/lib/mysql/ibdata1 ]; then
    mysql_install_db
  fi

  # If SSH is needed
  # SSH_USERPASS=`pwgen -c -n -1 8`
  # mkdir /home/user
  # useradd -G sudo -d /home/user user
  # chown user /home/user
  # echo user:$SSH_USERPASS | chpasswd
  # echo ssh user password: $SSH_USERPASS

  # Start MySQL and wait for it to become available
  /usr/bin/mysqld_safe > /dev/null 2>&1 &

  RET=1
  while [[ RET -ne 0 ]]; do
      echo "=> Waiting for confirmation of MySQL service startup"
      sleep 5
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

  echo "=> Setting up Koken installer"
  # Setup webroot
  rm -rf /usr/share/nginx/www/*
  mkdir -p /usr/share/nginx/www

  # Download core.zip / elementary.zip to save time for the end user.
  curl --silent -o /usr/share/nginx/www/core.zip https://s3.amazonaws.com/install.koken.me/releases/latest.zip
  curl --silent -o /usr/share/nginx/www/elementary.zip https://koken-store.s3.amazonaws.com/plugins/be1cb2d9-ed05-2d81-85b4-23282832eb84.zip

  # Move install helpers into place
  mv /installer.php /usr/share/nginx/www/installer.php
  mv /pclzip.lib.php /usr/share/nginx/www/pclzip.lib.php

  # Configure Koken database connection
  sed -e "s/___PWD___/$KOKEN_PASSWORD/" /database.php > /usr/share/nginx/www/database.php
  chown www-data:www-data /usr/share/nginx/www/
  chmod -R 755 /usr/share/nginx/www
fi

echo "=> Starting supervisord"
/usr/bin/supervisord -c /etc/supervisor/supervisord.conf
