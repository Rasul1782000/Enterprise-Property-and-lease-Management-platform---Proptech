# Backend — PropertyLease API (Laravel 11)

## Requirements
PHP 8.2+, Composer, MySQL 8 / PostgreSQL, Node not needed

## Install
```powershell
cd backend
composer install
Copy-Item .env.example .env
php artisan key:generate
# edit .env: DB_* , MAIL_* , FRONTEND_URL
php artisan migrate --seed
php artisan storage:link
php artisan serve  # http://localhost:8000
```

Demo logins after seed:
- admin@propertylease.test / password (admin)
- manager@propertylease.test / password (manager)
- accountant@propertylease.test / password (accountant)
- any tenant email / password (tenant)

## Scheduler (Windows)
```powershell
# run every minute in dev:
php artisan schedule:work
# or Task Scheduler -> php.exe "C:\path\backend\artisan" schedule:run
```
Commands:
- `php artisan invoices:generate --dry-run`
- `php artisan invoices:generate --test-date=2026-10-01`
- `php artisan invoices:overdue-check`
- `php artisan leases:expiry-notify`

## PDF
GET /api/leases/{id}/pdf  +  GET /api/invoices/{id}/receipt  (DomPDF, streams A4)

## API Docs
All routes under `/api` with Sanctum. See `routes/api.php` for full list.

## Testing
```powershell
php artisan test
php artisan route:list
```
