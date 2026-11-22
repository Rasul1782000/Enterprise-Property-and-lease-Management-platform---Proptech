#!/bin/sh
set -e

# Prepare the bundled MariaDB data directory on Render's persistent disk.
# A temporary socket-only server creates the application database/user before
# supervisor starts the long-running MariaDB process.
DATA_DIR="${DB_DATA_DIR:-/var/lib/mysql}"
SOCKET_DIR=/run/mysqld
INIT_SOCKET="$SOCKET_DIR/mysql-init.sock"
INIT_PID="$SOCKET_DIR/mysql-init.pid"

mkdir -p "$SOCKET_DIR" "$DATA_DIR"
chown mysql:mysql "$SOCKET_DIR" "$DATA_DIR"

if [ ! -d "$DATA_DIR/mysql" ]; then
    mariadb-install-db --user=mysql --datadir="$DATA_DIR" --skip-test-db >/dev/null 2>&1
fi
chown -R mysql:mysql "$DATA_DIR"

cleanup() {
    if [ -n "${init_pid:-}" ] && kill -0 "$init_pid" 2>/dev/null; then
        kill "$init_pid" 2>/dev/null || true
        wait "$init_pid" 2>/dev/null || true
    fi
}
trap cleanup EXIT

rm -f "$INIT_SOCKET" "$INIT_PID"
mariadbd --user=mysql --datadir="$DATA_DIR" --skip-networking \
    --socket="$INIT_SOCKET" --pid-file="$INIT_PID" \
    --log-error=/var/log/mysql-init.log >/dev/null 2>&1 &
init_pid=$!

ready=0
for i in $(seq 1 90); do
    if mariadb-admin --socket="$INIT_SOCKET" ping >/dev/null 2>&1; then
        ready=1
        break
    fi
    if ! kill -0 "$init_pid" 2>/dev/null; then
        break
    fi
    sleep 1
done

if [ "$ready" -ne 1 ]; then
    echo 'MariaDB failed to initialize:' >&2
    cat /var/log/mysql-init.log >&2
    exit 1
fi

sql_escape() {
    printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e "s/'/''/g"
}

sql_ident() {
    printf '%s' "$1" | sed 's/`/``/g'
}

DB_NAME=$(sql_ident "${DB_DATABASE:-Gaarbejana}")
DB_USER=$(sql_escape "${DB_USERNAME:-property_lease}")
DB_PASS=$(sql_escape "${DB_PASSWORD:-GaarbejanaLocal123!}")

mariadb --socket="$INIT_SOCKET" -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

mariadb-admin --socket="$INIT_SOCKET" -uroot shutdown
wait "$init_pid" || true
init_pid=
