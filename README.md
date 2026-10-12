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
php artisan migrate
# Optional local-only demo data: set DEMO_SEED_ENABLED=true and a unique
# DEMO_PASSWORD (12+ characters) in backend/.env before php artisan db:seed.
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

### 3) Local demo login (optional)
- Email: `admin@propertylease.test`
- Password: the local-only `DEMO_PASSWORD` configured before explicitly
  running the demo seeder. The seeder stores password hashes in MySQL and is
  disabled in production; there is no built-in shared demo password.

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
| Jenkins | `frontend/deployment/docker-compose.jenkins.yml` | Web UI on http://localhost:8888 |
| Floci | `floci/floci:latest` | Local AWS emulator, port 4566 |
| Frontend | Angular 22 + PrimeNG | Vitest via `@angular/build:unit-test` |
| Backend | Laravel 12 + MySQL | S3 access via `aws/aws-sdk-php` + Flysystem |

Before starting Jenkins, set a unique administrator password in the
`JENKINS_ADMIN_PASSWORD` environment variable. On Linux/macOS:

```bash
read -s JENKINS_ADMIN_PASSWORD
export JENKINS_ADMIN_PASSWORD
docker compose -f frontend/deployment/docker-compose.jenkins.yml up -d --build
```

On PowerShell:

```powershell
$secure = Read-Host "Jenkins administrator password" -AsSecureString
$env:JENKINS_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new("", $secure).Password
docker compose -f frontend/deployment/docker-compose.jenkins.yml up -d --build
```

Jenkins is available on http://localhost:8888 and is bound to localhost only.
Jenkins has access to the host Docker socket for image builds, which is
effectively host-level control. Floci does not need that socket for this
project's S3 use. Keep the CI stack local-only and do not run untrusted jobs.
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

Buckets the project expects on Floci:

| Bucket | Used by |
|---|---|
| `property-lease-ci` | Jenkins builds (`AWS_STORAGE_BUCKET` in the `Jenkinsfile`) |
| `property-lease-documents` | Local dev / `.env` default |
| `openwa-media` | Optional local OpenWA profile (`frontend/deployment/docker-compose.jenkins.yml`) |

```bash
# Create the bucket if it does not exist (idempotent)
cd backend && php artisan storage:ensure-bucket

# Run the backend suite; S3 tests skip automatically when no endpoint is reachable
cd backend && php artisan test
```

### Adding a service the pipeline needs

1. Add the plugin ID to the `jenkins-plugin-cli --plugins` list in
   `frontend/deployment/Dockerfile.jenkins` (shared by the Jenkins and
   monitoring Compose stacks).
2. Verify it still resolves — withdrawn plugins fail the image build.
3. Rebuild: `docker compose -f frontend/deployment/docker-compose.jenkins.yml build`.

### Jenkinsfile defects this setup avoids

Each of these was a real bug, worth remembering when editing the pipeline:

- **`params` vs `environment`.** `when { environment name: 'PUSH_IMAGE' }` never
  matches, because `PUSH_IMAGE` is a build *parameter*. Use
  `when { expression { params.PUSH_IMAGE == true } }`.
- **Hardcoded network names.** `docker network inspect jenkins_default` breaks
  when the compose directory or project name changes. The pipeline now reads the
  Jenkins container's own network via `docker inspect -f ... "$(hostname)"`.
- **Concatenated network names.** Ranging the network map with `{{$k}}{{end}}`
  joins the names with no separator (`jenkins_defaultmy-shared-network`), which
  then fails as a `docker network connect` argument. The pipeline uses
  `{{println $k}}` so each name is on its own line, and prefers a user-defined
  network over the DNS-less default `bridge`.
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
