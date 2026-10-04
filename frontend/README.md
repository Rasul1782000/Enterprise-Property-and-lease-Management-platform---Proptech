# Frontend — PropertyLease Portal (Angular 22 + PrimeNG 22)

## Requirements
- **Node.js 22.22.3+ / 24.15+ / 26+** — Angular 22 hard-fails on older runtimes
- npm 8+

## Install
```powershell
cd frontend
npm ci            # deterministic install from package-lock.json
npm start         # http://localhost:4200 (proxies /api -> http://localhost:8000)
```

## Scripts
```powershell
npm run lint        # ESLint 10 + angular-eslint 22 (flat config)
npm test            # Vitest 5 via @angular/build:unit-test (jsdom, no browser needed)
npm run build       # dist/frontend/browser
npm run build:prod  # production config (environment.prod.ts, hashed assets, budgets)
```

## Architecture
- **Angular 22**, standalone components, lazy-loaded feature modules
- **PrimeNG 22** with a custom `@primeuix/themes` preset (BounceBox)
- **Signals** for state (`PropertyContextService`) — NgRx-ready facade
- **Lazy chunks** per feature: properties, buildings, units, tenants, leases, invoices, dashboard
- **Forms**: reactive forms + multi-step lease wizard with custom validators
- **Charts**: chart.js (dashboard)
- **Interceptors**: Sanctum bearer token, error toast, optional in-memory mock data source
- **Mobile**: Capacitor 8 (Android/iOS)

## Environments
| File | Used by | apiUrl | Mock data |
|---|---|---|---|
| `src/environments/environment.ts` | dev (`ng serve`, `ng test`) | `http://localhost:8000/api` | on |
| `src/environments/environment.prod.ts` | `--configuration production` | `/api` (proxied by nginx) | off |

`angular.json` swaps them via `fileReplacements` in the `production` configuration — do not remove that block, or the production bundle ships the dev API URL and mock data.

## Testing
- Runner: **Vitest** (`@angular/build:unit-test`, jsdom) — 11 specs across feature areas
- `src/testing/test-providers.ts` — PrimeNG `MessageService` / `ConfirmationService` providers for TestBed
- `src/testing/setup.ts` — jsdom polyfill for `ResizeObserver` (PrimeNG tabs)
- `tsconfig.spec.json` — spec compilation with `vitest/globals` types

## Docker
```powershell
docker build -t proptech-frontend .
docker run --rm -p 8080:80 proptech-frontend
```
Multi-stage build (`node:22.22-alpine` → `nginx:1.27-alpine`). `nginx.conf` serves the SPA with history fallback, immutable caching for hashed assets, and reverse-proxies `/api` to `http://backend:8000`.

## Login
Seeded account: `admin@propertylease.test` / `password`