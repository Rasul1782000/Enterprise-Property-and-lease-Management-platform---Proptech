# Monitoring (Prometheus + Grafana)

A standalone [Prometheus](https://prometheus.io/) and
[Grafana](https://grafana.com/) stack that scrapes the Laravel API's built-in
metrics endpoint and renders dashboards.

The API already exposes Prometheus text-format metrics at `GET /metrics` on port
`8000` (`backend/routes/web.php` -> `App\Http\Controllers\Api\MetricsController`),
using the `propertylease_` namespace. This stack does not change the API; it only
scrapes it.

## Files

| Path | Purpose |
| --- | --- |
| `docker-compose.monitoring.yml` | Prometheus + Grafana services |
| `.env.example` | Configuration template (copy to `.env`) |
| `prometheus/prometheus.yml.tmpl` | Scrape config template, rendered at container start |
| `grafana/provisioning/` | Auto-provisioned Prometheus datasource + dashboard provider |
| `grafana/dashboards/gaarbejana-api.json` | The provisioned API dashboard |

## Prerequisites

Docker with the Compose plugin. The Prometheus service joins the **API's Docker
network** so it can reach the API container by service name (`app:8000`). Start
the API stack first (see `../DEPLOYMENT.md`), or point `API_TARGET` at any other
reachable metrics endpoint.

## Setup

```powershell
cd frontend\deployment\monitoring
Copy-Item .env.example .env
# Edit .env and set GRAFANA_ADMIN_PASSWORD (and API_TARGET / API_NETWORK if needed)
docker compose -f docker-compose.monitoring.yml up -d
```

Then open:

- Prometheus: <http://localhost:9090> (check **Status > Targets** for `gaarbejana-api`)
- Grafana: <http://localhost:3000> (log in with `GRAFANA_ADMIN_USER` /
  `GRAFANA_ADMIN_PASSWORD`; the **Gaarbejana API** dashboard is the home page)

## Configuration

All settings live in `.env` (loaded automatically because this is the Compose
project directory):

| Variable | Default | Meaning |
| --- | --- | --- |
| `GRAFANA_ADMIN_PASSWORD` | *(required)* | Grafana admin password |
| `API_TARGET` | `app:8000` | `host:port` Prometheus scrapes |
| `API_SCHEME` | `http` | `http` or `https` |
| `API_NETWORK` | `deployment_default` | External Docker network of the API stack |
| `ENVIRONMENT` | `local` | Value of the `environment` external label |
| `PROMETHEUS_PORT` / `GRAFANA_PORT` | `9090` / `3000` | Host ports (bound to `127.0.0.1`) |
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
