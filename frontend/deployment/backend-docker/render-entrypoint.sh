#!/bin/sh
set -e

# Render injects the port to bind at runtime (default: 10000).
# Only ${PORT} is substituted; nginx variables such as $uri stay untouched.
: "${PORT:=10000}"
export PORT

# Render Blueprint's generateValue is a plain base64 secret. Laravel expects
# APP_KEY to carry the "base64:" prefix, so normalize it before artisan runs.
case "${APP_KEY:-}" in
    ""|base64:*) ;;
    *) APP_KEY="base64:$APP_KEY"; export APP_KEY ;;
esac

# This image bakes package discovery for a deterministic build, but stale
# bootstrap cache copied into the image/context can hide artisan commands.
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php bootstrap/cache/routes-v7.php

envsubst '${PORT}' < /etc/nginx/templates/nginx.conf.template > /etc/nginx/nginx.conf

# Self-contained defaults: the MySQL server lives in this container, so a
# plain "push to main" deploy works even if Render has not injected any DB_*
# variables. Render-provided values always win.
: "${DB_CONNECTION:=mysql}"
: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:=Gaarbejana}"
: "${DB_USERNAME:=property_lease}"
: "${DB_PASSWORD:=GaarbejanaLocal123!}"
export DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD

# The MySQL image reads MYSQL_* on first boot to create the schema and user.
# Derive them from the app settings so the two can never drift apart.
: "${MYSQL_DATABASE:=$DB_DATABASE}"
: "${MYSQL_USER:=$DB_USERNAME}"
: "${MYSQL_PASSWORD:=$DB_PASSWORD}"
: "${MYSQL_ROOT_PASSWORD:=GaarbejanaRoot123!}"
export MYSQL_DATABASE MYSQL_USER MYSQL_PASSWORD MYSQL_ROOT_PASSWORD

chown mysql:mysql /var/lib/mysql /var/run/mysqld 2>/dev/null || true

exec "$@"
