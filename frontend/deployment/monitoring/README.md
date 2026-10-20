# Monitoring (Prometheus + Grafana)

A standalone [Prometheus](https://prometheus.io/) and
[Grafana](https://grafana.com/) stack that scrapes the Laravel API's built-in
metrics endpoint and reads aggregate property-management data from MySQL.

The API already exposes Prometheus text-format metrics at `GET /metrics` on port
`8000` (`backend/routes/web.php` -> `App\Http\Controllers\Api\MetricsController`),
using the `propertylease_` namespace. Grafana also connects directly to the
local MySQL service with a read-only account for aggregate record counts.

## Files

| Path | Purpose |
| --- | --- |
| `docker-compose.monitoring.yml` | Prometheus + Grafana services |
| `.env.example` | Configuration template (copy to `.env`) |
| `prometheus/prometheus.yml.tmpl` | Scrape config template, rendered at container start |
| `grafana/provisioning/` | Auto-provisioned Prometheus datasource + dashboard provider |
| `grafana/dashboards/gaarbejana-api.json` | The provisioned API dashboard |
| `mysql/init-grafana-reader.sh` | Creates a read-only Grafana account on fresh local MySQL volumes |

## Prerequisites

Docker with the Compose plugin. Prometheus joins the API's Docker network and
scrapes the API container by its Compose service name (`app:8000`); it does not
route through a host-published port. Start the local API stack first so Compose
creates its default network:

```powershell
cd frontend\deployment
docker compose -f docker-compose.local.yml up -d --build app
```

For a local dashboard with sample records, run the explicitly gated demo seeder
against the non-production database:

```powershell
docker exec -e DEMO_SEED_ENABLED=true deployment-app-1 php artisan db:seed --class=DemoDataSeeder
```

It adds synthetic sample properties, leases, invoices, and related records; do
not run it against production or a database containing real data.

The default project name creates the `deployment_default` network expected by
this monitoring stack. The API is published on `127.0.0.1:8000` by default;
set `API_PORT` in `frontend/deployment/.env` if that host port is already in
use. Prometheus continues to scrape the container on its internal port 8000.
Grafana also joins this network to query MySQL directly using a read-only
`grafana_reader` account. Set the same strong `GRAFANA_MYSQL_PASSWORD` in both
`frontend/deployment/.env` and `frontend/deployment/monitoring/.env`. Use an
alphanumeric password of at least 32 characters; the init script rejects other
characters to safely embed the credential in SQL. On an existing database
volume, apply the account setup once after restarting the DB service:

```powershell
docker compose -f docker-compose.local.yml up -d db
docker exec deployment-db-1 /docker-entrypoint-initdb.d/20-grafana-reader.sh
```

If you use another Compose project name, set `API_NETWORK` to that API network
in the monitoring `.env`.

## Setup

```powershell
cd monitoring
Copy-Item .env.example .env
# Edit .env and set GRAFANA_ADMIN_PASSWORD (and API_TARGET / API_NETWORK if needed)
# Set GRAFANA_MYSQL_PASSWORD to the same value used in ../.env
docker compose -f docker-compose.monitoring.yml up -d
```

Then open:

- Prometheus: <http://localhost:9090> (check **Status > Targets** for `property-lease-api`)
- Grafana: <http://localhost:3001> (log in with `GRAFANA_ADMIN_USER` /
  `GRAFANA_ADMIN_PASSWORD`; the **Gaarbejana API** dashboard is the home page)

The dashboard includes MySQL-backed record counts. The existing API metrics
and business panels continue to use Prometheus.

## Configuration

All settings live in `.env` (loaded automatically because this is the Compose
project directory):

| Variable | Default | Meaning |
| --- | --- | --- |
| `GRAFANA_ADMIN_PASSWORD` | *(required)* | Grafana admin password |
| `GRAFANA_MYSQL_PASSWORD` | *(required)* | Password for the MySQL read-only `grafana_reader` account; set the same value in both local `.env` files |
| `API_TARGET` | `app:8000` | `host:port` Prometheus scrapes |
| `API_SCHEME` | `http` | `http` or `https` |
| `API_NETWORK` | `deployment_default` | External Docker network of the API stack |
| `ENVIRONMENT` | `local` | Value of the `environment` external label |
| `PROMETHEUS_PORT` / `GRAFANA_PORT` | `9090` / `3001` | Host ports (bound to `127.0.0.1`) |
| `PROMETHEUS_RETENTION` | `15d` | Prometheus data retention |

`API_TARGET` / `API_SCHEME` are substituted into `prometheus.yml.tmpl` by the
container entrypoint, because Prometheus itself only expands environment
variables in `external_labels`.

### Scraping a remote or public API

Set `API_SCHEME=https` and `API_TARGET=<host>` in `.env`, then remove the `api`
network from the `prometheus` service in `docker-compose.monitoring.yml` (an
external network that does not exist on the host would prevent startup). The
target's `/metrics` route must be publicly reachable.

### Finding the API network name

The default network is the one created by `../docker-compose.local.yml`. If you
use a different project name, look it up and set `API_NETWORK`:

```powershell
docker network ls
```

## Operations

```powershell
# Reload the scrape config after editing the template (container recreates it):
docker compose -f docker-compose.monitoring.yml up -d --force-recreate prometheus

# Follow logs
docker compose -f docker-compose.monitoring.yml logs -f prometheus grafana

# Stop (keeps Prometheus and Grafana data in named volumes)
docker compose -f docker-compose.monitoring.yml down

# Stop and delete all collected metrics and dashboards state
docker compose -f docker-compose.monitoring.yml down -v
```

## Metric reference

| Metric | Type | Labels |
| --- | --- | --- |
| `propertylease_http_requests_total` | counter | `method`, `route`, `status` |
| `propertylease_http_request_duration_seconds` | histogram | `method`, `route`, `status` |
| `propertylease_http_response_size_bytes` | histogram | `method`, `route` |
| `propertylease_http_exceptions_total` | counter | `route`, `exception` |
| `propertylease_database_queries_total` | counter | `connection` |
| `propertylease_database_queries_by_operation_total` | counter | `connection`, `operation` |
| `propertylease_database_query_duration_seconds` | histogram | `connection`, `operation` |
| `propertylease_process_memory_bytes` | gauge | - |
| `propertylease_properties_total` | gauge | - |
| `propertylease_tenants_total` | gauge | - |
| `propertylease_units_total` | gauge | `status` |
| `propertylease_occupancy_ratio` | gauge | - |
| `propertylease_leases_total` | gauge | `status` |
| `propertylease_leases_expiring_soon` | gauge | - |
| `propertylease_leases_active_monthly_rent` | gauge | - |
| `propertylease_invoices_total` | gauge | `status` |
| `propertylease_invoices_overdue_amount` | gauge | - |
| `propertylease_invoices_pending_amount` | gauge | - |
| `propertylease_invoices_paid_amount` | gauge | - |
| `propertylease_payments_total` | gauge | - |

Business gauges refresh at most once per `PROMETHEUS_BUSINESS_TTL` seconds
(default 60) while the endpoint is being scraped.
