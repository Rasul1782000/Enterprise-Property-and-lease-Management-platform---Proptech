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

# This image bundles MariaDB, so keep Laravel's connection settings in sync
# with it even when Render has not injected every DB_* variable. Without this,
# Laravel silently falls back to config/database.php's "forge" default and the
# migration check fails with SQLSTATE[HY000] [2002] Connection refused.
: "${DB_CONNECTION:=mysql}"
: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:=Gaarbejana}"
: "${DB_USERNAME:=property_lease}"
: "${DB_PASSWORD:=GaarbejanaLocal123!}"
export DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD

# Prepare the persistent MySQL data directory and application credentials.
# Supervisor then runs MariaDB, bootstrap, PHP-FPM, and nginx in order.
/usr/local/bin/init-mysql.sh

exec "$@"
