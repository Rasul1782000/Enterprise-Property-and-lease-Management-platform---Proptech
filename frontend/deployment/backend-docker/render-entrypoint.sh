#!/bin/sh
set -e

# Render injects the port to bind at runtime (default: 10000).
# Only ${PORT} is substituted; nginx variables such as $uri stay untouched.
: "${PORT:=10000}"
export PORT

envsubst '${PORT}' < /etc/nginx/templates/nginx.conf.template > /etc/nginx/nginx.conf

# Apply migrations, and seed a small dataset only when the portal is empty.
# The command is idempotent and safe to run on every container start.
if [ "${PORTAL_BOOTSTRAP:-true}" = "true" ]; then
    php artisan portal:bootstrap --no-interaction
fi

exec "$@"
