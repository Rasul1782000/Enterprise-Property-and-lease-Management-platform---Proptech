#!/bin/sh
set -e

# Long-running MariaDB process managed by supervisor.
DATA_DIR="${DB_DATA_DIR:-/var/lib/mysql}"
SOCKET_DIR=/run/mysqld

mkdir -p "$SOCKET_DIR" "$DATA_DIR"
chown mysql:mysql "$SOCKET_DIR" "$DATA_DIR"

exec mariadbd --user=mysql --datadir="$DATA_DIR" \
    --bind-address=127.0.0.1 --port=3306 \
    --socket="$SOCKET_DIR/mysqld.sock" --pid-file="$SOCKET_DIR/mysqld.pid" \
    --skip-name-resolve --log-error=/dev/stderr
