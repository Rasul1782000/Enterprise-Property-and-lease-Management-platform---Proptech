#!/bin/bash
# Production Deployment Script for PropertyLease Portal
# Usage: ./deploy.sh [environment] - default: production

set -e

ENVIRONMENT=${1:-production}
COMPOSE_FILE="docker-compose.prod.yml"
ENV_FILE=".env.${ENVIRONMENT}"

echo "========================================="
echo "Deploying PropertyLease Portal - ${ENVIRONMENT}"
echo "========================================="

# Check if .env file exists
if [ ! -f "${ENV_FILE}" ]; then
    echo "ERROR: ${ENV_FILE} not found!"
    echo "Copy .env.production to ${ENV_FILE} and fill in your values."
    exit 1
fi

# Load environment variables
export $(grep -v '^#' ${ENV_FILE} | xargs)

# Validate required variables
REQUIRED_VARS=(
    "APP_KEY"
    "DB_PASSWORD"
    "DB_ROOT_PASSWORD"
    "MAIL_HOST"
    "MAIL_PORT"
    "MAIL_USERNAME"
    "MAIL_PASSWORD"
    "MAIL_ENCRYPTION"
)

for var in "${REQUIRED_VARS[@]}"; do
    if [ -z "${!var}" ] || [ "${!var}" = "CHANGE_ME_SECURE_PASSWORD" ] || [ "${!var}" = "CHANGE_ME_ROOT_PASSWORD" ]; then
        echo "ERROR: ${var} is not set or uses default value in ${ENV_FILE}"
        exit 1
    fi
done

echo "Environment variables validated."

# Pull latest images
echo "Pulling latest images..."
docker compose -f ${COMPOSE_FILE} pull

# Build images
echo "Building images..."
docker compose -f ${COMPOSE_FILE} build --no-cache

# Run database migrations
echo "Running database migrations..."
docker compose -f ${COMPOSE_FILE} run --rm backend php artisan migrate --force

# Seed database (optional - remove in production if not needed)
# docker compose -f ${COMPOSE_FILE} run --rm backend php artisan db:seed --force

# Create storage link
echo "Creating storage link..."
docker compose -f ${COMPOSE_FILE} run --rm backend php artisan storage:link

# Clear and cache config
echo "Optimizing application..."
docker compose -f ${COMPOSE_FILE} run --rm backend php artisan config:cache
docker compose -f ${COMPOSE_FILE} run --rm backend php artisan route:cache
docker compose -f ${COMPOSE_FILE} run --rm backend php artisan view:cache

# Start services
echo "Starting services..."
docker compose -f ${COMPOSE_FILE} up -d

# Wait for services to be healthy
echo "Waiting for services to be healthy..."
sleep 10

# Check service health
docker compose -f ${COMPOSE_FILE} ps

echo "========================================="
echo "Deployment complete!"
echo "Frontend: https://rasul17.indevs.in"
echo "Backend API: https://rasul17.indevs.in/api"
echo "Traefik Dashboard: http://rasul17.indevs.in:8080"
echo "========================================="