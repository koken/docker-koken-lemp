# Docker + Koken + nginx = ♥

This a forked of the [offical Koken Docker image](https://github.com/koken/docker-koken-lemp) for ARM witch based on Raspbian and installs the latest version of [Koken](http://koken.me) and all necessary system requirements.

## Features

* Automatically sets up and configures the database for Koken and skips that step in the installation process.
* Adds a cron job to do periodic cleanup of the image cache.
* nginx/PHP configured for best Koken performance.
* Can be used on any machine with Docker installed.

## General usage

1. Build image by using the build.sh script.
2. Run the following command to start Koken.
~~~bash
 docker run --restart=always -p 80:8080 --name koken -v /data/koken/www:/usr/share/nginx/www -v /data/koken/www:/var/lib/mysql -d r0b2g1t/koken:latest
~~~

This forwards port 80 on your host machine to the instance of Koken running on port 8080 inside the container. You can now access your new Koken install by loading the IP address or domain name for your host in a browser. Your files reside in `/data/koken/www` on the host machine, while the MySQL data lives in `/data/koken/www`.
