#!/bin/sh
set -e

# Initialize and run the MySQL 8 server installed from Oracle's APT repo.
# The image's data directory lives on the persistent disk mounted at
# /var/lib/mysql by Render.

DATA_DIR="${DB_DATA_DIR:-/var/lib/mysql}"
SOCKET_DIR=/var/run/mysqld
SOCKET="$SOCKET_DIR/mysqld.sock"
PID="$SOCKET_DIR/mysqld.pid"
INIT_SOCKET="$SOCKET_DIR/mysql-init.sock"
INIT_PID="$SOCKET_DIR/mysql-init.pid"
MARKER="$DATA_DIR/.portal-initialized"

mkdir -p "$SOCKET_DIR" "$DATA_DIR"
chown -R mysql:mysql "$SOCKET_DIR" "$DATA_DIR"

# First boot: create an empty data directory with a passwordless root account.
if [ ! -d "$DATA_DIR/mysql" ]; then
    echo "Initializing MySQL data directory at $DATA_DIR"
    mysqld --initialize-insecure --user=mysql --datadir="$DATA_DIR" \
        --log-error="$DATA_DIR/mysql-error.log"
fi

if [ ! -f "$MARKER" ]; then
    DB_NAME=$(printf '%s' "${MYSQL_DATABASE:-Gaarbejana}" | sed 's/`/``/g')
    DB_USER=$(printf '%s' "${MYSQL_USER:-property_lease}" | sed -e 's/\\/\\\\/g' -e "s/'/''/g")
    DB_PASS=$(printf '%s' "${MYSQL_PASSWORD:-${DB_PASSWORD:-GaarbejanaLocal123!}}" | sed -e 's/\\/\\\\/g' -e "s/'/''/g")
    ROOT_PASS=$(printf '%s' "${MYSQL_ROOT_PASSWORD:-GaarbejanaRoot123!}" | sed -e 's/\\/\\\\/g' -e "s/'/''/g")

    cleanup() {
        if [ -n "${init_pid:-}" ] && kill -0 "$init_pid" 2>/dev/null; then
            kill "$init_pid" 2>/dev/null || true
            wait "$init_pid" 2>/dev/null || true
        fi
    }
    trap cleanup EXIT

    rm -f "$INIT_SOCKET" "$INIT_PID"
    mysqld --user=mysql --datadir="$DATA_DIR" --skip-networking \
        --socket="$INIT_SOCKET" --pid-file="$INIT_PID" \
        --log-error="$DATA_DIR/mysql-error.log" &
    init_pid=$!

    ready=0
    for i in $(seq 1 90); do
        if mysqladmin --socket="$INIT_SOCKET" ping >/dev/null 2>&1; then
            ready=1
            break
        fi
        if ! kill -0 "$init_pid" 2>/dev/null; then
            break
        fi
        sleep 1
    done

    if [ "$ready" -ne 1 ]; then
        echo 'MySQL failed to initialize.' >&2
        exit 1
    fi

    mysql --socket="$INIT_SOCKET" -uroot <<SQL
ALTER USER 'root'@'localhost' IDENTIFIED BY '$ROOT_PASS';
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'%';
FLUSH PRIVILEGES;
SQL

    mysqladmin --socket="$INIT_SOCKET" -uroot "-p$ROOT_PASS" shutdown
    wait "$init_pid" || true
    init_pid=
    trap - EXIT
    touch "$MARKER"
    chown mysql:mysql "$MARKER"
fi

# Long-running server managed by supervisor.
exec mysqld --user=mysql --datadir="$DATA_DIR" \
    --socket="$SOCKET" --pid-file="$PID" \
    --bind-address=0.0.0.0 --port=3306 --skip-name-resolve \
    --log-error="$DATA_DIR/mysql-error.log"
