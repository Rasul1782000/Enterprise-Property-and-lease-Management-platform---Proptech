# Deploying the frontend to Vercel

The Angular frontend (`frontend/`) is deployed to **Vercel**. The Laravel API is
**not** part of this deployment — it keeps running at `https://rasul17.indevs.in`,
which is the `apiUrl` compiled into `environment.prod.ts`. Jenkins deploys to
Vercel and to nothing else (the Docker/Traefik production host is no longer a
deploy target).

## What lives where

| File | Purpose |
|---|---|
| `frontend/vercel.json` | Vercel project settings: build command, output dir, SPA rewrite, security headers |
| `frontend/package.json` → `engines.node` | Pins Vercel's Node to `22.x` (Angular 22 needs `22.22.3+`) |
| `frontend/.nvmrc` | Local dev Node (`24.20.0`); also satisfies Angular 22 |
| `Jenkinsfile` → `Deploy Frontend to Vercel` | CI stage that runs `vercel pull/build/deploy --prod` on `main` |

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

The two IDs are already committed in the `environment` block at the top of
`Jenkinsfile`:

```groovy
VERCEL_ORG_ID     = 'team_wpE0cAB9zBtXOEltZjBRtcqP'
VERCEL_PROJECT_ID = 'prj_XcprW7xNZSBZRAp9pwOfNX7NA7gY'
VERCEL_TOKEN_ID   = 'vercel-token'   // Jenkins credential id
```

The only missing piece is the token. Add it as a Jenkins credential:

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
- If the two IDs are left empty the deploy stage is skipped, so a half-configured
  job cannot accidentally push a broken build.

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
3. Keep the API domain (`rasul17.indevs.in`) as-is.

If the frontend is served from a new origin, make sure the Laravel API's CORS
config allows it (`config/cors.php` / `FRONTEND_URL` in the backend env).

## Troubleshooting

- **Build fails with "Angular CLI requires a minimum Node.js version"** — Vercel
  is using an old Node. Confirm `"engines": { "node": "22.x" }` is present in
  `frontend/package.json`, or set Node 22.x under Vercel → Project → Settings →
  General → Node.js Version.
- **Blank page / 404 on refresh** — the SPA rewrite in `vercel.json` is missing
  or the output directory is wrong. It must be `dist/frontend/browser`.
- **`Error: Not currently logged in` in CI** — the `vercel-token` credential is
  missing, or the token is scoped to a team that cannot see `VERCEL_ORG_ID`.
