# Deploying the frontend to Vercel

The Angular frontend (`frontend/`) is deployed to **Vercel**. The Laravel API is
**not** part of this deployment — it runs at `https://api.rasul17.indevs.in`,
which is the `apiUrl` compiled into `environment.prod.ts`. Jenkins deploys to
Vercel and to nothing else (the Docker/Traefik production host is no longer a
deploy target).

## What lives where

| File | Purpose |
|---|---|
| `frontend/vercel.json` | Vercel project settings: build command, output dir, SPA rewrite, security headers |
| `frontend/package.json` → `engines.node` | Pins Vercel's Node to `24.x` (Angular 22 needs `24.15.0+`) |
| `frontend/.nvmrc` | Local dev Node (`24.20.0`); also satisfies Angular 22 |
| `frontend/deployment/Jenkinsfile` → `Deploy Frontend to Vercel` | CI stage that runs `vercel pull/build/deploy --prod` on `main` |

## Status

This project is already linked and has a live production deployment:

- Team / org: `rasul-ahmed-khans-projects` — `team_wpE0cAB9zBtXOEltZjBRtcqP`
- Project: `frontend` — `prj_XcprW7xNZSBZRAp9pwOfNX7NA7gY`
- Production URL: <https://frontend-gamma-hazel-80.vercel.app>
- Alias: <https://frontend-rasul-ahmed-khans-projects.vercel.app>

The project's **Root Directory** is `frontend`, so the Vercel CLI is always run
from the **repository root** (not from inside `frontend/`). Linking writes
`.vercel/project.json` at the repo root; `.vercel/` and `.env*.local` are
gitignored.

> Deployment Protection ("Vercel Authentication") is enabled on the team, so the
> URL asks for a Vercel login. To make the site public, turn it off under
> Project → Settings → Deployment Protection.

## One-time setup

From the **repository root**:

```powershell
npx vercel login                 # browser / email confirmation
npx vercel link --project frontend
```

`vercel link` writes `.vercel/project.json` at the repo root with two
non-secret identifiers:

```jsonc
{
  "orgId": "team_wpE0cAB9zBtXOEltZjBRtcqP",
  "projectId": "prj_XcprW7xNZSBZRAp9pwOfNX7NA7gY"
}
```

Create a token at **Vercel → Settings → Tokens** (scope it to the team that owns
the project).

## Wiring it into Jenkins

The project IDs are configured in the `environment` block of
`frontend/deployment/Jenkinsfile`:

```groovy
VERCEL_ORG_ID     = 'team_wpE0cAB9zBtXOEltZjBRtcqP'
VERCEL_PROJECT_ID = 'prj_XcprW7xNZSBZRAp9pwOfNX7NA7gY'
VERCEL_TOKEN_ID   = 'vercel-token'   // Jenkins credential id
```

The Jenkins secret-text credential is required; the deploy stage fails if it
is absent or Vercel rejects the release. Full steps are in
[`CREDENTIALS.md`](CREDENTIALS.md). In short, add it as a Jenkins
credential:

- **Manage Jenkins → Credentials → (global) → Add Credentials**
- Kind: **Secret text**
- Secret: the Vercel token
- ID: `vercel-token` (must match `VERCEL_TOKEN_ID`)

Once `VERCEL_ORG_ID` and `VERCEL_PROJECT_ID` are set, every push to `main` runs:

```
npx vercel@latest pull --yes --environment=production --token="$VERCEL_TOKEN"
npx vercel@latest build --prod --token="$VERCEL_TOKEN"
npx vercel@latest deploy --prebuilt --prod --token="$VERCEL_TOKEN"
```

`vercel build` runs the `installCommand`/`buildCommand` from `vercel.json`
(`npm ci` then `npm run build:prod`) and produces `.vercel/output`; the last
command ships that prebuilt output to the production domain.

- Branches other than `main` stop after lint/test/build — they never deploy.
- Configure the production API origin, CORS allowlist, TLS, and object storage
  separately from Vercel. The API DNS record must point at the backend load
  balancer, and Laravel CORS must allow the Vercel frontend origin. Vercel hosts
  only the Angular static frontend.

### Repairing the API DNS delegation

Public DNS reports a lame delegation for `rasul17.indevs.in`. Route 53 records
inside the child zone are not visible until the parent DNS zone delegates the
child to the exact Route 53 name servers assigned to its hosted zone.

From the initialized Terraform directory, retrieve those nameservers:

```powershell
terraform output -json route53_name_servers
```

At the DNS provider authoritative for `indevs.in`, replace the broken
delegation for `rasul17.indevs.in` with the returned Route 53 name-server set.
Allow DNS propagation, then verify that `rasul17.indevs.in` and
`api.rasul17.indevs.in` resolve before retrying login.

## Manual deploy

From the **repository root**:

```powershell
npx vercel pull  --yes --environment=production
npx vercel build --prod
npx vercel deploy --prebuilt --prod
```

Or let Vercel build in the cloud (no local toolchain needed):

```powershell
npx vercel deploy --prod --yes
```

## Custom domain

Point the (sub)domain at the Vercel project, e.g. `app.rasul17.indevs.in`:

1. Vercel → Project → **Settings → Domains → Add**.
2. Add the `CNAME` Vercel shows at your DNS provider.
3. Keep the API domain (`api.rasul17.indevs.in`) as-is.

If the frontend is served from a new origin, make sure the Laravel API's CORS
config allows it (`config/cors.php` / `FRONTEND_URL` in the backend env).

## Troubleshooting

- **Build fails with "Angular CLI requires a minimum Node.js version"** — Vercel
  is using an old Node. Confirm `"engines": { "node": "24.x" }` is present in
  `frontend/package.json`, or set Node 24.x under Vercel → Project → Settings →
  General → Node.js Version.
- **Blank page / 404 on refresh** — the SPA rewrite in `vercel.json` is missing
  or the output directory is wrong. It must be `dist/frontend/browser`.
- **`Error: Not currently logged in` in CI** — the `vercel-token` credential is
  missing, or the token is scoped to a team that cannot see `VERCEL_ORG_ID`.
