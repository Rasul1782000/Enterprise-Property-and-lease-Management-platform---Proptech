@echo off
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