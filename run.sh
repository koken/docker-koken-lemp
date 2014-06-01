#!/bin/bash
CHECK=$(docker ps -a | grep $1-storage | wc -l)

if [ "$CHECK" -ne 1 ]; then
	echo "No storage container found. Creating..."
	docker run -v /var/lib/mysql -v /usr/share/nginx/www --name $1-storage busybox true
fi;

APP_CHECK=$(docker ps | grep $1-application | cut -c -12)

if [ "$APP_CHECK" != "" ]; then
	echo "Existing app container found, restarting."
	ID=$(docker stop $APP_CHECK)
	ID=$(docker rm $APP_CHECK)
fi;

CID=$(docker run -dt --volumes-from $1-storage -p 8888:8888 --name $1-application -h $1 -e VIRTUAL_HOST=$1 bradleyboy/docker-koken-nginx)
echo "Koken container started: $CID"
