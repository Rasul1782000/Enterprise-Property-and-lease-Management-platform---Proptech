@echo off
echo 🚀 Laravel Backend Setup Script
if not exist composer.json (
    echo ❌ composer.json not found - please ensure you're in the backend directory
    exit /b 1
)

if exist .env goto :env_check
echo Creating .env file...
copy .env.example .env
:env_check

php artisan key:generate
php artisan migrate --seed
php artisan optimize

echo.
echo ✅ Laravel backend setup complete!
echo 📋 Available commands:
echo   php artisan serve
@echo   php artisan migrate
@echo   php artisan db:seed
@echo   php artisan tinker
@echo   etc.
