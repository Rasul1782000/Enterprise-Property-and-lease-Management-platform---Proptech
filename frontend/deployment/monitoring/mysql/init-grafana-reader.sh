#!/bin/sh
set -eu

case "${GRAFANA_MYSQL_PASSWORD:-}" in
    ''|*[!A-Za-z0-9]*)
        echo "GRAFANA_MYSQL_PASSWORD must contain only letters and numbers." >&2
        exit 1
        ;;
esac

MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket --user=root <<SQL
CREATE USER IF NOT EXISTS 'grafana_reader'@'%' IDENTIFIED BY '${GRAFANA_MYSQL_PASSWORD}';
ALTER USER 'grafana_reader'@'%' IDENTIFIED BY '${GRAFANA_MYSQL_PASSWORD}';
GRANT SELECT, SHOW VIEW ON property_lease.* TO 'grafana_reader'@'%';
SQL
