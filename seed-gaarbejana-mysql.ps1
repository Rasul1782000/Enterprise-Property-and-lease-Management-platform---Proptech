param(
    [string]$DbHost = "127.0.0.1",
    [int]$DbPort = 3307,
    [string]$DbName = "Gaarbejana",
    [string]$DbUser = "property_lease",
    [string]$DbPassword = "GaarbejanaLocal123!",
    [string]$DbRootPassword = "RootGaarbejana123!"
)

$ErrorActionPreference = "Stop"
$repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$composeFile = Join-Path $repoRoot "docker-compose.mysql.yml"
$backendDir = Join-Path $repoRoot "backend"

$dockerCli = (Get-Command docker -ErrorAction SilentlyContinue).Source
if (-not $dockerCli) {
    $desktopCli = Join-Path $env:LOCALAPPDATA "Programs\DockerDesktop\resources\bin\docker.exe"
    if (Test-Path $desktopCli) {
        $env:PATH = (Split-Path -Parent $desktopCli) + [IO.Path]::PathSeparator + $env:PATH
    }
}
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker is not installed or not on PATH. Install Docker Desktop/Engine, then rerun this script."
}

Write-Host "==> Building MySQL image and starting container (Gaarbejana)..."
$existing = docker ps -a --filter 'name=^/gaarbejana-mysql$' --format '{{.Names}}'
if ($existing) {
    docker rm -f gaarbejana-mysql | Out-Null
}
$env:DB_ROOT_PASSWORD = $DbRootPassword
$env:DB_USERNAME = $DbUser
$env:DB_PASSWORD = $DbPassword
$env:DB_PORT_HOST = "$DbPort"
docker compose -f $composeFile up -d --build --wait

Write-Host "==> Ensuring database '$DbName' exists..."
docker exec gaarbejana-mysql mysql -uroot "-p$DbRootPassword" -e "CREATE DATABASE IF NOT EXISTS $DbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON $DbName.* TO '$DbUser'@'%'; FLUSH PRIVILEGES;"

Write-Host "==> Preparing Laravel environment for MySQL seeding..."
Push-Location $backendDir
try {
    if (-not (Test-Path ".env")) {
        Copy-Item ".env.example" ".env"
    }

    $env:APP_ENV = "local"
    $env:DB_CONNECTION = "mysql"
    $env:DB_HOST = $DbHost
    $env:DB_PORT = "$DbPort"
    $env:DB_DATABASE = $DbName
    $env:DB_USERNAME = $DbUser
    $env:DB_PASSWORD = $DbPassword
    $env:PORTAL_SEED_USERS = "3000"

    if (-not (Select-String -Path ".env" -Pattern '^APP_KEY=.+')) {
        Write-Host "==> Generating a local application key..."
        php artisan key:generate --force
    }

    Write-Host "==> Rebuilding schema and seeding portal data into database '$DbName'..."
    php artisan migrate:fresh --seed --force

    Write-Host "==> Done. Seeded Gaarbejana MySQL database."
    Write-Host "    Expected KPIs: 15 properties, 150 buildings, 3000 units, 3000 tenant users, 3000 active leases."
} finally {
    Pop-Location
}
