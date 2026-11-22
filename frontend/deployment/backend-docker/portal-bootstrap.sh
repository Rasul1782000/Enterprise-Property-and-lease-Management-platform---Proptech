#!/bin/sh
set -e

# Wait for MySQL, apply migrations/seed, then release nginx and PHP-FPM so the
# health check only succeeds against a ready application.
cd /var/www/html

SUPERVISOR_CONFIG=/etc/supervisor/conf.d/supervisord.conf
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USERNAME:-property_lease}"
DB_PASS="${DB_PASSWORD:-}"

ready=0
for i in $(seq 1 180); do
    if MYSQL_PWD="$DB_PASS" mysqladmin --protocol=tcp \
        -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" \
        --connect-timeout=2 ping --silent >/dev/null 2>&1; then
        ready=1
        break
    fi
    sleep 1
done

if [ "$ready" -ne 1 ]; then
    echo "MySQL did not become ready for portal bootstrap at ${DB_HOST}:${DB_PORT}." >&2
    echo '--- mysqld log tail ---' >&2
    tail -n 80 /var/log/mysqld.log /var/log/mysqld-error.log 2>/dev/null >&2 || true
    exit 1
fi

if [ "${PORTAL_BOOTSTRAP:-true}" = "true" ]; then
    php artisan portal:bootstrap --no-interaction
else
    echo 'PORTAL_BOOTSTRAP is not true; skipping portal bootstrap.'
fi

for i in $(seq 1 30); do
    if supervisorctl -c "$SUPERVISOR_CONFIG" start php-fpm nginx; then
        exit 0
    fi
    sleep 1
done

echo 'Could not start php-fpm and nginx via supervisorctl.' >&2
exit 1
