# syntax=docker/dockerfile:1
# One Dockerfile for every backend target. Build with --target:
#   backend - local stack / CI: Laravel API (nginx + php-fpm) on Alpine
#   render  - Laravel API only (PHP/nginx); connects to the external MySQL service
#   jenkins - Jenkins controller
#   mysql   - standalone MySQL 8 with the portal schema bootstrap
# All runtime config and SQL live here; no extra config files are required.

FROM composer:2.7 AS build

WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY backend/ ./
RUN composer dump-autoload --optimize --classmap-authoritative \
    && rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
             bootstrap/cache/config.php bootstrap/cache/routes-v7.php


# ---------------------------------------------------------------------------
# Shared inline configuration
# ---------------------------------------------------------------------------
FROM alpine:3.20 AS config

RUN mkdir -p /config

RUN cat > /config/nginx.local.conf <<'NGINX'
user www-data;
worker_processes auto;
pid /run/nginx.pid;
include /etc/nginx/modules-enabled/*.conf;
events { worker_connections 1024; }
http {
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    client_max_body_size 20M;
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;
    server {
        listen 80;
        server_name _;
        root /var/www/html/public/frontend;
        index index.html;
        add_header X-Frame-Options "SAMEORIGIN" always;
        add_header X-Content-Type-Options "nosniff" always;
        add_header X-XSS-Protection "1; mode=block" always;
        add_header Referrer-Policy "strict-origin-when-cross-origin" always;
        location / {
            try_files $uri $uri/ /index.html;
            location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
                expires 1y;
                add_header Cache-Control "public, immutable";
            }
        }
        location /api/ {
            proxy_pass http://127.0.0.1:8000;
            proxy_http_version 1.1;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
            proxy_read_timeout 90s;
            proxy_send_timeout 90s;
        }
        location /up {
            proxy_pass http://127.0.0.1:8000;
            proxy_set_header Host $host;
            access_log off;
        }
    }
    server {
        listen 8000;
        server_name _;
        root /var/www/html/public;
        index index.php;
        add_header X-Frame-Options "SAMEORIGIN" always;
        add_header X-Content-Type-Options "nosniff" always;
        location / { try_files $uri $uri/ /index.php?$query_string; }
        location ~ \.php$ {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_read_timeout 90s;
        }
        location ~ /\.(env|git|htaccess) { deny all; }
    }
}
NGINX

RUN cat > /config/nginx.render.conf.template <<'NGINX'
user www-data;
worker_processes auto;
pid /run/nginx.pid;
events { worker_connections 1024; }
http {
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    client_max_body_size 20M;
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;
    server {
        listen ${PORT};
        server_name _;
        root /var/www/html/public;
        index index.php;
        add_header X-Frame-Options "SAMEORIGIN" always;
        add_header X-Content-Type-Options "nosniff" always;
        add_header X-XSS-Protection "1; mode=block" always;
        add_header Referrer-Policy "strict-origin-when-cross-origin" always;
        location / { try_files $uri $uri/ /index.php?$query_string; }
        location ~ \.php$ {
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_read_timeout 90s;
        }
        location ~ /\.(env|git|htaccess) { deny all; }
    }
}
NGINX

RUN cat > /config/php.ini <<'INI'
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
post_max_size = 20M
upload_max_filesize = 20M
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 0
opcache.validate_timestamps = 0
opcache.save_comments = 1
date.timezone = UTC
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/www/html/storage/logs/php-errors.log
session.gc_maxlifetime = 7200
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = "Lax"
INI

RUN cat > /config/www.conf <<'INI'
[www]
user = www-data
group = www-data
listen = 127.0.0.1:9000
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500
clear_env = no
catch_workers_output = yes
INI

RUN cat > /config/supervisord.local.conf <<'SUP'
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisord.log
logfile_maxbytes=50MB
logfile_backups=10
loglevel=info
pidfile=/var/run/supervisord.pid

[program:php-fpm]
command=php-fpm -F
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stdout_logfile=/var/log/php-fpm.log
stderr_logfile=/var/log/php-fpm-error.log

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stdout_logfile=/var/log/nginx.log
stderr_logfile=/var/log/nginx-error.log
SUP

RUN cat > /config/supervisord.render.conf <<'SUP'
[unix_http_server]
file=/run/supervisor.sock
chmod=0700

[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisord.log
logfile_maxbytes=50MB
logfile_backups=10
loglevel=info
pidfile=/run/supervisord.pid
childlogdir=/var/log/supervisor

[rpcinterface:supervisor]
supervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface

[supervisorctl]
serverurl=unix:///run/supervisor.sock

[program:portal-bootstrap]
command=/usr/local/bin/portal-bootstrap.sh
priority=10
autostart=true
autorestart=false
startsecs=0
stdout_logfile=/var/log/portal-bootstrap.log
stderr_logfile=/var/log/portal-bootstrap-error.log

[program:php-fpm]
command=/usr/sbin/php-fpm8.2 -F
priority=20
autostart=false
autorestart=true
stopasgroup=true
killasgroup=true
stdout_logfile=/var/log/php-fpm.log
stderr_logfile=/var/log/php-fpm-error.log

[program:nginx]
command=nginx -g "daemon off;"
priority=30
autostart=false
autorestart=true
stopasgroup=true
killasgroup=true
stdout_logfile=/var/log/nginx.log
stderr_logfile=/var/log/nginx-error.log
SUP

RUN cat > /config/render-entrypoint.sh <<'SH'
#!/bin/sh
set -e
: "${PORT:=10000}"
export PORT
case "${APP_KEY:-}" in
    ""|base64:*) ;;
    *) APP_KEY="base64:$APP_KEY"; export APP_KEY ;;
esac
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php bootstrap/cache/routes-v7.php
envsubst '${PORT}' < /etc/nginx/templates/nginx.conf.template > /etc/nginx/nginx.conf
: "${DB_CONNECTION:=mysql}"
: "${DB_HOST:=db}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:=Gaarbejana}"
: "${DB_USERNAME:=property_lease}"
: "${DB_PASSWORD:=GaarbejanaLocal123!}"
export DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
exec "$@"
SH

RUN cat > /config/portal-bootstrap.sh <<'SH'
#!/bin/sh
set -e
cd /var/www/html
SUPERVISOR_CONFIG=/etc/supervisor/conf.d/supervisord.conf
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USERNAME:-property_lease}"
DB_PASS="${DB_PASSWORD:-}"
ready=0
for i in $(seq 1 180); do
    if DB_USER="$DB_USER" DB_PASS="$DB_PASS" DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" \
       DB_NAME="${DB_DATABASE:-Gaarbejana}" php -r '
        $dsn = "mysql:host=" . getenv("DB_HOST") . ";port=" . getenv("DB_PORT") . ";dbname=" . getenv("DB_NAME");
        try {
            new PDO($dsn, getenv("DB_USER"), getenv("DB_PASS"), [PDO::ATTR_TIMEOUT => 2]);
            exit(0);
        } catch (Throwable $e) {
            exit(1);
        }
    ' 2>/dev/null; then
        ready=1
        break
    fi
    sleep 1
done
if [ "$ready" -ne 1 ]; then
    echo "MySQL did not become ready at ${DB_HOST}:${DB_PORT}." >&2
    exit 1
fi
if [ "${PORTAL_BOOTSTRAP:-true}" = "true" ]; then
    php artisan portal:bootstrap --no-interaction
else
    echo 'PORTAL_BOOTSTRAP is not true; skipping portal bootstrap.'
fi
for i in $(seq 1 30); do
    if supervisorctl -c "$SUPERVISOR_CONFIG" start php-fpm nginx; then exit 0; fi
    sleep 1
done
echo 'Could not start php-fpm and nginx via supervisorctl.' >&2
exit 1
SH



# ---------------------------------------------------------------------------
# backend: Laravel API for the local stack and CI
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS backend

RUN apk add --no-cache \
        nginx supervisor curl libpng libjpeg-turbo freetype libzip oniguruma icu-libs \
    && apk add --no-cache --virtual .build-deps \
        libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev oniguruma-dev icu-dev \
        linux-headers $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip intl opcache bcmath pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

COPY --from=build /app /var/www/html
COPY --from=config /config/nginx.local.conf /etc/nginx/nginx.conf
COPY --from=config /config/supervisord.local.conf /etc/supervisor/conf.d/supervisord.conf
COPY --from=config /config/php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html
RUN mkdir -p storage/framework/sessions storage/framework/views \
             storage/framework/cache/data storage/logs \
    && touch storage/logs/laravel.log \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache \
    && nginx -t

EXPOSE 80 8000
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://localhost:8000/up || exit 1
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]


# ---------------------------------------------------------------------------
# render: Laravel API only; MySQL is provided by the mysql target / external host
# ---------------------------------------------------------------------------
FROM debian:bookworm-slim AS render

RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        curl ca-certificates gettext-base \
        nginx supervisor \
        php8.2-fpm php8.2-cli php8.2-mysql php8.2-gd php8.2-zip php8.2-intl \
        php8.2-bcmath php8.2-opcache php8.2-curl php8.2-sqlite3 php8.2-xml \
        php8.2-mbstring php8.2-redis \
 && rm -rf /var/lib/apt/lists/*

COPY --from=build /app /var/www/html
COPY --from=config /config/nginx.render.conf.template /etc/nginx/templates/nginx.conf.template
COPY --from=config /config/supervisord.render.conf /etc/supervisor/conf.d/supervisord.conf
COPY --from=config /config/php.ini /etc/php/8.2/fpm/conf.d/99-custom.ini
COPY --from=config /config/php.ini /etc/php/8.2/cli/conf.d/99-custom.ini
COPY --from=config /config/www.conf /etc/php/8.2/fpm/pool.d/www.conf
COPY --from=config /config/render-entrypoint.sh /usr/local/bin/render-entrypoint.sh
COPY --from=config /config/portal-bootstrap.sh /usr/local/bin/portal-bootstrap.sh

WORKDIR /var/www/html
RUN mkdir -p storage/framework/sessions storage/framework/views \
             storage/framework/cache/data storage/logs \
             /run/supervisor /var/log/supervisor /var/log/nginx \
    && touch storage/logs/laravel.log \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache \
    && chmod +x /usr/local/bin/render-entrypoint.sh \
               /usr/local/bin/portal-bootstrap.sh

ENV PORT=10000
EXPOSE 10000
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -fsS "http://127.0.0.1:${PORT}/up" || exit 1
ENTRYPOINT ["/usr/local/bin/render-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]


# ---------------------------------------------------------------------------
# jenkins: Jenkins controller
# ---------------------------------------------------------------------------
FROM jenkins/jenkins:lts AS jenkins

USER root
USER jenkins
RUN jenkins-plugin-cli --plugins \
    configuration-as-code workflow-aggregator git docker-workflow docker-plugin \
    blueocean credentials-binding ssh-agent matrix-auth role-strategy job-dsl \
    pipeline-stage-view timestamper
USER root
COPY frontend/deployment/jenkins-casc.yaml /etc/jenkins/casc.yaml
RUN chown jenkins:jenkins /etc/jenkins/casc.yaml \
 && apt-get update \
 && apt-get install -y --no-install-recommends curl ca-certificates \
 && curl -fsSL https://download.docker.com/linux/static/stable/x86_64/docker-27.5.1.tgz \
    | tar xz -C /tmp \
 && cp /tmp/docker/docker /usr/local/bin/docker \
 && rm -rf /tmp/docker /var/lib/apt/lists/* \
 && git config --system --add safe.directory '*'


# ---------------------------------------------------------------------------
# mysql: standalone MySQL 8 with the portal schema bootstrap
# ---------------------------------------------------------------------------
FROM mysql:8.0 AS mysql

RUN mkdir -p /docker-entrypoint-initdb.d \
 && cat > /docker-entrypoint-initdb.d/01-create-gaarbejana.sql <<'SQL'
CREATE DATABASE IF NOT EXISTS `Gaarbejana`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
SQL

EXPOSE 3306