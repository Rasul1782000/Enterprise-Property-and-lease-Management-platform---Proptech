@echo off
REM ============================================================
REM  One-shot setup + production build for the Angular frontend.
REM  Install deps (if needed) and build in a single run, so
REM  there is no need to run `npm install` and `npm run build`
REM  separately every time.
REM
REM  Usage:  build.bat            (production build)
REM          build.bat dev        (development build)
REM          build.bat serve      (install only, then dev server)
REM ============================================================
setlocal enabledelayedexpansion

set "MODE=%~1"
if "%MODE%"=="" set "MODE=prod"

echo.
echo === Angular Frontend Build ===
echo Mode: %MODE%
echo.

if not exist package.json (
    echo [ERROR] package.json not found - run this from the frontend directory.
    exit /b 1
)

REM --- Dependencies -------------------------------------------------
REM `npm ci` is only valid with a lockfile in sync; fall back to
REM `npm install` so a freshly cloned or edited lockfile still works.
if not exist node_modules (
    echo [1/2] Installing dependencies ^(first run^)...
    call npm ci --no-audit --fund=false
    if errorlevel 1 (
        echo [WARN] npm ci failed, retrying with npm install...
        call npm install --no-audit --fund=false
        if errorlevel 1 (
            echo [ERROR] Dependency installation failed.
            exit /b 1
        )
    )
) else (
    echo [1/2] node_modules present - syncing dependencies...
    call npm install --no-audit --fund=false
    if errorlevel 1 (
        echo [ERROR] Dependency installation failed.
        exit /b 1
    )
)

if "%MODE%"=="serve" goto :serve

REM --- Build ---------------------------------------------------------
echo [2/2] Building...
if "%MODE%"=="dev" (
    call npm run build
) else (
    call npm run build:prod
)

if errorlevel 1 (
    echo.
    echo [ERROR] Build failed.
    exit /b 1
)

echo.
echo [OK] Build complete. Output: dist\frontend
goto :done

:serve
echo [2/2] Starting dev server ^(watch mode - rebuilds on save^)...
echo      Press Ctrl+C to stop.
call npm start
goto :done

:done
endlocal
exit /b 0