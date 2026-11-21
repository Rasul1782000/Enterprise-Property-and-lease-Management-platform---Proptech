# Deployment and local development

## Production architecture

Vercel hosts the Angular frontend only. The Laravel API can be deployed to
Render as a Docker Web Service using the root `render.yaml` blueprint; MySQL and
persistent object storage must still be hosted separately. Floci is a local/CI
AWS emulator, not production storage. Do not use local Compose volumes as a
production database or document backup.

The API and dashboard do not have authentication or authorization: every API
endpoint is public, including data reads and management changes. Do not deploy
this configuration with sensitive data on an untrusted network. Configure a
TLS-protected API origin, provision a managed MySQL database with backups, and
use persistent S3-compatible storage with least-privilege credentials. Store
production secrets in the chosen hosting provider's secret manager. Run
migrations and smoke tests against that environment before directing users to
it. See [`../../VERCEL_DEPLOYMENT.md`](../../VERCEL_DEPLOYMENT.md).

## Render (Laravel API web service)

Render has no native PHP runtime, so the API is deployed as a Docker Web
Service. The root `render.yaml` blueprint builds
`frontend/deployment/Dockerfile.backend.render` with the repository root as
the Docker context. Create it from **New → Blueprint** in the Render dashboard
and select this repository and branch `main`.

The blueprint prompts for `APP_KEY` (paste the output of
`php artisan key:generate --show`), `FRONTEND_URL`, the `DB_*` credentials, and
the S3 credentials. `APP_URL` is mapped from Render's `RENDER_EXTERNAL_URL`.
Render does not provide managed MySQL; use an external MySQL instance that
accepts Render connections. The health check path is `/up`.

`render-entrypoint.sh` renders `${PORT}` into the nginx config at container
start, so the service binds to Render's dynamically assigned port. Do **not**
set `PORT` manually or use the local `Dockerfile.backend` for Render — the
local image hardcodes port 8000 and serves the frontend separately.

The entrypoint runs `php artisan portal:bootstrap` on every container start:
it applies migrations and, **only when the `properties` table is empty**, seeds
a small demo dataset (`PORTAL_SEED_USERS`, default 30). Existing data is never
truncated. Production seeding stays disabled unless this bootstrap command
explicitly enables it. You can still run migrations manually in the **Shell**
tab with `php artisan migrate --force`.

## Local API, MySQL, Redis, and Floci

Create `frontend\deployment\.env` and set `APP_KEY`, `DB_PASSWORD`, and
`DB_ROOT_PASSWORD`, plus the optional `PORTAL_SEED_USERS` value; the Compose
file in `docker-compose.local.yml` lists the required
names. Generate an application key with `php artisan key:generate --show` from
`backend\` and put the returned `base64:...` value in `APP_KEY`. Compose loads
that file automatically. Then build the one Laravel API image, start
MySQL/Redis/Floci, apply migrations, ensure the S3 bucket, and start the API:

```powershell
docker compose -f frontend\deployment\docker-compose.local.yml build app
docker compose -f frontend\deployment\docker-compose.local.yml up -d --wait db redis floci
docker compose -f frontend\deployment\docker-compose.local.yml run --rm app php artisan migrate --force
docker compose -f frontend\deployment\docker-compose.local.yml run --rm app php artisan storage:ensure-bucket
docker compose -f frontend\deployment\docker-compose.local.yml up -d app
```

The API listens on `http://127.0.0.1:8000` by default; set `API_PORT` in
`frontend/deployment/.env` if that host port is occupied. This workspace uses
`http://127.0.0.1:18000`. MySQL and object data persist in named volumes.
Stop containers without deleting those volumes with:

```powershell
docker compose -f frontend\deployment\docker-compose.local.yml down
```

To seed the database with the portal dataset (15 properties and 3000 tenant
users with leases), optionally set `PORTAL_SEED_USERS` in `.env`, then run:

```powershell
docker compose -f frontend\deployment\docker-compose.local.yml run --rm app php artisan db:seed --force
```

The API and dashboard are public and require no login. Seeding truncates the
data tables first, is rejected in production, and is never run automatically by
migrations.

Start the Angular development server separately from `frontend\` with
`npm ci` and `npm start`.

## Prometheus and Grafana monitoring

The API exposes Prometheus text-format metrics at `http://127.0.0.1:8000/metrics`
(`propertylease_*` namespace). A standalone Compose stack in
[`monitoring/`](./monitoring/README.md) scrapes that endpoint and renders dashboards;
it runs alongside the API and does not modify it. Start the API first, then create
the monitoring `.env`, set a Grafana admin password, and start the stack:

```powershell
cd frontend\deployment\monitoring
Copy-Item .env.example .env
# Set GRAFANA_ADMIN_PASSWORD; adjust API_TARGET / API_NETWORK if needed
docker compose -f docker-compose.monitoring.yml up -d
```

Prometheus is served on `http://localhost:9090` and Grafana on
`http://localhost:3000`. Both bind to `127.0.0.1` only. By default Prometheus
joins the API stack's Docker network (`API_NETWORK=deployment_default`) and
scrapes the `app` service at `app:8000`. See
[`monitoring/README.md`](./monitoring/README.md) for configuration, remote
scraping, and operational commands.

Stop the stack (data is kept in named volumes) with:

```powershell
docker compose -f frontend\deployment\monitoring\docker-compose.monitoring.yml down
```

## Optional local Jenkins/Floci CI

Jenkins runs in the monitoring stack and is bound to `127.0.0.1:8888`. Set a
unique `JENKINS_ADMIN_PASSWORD` in `monitoring/.env` (or in the current
PowerShell session), build the Jenkins image, and start it:

```powershell
$env:JENKINS_ADMIN_PASSWORD = Read-Host "Jenkins administrator password"
docker compose -f frontend\deployment\monitoring\docker-compose.monitoring.yml build jenkins
docker compose -f frontend\deployment\monitoring\docker-compose.monitoring.yml up -d jenkins
```

The Jenkins container mounts the host Docker socket to build the API image.
Anyone controlling that Jenkins instance can effectively control the host.
Keep it local-only, do not run untrusted jobs, and do not expose it to the
internet. Floci runs in the app stack
(`frontend\deployment\docker-compose.local.yml`) and does not require Docker
socket access for this project's S3 integration. OpenWA is optional and starts
only with the `whatsapp` Compose profile of the app stack.

Stop the stacks with:

```powershell
docker compose -f frontend\deployment\monitoring\docker-compose.monitoring.yml down
docker compose -f frontend\deployment\docker-compose.local.yml down
```

Stop actions preserve data. Do not run `docker compose down -v` unless
intentionally deleting local databases and uploads.
