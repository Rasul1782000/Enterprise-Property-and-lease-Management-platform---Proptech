# Backend — Gaarbejana API (Laravel 11)

## Requirements
PHP 8.2+, Composer, MySQL 8 / PostgreSQL, Node not needed

## Install
```powershell
cd backend
composer install
Copy-Item .env.example .env
php artisan key:generate
# edit .env: DB_* , MAIL_* , FRONTEND_URL
php artisan migrate
php artisan storage:link
php artisan serve  # http://localhost:8000
```

To seed the database with the portal dataset (15 properties, 3000 tenant users,
one active lease per user), run `php artisan db:seed --force`. Set
`PORTAL_SEED_USERS` in `.env` to change the number of tenant users (default
3000). Seeding truncates the data tables first and is rejected in production.

The dashboard and API do not use authentication or authorization; all API
endpoints are public. Do not use this configuration with sensitive data on an
untrusted network.

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
All routes under `/api` are public. See `routes/api.php` for the full list.

## Testing
```powershell
php artisan test
php artisan route:list
```
