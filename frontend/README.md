# Frontend — PropertyLease Portal (Angular 18 + Material + AG-Grid)

## Requirements
Node 20+, Angular CLI 18

## Install
```powershell
cd frontend
npm install
# dev proxy to Laravel backend
npm start  # http://localhost:4200 (proxies /api -> http://localhost:8000)
# or
ng serve --proxy-config proxy.conf.json
```

Build:
```powershell
npm run build        # dist/frontend
```

## Features Implemented
- **Data Tables & Filtering**: MatTable with MatSort/MatPaginator + AG-Grid Community (units, tenants) with multi-column filter, sorting, pagination, CSV export
- **Form Management**: Reactive Forms + multi-step lease wizard (4 steps, custom validators: email, phone, date range `end > start`, unit vacant check)
- **State Management**: Signals (PropertyContextService: selectedPropertyId, tenantId, sidebar) — NgRx-ready facade
- **Routing**: Lazy-loaded standalone components, authGuard + roleGuard, layout with sidenav
- **PDF**: Lease agreement + invoice receipt via backend DomPDF (blob open)
- **Interceptors**: Sanctum Bearer token, withCredentials

## API
Set `src/environments/environment.ts` apiUrl = `http://localhost:8000/api`

## Login
Use seeded accounts: admin@propertylease.test / password

## NG-Rx Alternative
Project defaults to Signals. To migrate: `ng add @ngrx/store @ngrx/effects @ngrx/entity` and replace PropertyContextService with entity store — API surface identical.
