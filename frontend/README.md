# Frontend — PropertyLease Portal (Angular 22 + PrimeNG 22)

## Requirements
- **Node.js 22.22.3+ / 24.15+ / 26+** — Angular 22 hard-fails on older runtimes
- npm 8+

## Install
```powershell
cd frontend
npm ci            # deterministic install from package-lock.json
npm start         # http://localhost:4200 (proxies /api, /sanctum -> https://rasul17.indevs.in)
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

## Environments
| File | Used by | apiUrl | Mock data |
|---|---|---|---|
| `src/environments/environment.ts` | dev (`ng serve`, `ng test`) | `https://rasul17.indevs.in/api` | off |
| `src/environments/environment.prod.ts` | `--configuration production` | `https://rasul17.indevs.in/api` | off |

`angular.json` swaps them via `fileReplacements` in the `production` configuration — do not remove that block, or the production bundle ships the dev API URL and mock data.

## Styling: Tailwind CSS v4

Tailwind was migrated from v3 to v4. v4 is CSS-first, so the previous
`tailwind.config.js` is gone and the design tokens now live in `src/tailwind.css`
as an `@theme` block (coral/teal/sunny ramps, fonts, radii, shadows, spacing,
type scale, `min-height-touch`).

How the pieces fit together:

- `.postcssrc.json` registers `@tailwindcss/postcss`. Without it Angular falls
  back to auto-detecting a v3 `tailwind.config.js` and emits no utilities.
- `src/tailwind.css` is the Tailwind entrypoint and must be listed in the
  `styles` array of `angular.json`, **before** `src/styles.scss`.
- `src/styles.scss` starts with `@reference "./tailwind.css"` so its `@apply`
  calls can resolve theme tokens without re-emitting Tailwind.
- Component stylesheets that use `@apply` need their own `@reference` line.

**If `angular.json` is ever missing,** `ng build` / `ng serve` / `ng test` all
fail. When you restore it, make sure the global `build.options.styles` array
contains both entries, in this order:

```jsonc
"styles": [
  "node_modules/primeicons/primeicons.css",
  "src/tailwind.css",   // Tailwind v4 entry - must come first
  "src/styles.scss"
]
```

Do not collapse this back into a single `.scss` file. The Angular application
builder runs **Sass before PostCSS**, so `@import "tailwindcss"` inside a `.scss`
file is intercepted by Sass and fails with `Can't find stylesheet to import`.
Keeping the Tailwind entry in plain CSS is what makes this work.

The `Dockerfile` must stay in the tree as well; `Jenkinsfile` runs
`docker build -t <tag> .` from inside `frontend/`.

**`ng serve` refuses to start?** The Angular CLI hard-fails on unsupported Node
versions (e.g. `22.22.0`). Run `nvm use` in `frontend/` to pick up `.nvmrc`
(`24.20.0`), or install Node `22.22.3+` / `24.15+` / `26+`.

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

## Deploy (Vercel)

The Angular app is deployed to **Vercel**. The Laravel API is *not* deployed
by this pipeline — it stays at `https://rasul17.indevs.in`, which is also the
`apiUrl` baked into both environment files. Build settings live in
`vercel.json`:

- `buildCommand`: `npm run build:prod`
- `outputDirectory`: `dist/frontend/browser`
- every route is rewritten to `/index.html` so deep links work
- `"engines": { "node": "22.x" }` in `package.json` pins Vercel's Node to a
  release that satisfies Angular 22's runtime requirement

Linked/manual deploy:

```powershell
npx vercel login       # once
npx vercel link        # writes .vercel/project.json
npx vercel pull --yes --environment=production
npx vercel build --prod
npx vercel deploy --prebuilt --prod
```

CI runs the same commands (`npx vercel@latest`) in the Jenkinsfile's
**Deploy Frontend to Vercel** stage. See `../VERCEL_DEPLOYMENT.md`.

## Login
Seeded account: `admin@propertylease.test` / `password`