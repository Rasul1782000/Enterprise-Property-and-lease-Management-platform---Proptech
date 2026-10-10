<# 
.SYNOPSIS
    Production Deployment Script for PropertyLease Portal (PowerShell)

.DESCRIPTION
    Deploys the PropertyLease Portal using Docker Compose with Traefik for HTTPS.

.PARAMETER Environment
    Deployment environment (default: production)

.EXAMPLE
    .\deploy.ps1
    .\deploy.ps1 -Environment production
#>

param(
    [string]$Environment = "production"
)

$ErrorActionPreference = "Stop"

$composeFile = "docker-compose.prod.yml"
$envFile = ".env.$Environment"

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "Deploying PropertyLease Portal - $Environment" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan

# Check if .env file exists
if (-not (Test-Path $envFile)) {
    Write-Error "ERROR: $envFile not found!"
    Write-Host "Copy .env.production to $envFile and fill in your values." -ForegroundColor Yellow
    exit 1
}

# Load environment variables
$envVars = Get-Content $envFile | Where-Object { $_ -notmatch '^#' -and $_ -match '=' } | ForEach-Object {
    $parts = $_.Split('=', 2)
    @{ $parts[0] = $parts[1] }
}

foreach ($var in $envVars) {
    $name = $var.Keys[0]
    $value = $var.Values[0]
    [Environment]::SetEnvironmentVariable($name, $value, "Process")
}

# Validate required variables
$requiredVars = @(
    "APP_KEY",
    "DB_PASSWORD",
    "DB_ROOT_PASSWORD",
    "MAIL_HOST",
    "MAIL_PORT",
    "MAIL_USERNAME",
    "MAIL_PASSWORD",
    "MAIL_ENCRYPTION"
)

$hasErrors = $false
foreach ($var in $requiredVars) {
    $value = [Environment]::GetEnvironmentVariable($var, "Process")
    if (-not $value -or $value -eq "CHANGE_ME_SECURE_PASSWORD" -or $value -eq "CHANGE_ME_ROOT_PASSWORD") {
        Write-Error "ERROR: $var is not set or uses default value in $envFile"
        $hasErrors = $true
    }
}

if ($hasErrors) {
    exit 1
}

Write-Host "Environment variables validated." -ForegroundColor Green

# Pull latest images
Write-Host "Pulling latest images..." -ForegroundColor Yellow
docker compose -f $composeFile pull

# Build images
Write-Host "Building images..." -ForegroundColor Yellow
docker compose -f $composeFile build --no-cache

# Run database migrations
Write-Host "Running database migrations..." -ForegroundColor Yellow
docker compose -f $composeFile run --rm backend php artisan migrate --force

# Create storage link
Write-Host "Creating storage link..." -ForegroundColor Yellow
docker compose -f $composeFile run --rm backend php artisan storage:link

# Clear and cache config
Write-Host "Optimizing application..." -ForegroundColor Yellow
docker compose -f $composeFile run --rm backend php artisan config:cache
docker compose -f $composeFile run --rm backend php artisan route:cache
docker compose -f $composeFile run --rm backend php artisan view:cache

# Start services
Write-Host "Starting services..." -ForegroundColor Yellow
docker compose -f $composeFile up -d

# Wait for services to be healthy
Write-Host "Waiting for services to be healthy..." -ForegroundColor Yellow
Start-Sleep -Seconds 15

# Check service health
docker compose -f $composeFile ps

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "Deployment complete!" -ForegroundColor Green
Write-Host "Frontend: https://rasul17.indevs.in" -ForegroundColor Cyan
Write-Host "Backend API: https://rasul17.indevs.in/api" -ForegroundColor Cyan
Write-Host "Traefik Dashboard: http://rasul17.indevs.in:8080" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan