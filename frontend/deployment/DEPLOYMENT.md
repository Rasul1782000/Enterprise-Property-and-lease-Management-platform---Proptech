# Deployment and local development

## Production architecture

Vercel hosts the Angular frontend only. The Laravel API, MySQL database, and
persistent object storage must be hosted separately; this repository does not
configure or deploy those production services. Floci is a local/CI AWS emulator,
not production storage. Do not use local Compose volumes as a production
database or document backup.

Before a production release, configure a TLS-protected API origin, restrict
Laravel CORS/Sanctum to the real frontend origin, provision a managed MySQL
database with backups, and use persistent S3-compatible storage with
least-privilege credentials. Store production secrets in the chosen hosting
provider's secret manager. Run migrations and smoke tests against that
environment before directing users to it. See [`../../VERCEL_DEPLOYMENT.md`](../../VERCEL_DEPLOYMENT.md).

## Local API, MySQL, Redis, and Floci

Create `frontend\deployment\.env` and set `APP_KEY`, `DB_PASSWORD`, and
`DB_ROOT_PASSWORD`, plus the optional `DEMO_SEED_ENABLED` / `DEMO_PASSWORD`
values; the Compose file in `docker-compose.local.yml` lists the required
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

The API listens on `http://localhost:8000`. MySQL and object data persist in
named volumes. Stop containers without deleting those volumes with:

```powershell
docker compose -f frontend\deployment\docker-compose.local.yml down
```

To create local sample records and login accounts, first set
`DEMO_SEED_ENABLED=true` and choose a unique `DEMO_PASSWORD` of at least 12
characters in `.env`, then run:

```powershell
docker compose -f frontend\deployment\docker-compose.local.yml run --rm app php artisan db:seed --force
```

The admin login is `admin@propertylease.test`; use the password from your local
`.env`. Password hashes are stored in MySQL. Demo seeding is rejected in
production and is never run automatically by migrations.

Start the Angular development server separately from `frontend\` with
`npm ci` and `npm start`. The demo credentials are for local development only.

## Prometheus and Grafana monitoring

The API exposes Prometheus text-format metrics at `http://localhost:8000/metrics`
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

Jenkins is bound to `127.0.0.1:8888`; set a unique `JENKINS_ADMIN_PASSWORD`
in the current PowerShell session, build the Jenkins image, and start the
stack:

```powershell
$env:JENKINS_ADMIN_PASSWORD = Read-Host "Jenkins administrator password"
docker compose -f frontend\deployment\docker-compose.jenkins.yml build
docker compose -f frontend\deployment\docker-compose.jenkins.yml up -d jenkins floci
```

The Jenkins container mounts the host Docker socket to build the API image.
Anyone controlling that Jenkins instance can effectively control the host.
Keep it local-only, do not run untrusted jobs, and do not expose it to the
internet. Floci does not require Docker socket access for this project's S3
integration. OpenWA is optional and starts only with the `whatsapp` Compose
profile.

Stop Jenkins and Floci with:

```powershell
docker compose -f frontend\deployment\docker-compose.jenkins.yml down
```

Stop actions preserve data. Do not run `docker compose down -v` unless
intentionally deleting local databases and uploads.
