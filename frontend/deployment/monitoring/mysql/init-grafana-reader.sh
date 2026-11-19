#!/bin/sh
set -eu

case "${GRAFANA_MYSQL_PASSWORD:-}" in
    ''|*[!A-Za-z0-9]*)
        echo "GRAFANA_MYSQL_PASSWORD must contain only letters and numbers." >&2
        exit 1
        ;;
esac

DB_NAME="${MYSQL_DATABASE:-Gaarbejana}"

MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket --user=root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'grafana_reader'@'%' IDENTIFIED BY '${GRAFANA_MYSQL_PASSWORD}';
ALTER USER 'grafana_reader'@'%' IDENTIFIED BY '${GRAFANA_MYSQL_PASSWORD}';
GRANT SELECT, SHOW VIEW ON \`${DB_NAME}\`.* TO 'grafana_reader'@'%';
FLUSH PRIVILEGES;
SQL
