#!/bin/sh
set -e

# Render injects the port to bind at runtime (default: 10000).
# Only ${PORT} is substituted; nginx variables such as $uri stay untouched.
: "${PORT:=10000}"
export PORT

envsubst '${PORT}' < /etc/nginx/templates/nginx.conf.template > /etc/nginx/nginx.conf

exec "$@"
