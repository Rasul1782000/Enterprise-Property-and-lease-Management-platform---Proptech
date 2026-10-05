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

## Jenkins + Floci integration

### What runs where

| Component | Where | Notes |
|---|---|---|
| Jenkins | `docker compose` in `jenkins/` | Web UI on http://localhost:8888 |
| Floci | `floci/floci:latest` | Local AWS emulator, port 4566 |
| Frontend | Angular 22 + PrimeNG | Vitest via `@angular/build:unit-test` |
| Backend | Laravel 12 + MySQL | S3 access via `aws/aws-sdk-php` + Flysystem |

```bash
docker compose -f jenkins/docker-compose.yml up -d
```

Jenkins is on http://localhost:8888 (`admin` / `admin123`).
The Floci web console is on http://localhost:4500.

### Object storage: Floci in dev/CI, real S3 in production

Lease documents, tenant documents and property photos are stored on the
`documents` filesystem disk. The same code path serves both environments; only
`AWS_ENDPOINT_URL` differs.

| | `AWS_ENDPOINT_URL` | Result |
|---|---|---|
| Host machine, dev | `http://localhost:4566` | Floci container |
| Jenkins container | `http://floci:4566` | Floci on the shared Docker network |
| Production | *unset* | Real AWS endpoint from `AWS_DEFAULT_REGION` |

**Why `http://floci:4566` and not `localhost`:** inside the Jenkins container,
`localhost` is the Jenkins container itself, not the Docker host. Floci and
Jenkins share a user-defined Docker network, so Docker's embedded DNS resolves
the container name `floci`.

**Why path-style URLs are required:** Floci only answers path-style requests
(`/{bucket}/{key}`). The SDK defaults to virtual-hosted style
(`{bucket}.{endpoint}`), which Floci does not serve, so `config/filesystems.php`
sets `use_path_style_endpoint => true`. Real AWS accepts path-style too.

**Credentials:** Floci accepts any dummy credentials; the project standardises
on `test`/`test`. No real AWS key is needed to run the suite.

```bash
# Create the bucket if it does not exist (idempotent)
cd backend && php artisan storage:ensure-bucket

# Run the backend suite; S3 tests skip automatically when no endpoint is reachable
cd backend && php artisan test
```

### Adding a service the pipeline needs

1. Add the plugin ID to `jenkins/Dockerfile` `jenkins-plugin-cli --plugins`.
2. Verify it still resolves — withdrawn plugins fail the image build.
3. Rebuild: `docker compose -f jenkins/docker-compose.yml build`.

### Jenkinsfile defects this setup avoids

Each of these was a real bug, worth remembering when editing the pipeline:

- **`params` vs `environment`.** `when { environment name: 'PUSH_IMAGE' }` never
  matches, because `PUSH_IMAGE` is a build *parameter*. Use
  `when { expression { params.PUSH_IMAGE == true } }`.
- **Hardcoded network names.** `docker network inspect jenkins_default` breaks
  when the compose directory or project name changes. The pipeline now reads the
  Jenkins container's own network via `docker inspect -f ... "$(hostname)"`.
- **Port collisions.** Publishing `-p 4566:4566` collides with a Floci already
  running on the host. The pipeline reuses an existing `floci` container and
  starts one only if none exists, with no host port mapping.
- **`|| true` on tests.** `php artisan test || true` can never fail the build, so
  a broken storage integration would go unnoticed. The backend suite now runs
  unconditionally.
- **Tearing down a shared container.** The `post` block only removes a Floci
  container the build itself created, so a long-running one survives.

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
