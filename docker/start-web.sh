#!/bin/sh
set -e

PORT="${PORT:-8080}"
sed "s/__PORT__/${PORT}/" /etc/nginx/templates/web.conf.template > /etc/nginx/conf.d/default.conf

php-fpm -D
exec nginx -g 'daemon off;'
