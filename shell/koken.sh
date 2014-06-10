#!/bin/bash
find /usr/share/nginx/www/storage/cache/images/* -atime +10 -exec rm {} \;
