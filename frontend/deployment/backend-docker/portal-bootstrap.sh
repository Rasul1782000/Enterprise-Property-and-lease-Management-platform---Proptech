#!/bin/sh
set -e

# Wait for MariaDB, apply migrations/seed if requested, then release nginx and
# PHP-FPM so Render's health check only succeeds against a ready application.
cd /var/www/html

# Supervisor child processes should inherit container env, but load it
# defensively so Laravel sees Render-provided DB/APP settings.
if [ -r /proc/1/environ ]; then
    RUNTIME_ENV="$(PHP_INI_SCAN_DIR= php -r '
        $raw = @file_get_contents("/proc/1/environ");
        if ($raw === false) { exit(0); }
        foreach (explode("\0", $raw) as $pair) {
            if ($pair === "") { continue; }
            $pos = strpos($pair, "=");
            if ($pos === false || $pos === 0) { continue; }
            $k = substr($pair, 0, $pos);
            $v = substr($pair, $pos + 1);
            if (!preg_match("/^[A-Za-z_][A-Za-z0-9_]*$/", $k)) { continue; }
            echo "export ".$k."=".escapeshellarg($v)."\n";
        }
    ')" || true
    if [ -n "$RUNTIME_ENV" ]; then
        eval "$RUNTIME_ENV"
    fi
fi

SUPERVISOR_CONFIG=/etc/supervisor/conf.d/supervisord.conf
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USERNAME:-property_lease}"
DB_PASS="${DB_PASSWORD:-}"

ready=0
for i in $(seq 1 120); do
    if MYSQL_PWD="$DB_PASS" mariadb-admin --protocol=tcp \
        -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" \
        --connect-timeout=2 ping --silent >/dev/null 2>&1; then
        ready=1
        break
    fi
    sleep 1
done

if [ "$ready" -ne 1 ]; then
    echo "MariaDB did not become ready for portal bootstrap at ${DB_HOST}:${DB_PORT}." >&2
    echo '--- mysqld log tail ---' >&2
    tail -n 80 /var/log/mysqld.log /var/log/mysqld-error.log /var/log/mysql-init.log 2>/dev/null >&2 || true
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
