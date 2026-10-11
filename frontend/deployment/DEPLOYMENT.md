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

From the repository root in PowerShell:

```powershell
Copy-Item frontend\deployment\.env.local.example frontend\deployment\.env.local
```

Replace the `CHANGE_ME` values with unique local secrets. Generate an
application key with `php artisan key:generate --show` from `backend\` and put
the returned `base64:...` value in `APP_KEY`. Then run:

```powershell
frontend\deployment\build-and-run.bat local
```

The script builds one Laravel API image, starts MySQL/Redis/Floci, waits for
MySQL, applies migrations, ensures the S3 bucket, and starts the API on
`http://localhost:8000`. MySQL and object data persist in named volumes. The
`stop-local` action stops containers without deleting those volumes.

To create local sample records and login accounts, first set
`DEMO_SEED_ENABLED=true` and choose a unique `DEMO_PASSWORD` of at least 12
characters in `.env.local`, then run:

```powershell
frontend\deployment\build-and-run.bat demo
```

The admin login is `admin@propertylease.test`; use the password from your local
`.env.local`. Password hashes are stored in MySQL. Demo seeding is rejected in
production and is never run automatically by migrations.

Start the Angular development server separately from `frontend\` with
`npm ci` and `npm start`. The demo credentials are for local development only.

## Optional local Jenkins/Floci CI

Jenkins is bound to `127.0.0.1:8888`; set a unique `JENKINS_ADMIN_PASSWORD`
in the current PowerShell session and run:

```powershell
$env:JENKINS_ADMIN_PASSWORD = Read-Host "Jenkins administrator password"
frontend\deployment\build-and-run.bat ci
```

The Jenkins container mounts the host Docker socket to build the API image.
Anyone controlling that Jenkins instance can effectively control the host.
Keep it local-only, do not run untrusted jobs, and do not expose it to the
internet. Floci does not require Docker socket access for this project's S3
integration. OpenWA is optional and starts only with the `whatsapp` Compose
profile.

The single Windows batch orchestrator is
[`build-and-run.bat`](./build-and-run.bat); it supports `build`, `local`,
`demo`, `ci`, `stop-local`, and `stop-ci`. Stop actions preserve data. Do not
run `docker compose down -v` unless intentionally deleting local databases and
uploads.
