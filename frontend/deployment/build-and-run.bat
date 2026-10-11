@echo off
setlocal EnableExtensions

for %%I in ("%~dp0..\..") do set "PROJECT_ROOT=%%~fI"
set "DEPLOY_DIR=%PROJECT_ROOT%\frontend\deployment"
set "LOCAL_COMPOSE=%DEPLOY_DIR%\docker-compose.local.yml"
set "JENKINS_COMPOSE=%DEPLOY_DIR%\docker-compose.jenkins.yml"
set "ENV_FILE=%DEPLOY_DIR%\.env.local"
set "ACTION=%~1"

if not defined ACTION set "ACTION=help"

where docker >nul 2>&1
if errorlevel 1 (
    echo Docker is required but was not found on PATH.
    exit /b 1
)

docker compose version >nul 2>&1
if errorlevel 1 (
    echo Docker Compose v2 is required.
    exit /b 1
)

if /i "%ACTION%"=="build" goto build
if /i "%ACTION%"=="local" goto local
if /i "%ACTION%"=="demo" goto demo
if /i "%ACTION%"=="ci" goto ci
if /i "%ACTION%"=="stop-local" goto stoplocal
if /i "%ACTION%"=="stop-ci" goto stopci
goto help

:build
call :buildapi
if errorlevel 1 exit /b 1
docker build -f "%DEPLOY_DIR%\Dockerfile.jenkins" -t property-lease-jenkins:local "%PROJECT_ROOT%"
exit /b %errorlevel%

:local
call :checkenv
if errorlevel 1 exit /b 1
call :buildapi
if errorlevel 1 exit /b 1
docker compose --env-file "%ENV_FILE%" -f "%LOCAL_COMPOSE%" up -d db redis floci
if errorlevel 1 exit /b 1
set "DB_READY="
for /L %%I in (1,1,60) do (
    docker compose --env-file "%ENV_FILE%" -f "%LOCAL_COMPOSE%" exec -T db mysqladmin ping -h localhost --silent >nul 2>&1
    if not errorlevel 1 set "DB_READY=yes"
    if defined DB_READY goto migrate
    timeout /t 2 /nobreak >nul
)
echo MySQL did not become ready. Check its container logs.
docker compose --env-file "%ENV_FILE%" -f "%LOCAL_COMPOSE%" logs db
exit /b 1

:migrate
docker compose --env-file "%ENV_FILE%" -f "%LOCAL_COMPOSE%" run --rm app php artisan migrate --force
if errorlevel 1 exit /b 1
docker compose --env-file "%ENV_FILE%" -f "%LOCAL_COMPOSE%" run --rm app php artisan storage:ensure-bucket
if errorlevel 1 exit /b 1
docker compose --env-file "%ENV_FILE%" -f "%LOCAL_COMPOSE%" up -d app
if errorlevel 1 exit /b 1
echo Local API: http://localhost:8000
echo Run the Angular app from frontend with npm start, or deploy it through Vercel.
exit /b 0

:ci
if not defined JENKINS_ADMIN_PASSWORD (
    echo Set JENKINS_ADMIN_PASSWORD in the current shell before starting Jenkins.
    exit /b 1
)
docker build -f "%DEPLOY_DIR%\Dockerfile.jenkins" -t property-lease-jenkins:local "%PROJECT_ROOT%"
if errorlevel 1 exit /b 1
docker compose -f "%JENKINS_COMPOSE%" up -d jenkins floci
if errorlevel 1 exit /b 1
echo Jenkins is bound to http://localhost:8888. Keep this local-only CI stack off public hosts.
exit /b 0

:demo
call :checkenv
if errorlevel 1 exit /b 1
findstr /B /C:"DEMO_SEED_ENABLED=true" "%ENV_FILE%" >nul
if errorlevel 1 (
    echo Set DEMO_SEED_ENABLED=true in "%ENV_FILE%" to explicitly enable local demo data.
    exit /b 1
)
findstr /B /C:"DEMO_PASSWORD=CHANGE_ME" "%ENV_FILE%" >nul
if not errorlevel 1 (
    echo Set a unique DEMO_PASSWORD of at least 12 characters in "%ENV_FILE%".
    exit /b 1
)
docker compose --env-file "%ENV_FILE%" -f "%LOCAL_COMPOSE%" run --rm app php artisan db:seed --force
if errorlevel 1 exit /b 1
echo Demo admin account: admin@propertylease.test
echo Its password is the DEMO_PASSWORD value in "%ENV_FILE%".
exit /b 0

:stoplocal
if exist "%ENV_FILE%" docker compose --env-file "%ENV_FILE%" -f "%LOCAL_COMPOSE%" down
exit /b %errorlevel%

:stopci
docker compose -f "%JENKINS_COMPOSE%" down
exit /b %errorlevel%

:buildapi
docker build -f "%DEPLOY_DIR%\Dockerfile.backend" -t property-lease-api:local "%PROJECT_ROOT%"
exit /b %errorlevel%

:checkenv
if not exist "%ENV_FILE%" (
    echo Copy "%DEPLOY_DIR%\.env.local.example" to "%ENV_FILE%" and set unique local values.
    exit /b 1
)
findstr /B /C:"APP_KEY=CHANGE_ME" /C:"DB_PASSWORD=CHANGE_ME" /C:"DB_ROOT_PASSWORD=CHANGE_ME" "%ENV_FILE%" >nul
if not errorlevel 1 (
    echo Replace all CHANGE_ME values in "%ENV_FILE%" before starting the stack.
    exit /b 1
)
exit /b 0

:help
echo Usage: %~nx0 ^<build^|local^|demo^|ci^|stop-local^|stop-ci^>
echo.
echo   build      Build the single Laravel API and Jenkins images.
echo   local      Start MySQL, Redis and Floci, migrate, ensure the S3 bucket, and run the API.
echo   demo       Seed local demo users/data with the password in .env.local.
echo   ci         Start the localhost-only Jenkins and Floci CI stack.
echo   stop-local Stop local services without deleting persistent data.
echo   stop-ci    Stop Jenkins/Floci without deleting persistent data.
exit /b 0
