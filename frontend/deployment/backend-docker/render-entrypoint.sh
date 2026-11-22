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

# Fail loudly instead of letting Laravel fall back to config/database.php's
# "forge" defaults (the cause of SQLSTATE[HY000] [2002] Connection refused).
: "${DB_HOST:?DB_HOST must be set (see render.yaml)}"
: "${DB_PORT:?DB_PORT must be set (see render.yaml)}"
: "${DB_DATABASE:?DB_DATABASE must be set (see render.yaml)}"
: "${DB_USERNAME:?DB_USERNAME must be set (see render.yaml)}"
: "${DB_PASSWORD:?DB_PASSWORD must be set (see render.yaml)}"

# Apply migrations and seed a small dataset only when the portal is empty.
# Wait for the MySQL private service first so a slow DB boot cannot fail the
# deploy. The command itself is idempotent and safe on every container start.
if [ "${PORTAL_BOOTSTRAP:-true}" = "true" ]; then
    attempts=0
    while [ "$attempts" -lt 90 ]; do
        if php -r '
            $dsn = "mysql:host=" . getenv("DB_HOST") . ";port=" . getenv("DB_PORT") . ";dbname=" . getenv("DB_DATABASE");
            try {
                new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [PDO::ATTR_TIMEOUT => 2]);
                exit(0);
            } catch (Throwable $e) {
                exit(1);
            }
        ' 2>/dev/null; then
            break
        fi
        attempts=$((attempts + 1))
        sleep 2
    done
    php artisan portal:bootstrap --no-interaction
else
    echo 'PORTAL_BOOTSTRAP is not true; skipping portal bootstrap.'
fi

exec "$@"
