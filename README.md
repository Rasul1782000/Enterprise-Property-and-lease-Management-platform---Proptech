# Enterprise Property & Lease Management Portal

Central platform for commercial / multi-family managers to manage **Property → Building → Unit → Lease → Tenant → Invoice → Payment** with automated invoicing, PDF generation, and Angular Material / AG-Grid data management.

## Structure
```
Enterprise Property and lease Manaement Portal/
├── backend/   # Laravel 11 API (Sanctum, Scheduler, DomPDF)
├── frontend/  # Angular 18 SPA (Material, AG-Grid, Signals, Reactive Forms)
└── README.md
```

## Quick Start (You install)

### 1) Backend
```powershell
cd backend
composer install
Copy-Item .env.example .env
php artisan key:generate
# edit .env: DB_DATABASE=property_lease, DB_USERNAME/DB_PASSWORD, MAIL_*, FRONTEND_URL=http://localhost:4200
php artisan migrate --seed      # seeds admin/manager/accountant + 4 properties, 6-12 units each, 10 leases
php artisan storage:link
php artisan serve               # http://localhost:8000
# in another terminal for scheduler:
php artisan schedule:work
```

Test scheduler manually:
```powershell
php artisan invoices:generate --dry-run
php artisan invoices:generate --test-date=2026-10-01
php artisan invoices:overdue-check
php artisan leases:expiry-notify --days=30
```

PDF test: `GET http://localhost:8000/api/leases/1/pdf` (with Bearer token)

### 2) Frontend
```powershell
cd frontend
npm install
npm start    # http://localhost:4200  (proxy -> :8000)
# or: ng serve --proxy-config proxy.conf.json --open
```

Environment: `frontend/src/environments/environment.ts` -> `apiUrl: http://localhost:8000/api`

### 3) Logins (after seed)
- `admin@propertylease.test` / `password` (admin)
- `manager@propertylease.test` / `password` (manager)
- `accountant@propertylease.test` / `password` (accountant)
- any tenant email shown in Tenants list / `password`

## Backend Highlights
- **Eloquent Relationships**: `Property hasMany Building hasMany Unit hasMany Lease belongsTo Tenant`, `Lease hasMany Invoice hasMany Payment`, scopes `active`, `vacant`, `overdue`, `expiringSoon`
- **Automated Invoicing**: `invoices:generate` (monthly on 1st 02:00), `invoices:overdue-check` (daily), `leases:expiry-notify` (weekly) — all in `routes/console.php` scheduler. Queued Mail via `RentInvoiceMail` / `LeaseExpiryMail`.
- **PDF Generation**: `barryvdh/laravel-dompdf` — `resources/views/pdfs/lease-agreement.blade.php` (A4, terms, rent schedule, signatures) + `invoice-receipt.blade.php`.
- **API**: `/api/*` with Sanctum, Spatie QueryBuilder filters `?filter[status]=active&filter[name]=plaza&sort=-created_at&include=buildings.units&page=2`
- **Validation**: FormRequests with overlapping lease check (unit double-booking prevented)

## Frontend Highlights
- **Data Tables & Filtering**: `MatTable` + `AG-Grid Community` — server pagination, multi-column filter, sorting, CSV export. Handles hundreds of records (query builder + pagination).
- **Form Management**: Reactive Forms wizard 4 steps (Premises → Tenant → Terms → Review), custom validators: `end > start`, `due_day 1-28`, email, async tenant search, auto-fill rent from unit.
- **State Management**: `PropertyContextService` with Signals (`selectedPropertyId`, `selectedTenantId`, `computed hasPropertySelected`) — NgRx-ready; swap to NgRx entity if >5 stores.
- **Routing**: Lazy standalone components, `authGuard` + `roleGuard`, `MainLayoutComponent` (sidenav + toolbar + context)
- **Charts**: `ng2-charts` placeholder + stats cards (occupancy, overdue, collected This Month)

## Cron (Production)
Linux: `* * * * * cd /path/backend && php artisan schedule:run >> /dev/null 2>&1`
Windows Task Scheduler: trigger `php.exe artisan schedule:run` every minute.

## Next Steps
- Add payment gateway (Stripe) in `PaymentController@store`
- AG-Grid Enterprise for row grouping by Property/Building
- NgRx store for invoices/leaves if team prefers Redux
- Add tests: `php artisan test` (feature: lease overlap, invoice generation)

## Verification
```powershell
# backend
php artisan route:list | Select-String "api"
php artisan migrate:fresh --seed
# frontend
npm run build   # check compiles
```

---
Generated 2026-09-25 — Laravel 11 + Angular 18 + Material 18 + AG-Grid 31 + DomPDF 3.x
