# Production Deployment Guide

This guide covers deploying the Enterprise Property & Lease Management Portal to `https://rasul17.indevs.in` with automatic HTTPS via Let's Encrypt.

## Architecture

```
┌─────────────────┐     ┌─────────────────┐
│   Traefik v3    │────▶│   Frontend      │
│   (SSL Term)    │     │   (Nginx)       │
└─────────────────┘     └─────────────────┘
        │                       ▲
        │                       │
        ▼                       │
┌─────────────────┐     ┌─────────────────┐
│   Backend       │◀────│   Backend       │
│   (PHP-FPM)     │     │   Worker        │
└─────────────────┘     └─────────────────┘
        │
        ▼
┌─────────────────┐     ┌─────────────────┐
│   MySQL 8.0     │     │   Redis 7       │
└─────────────────┘     └─────────────────┘
```

## Prerequisites

1. **Domain**: `rasul17.indevs.in` pointing to your server's public IP
2. **Docker & Docker Compose** installed
3. **Ports 80 & 443** open on firewall
4. **SMTP credentials** for transactional emails

## Quick Start

### 1. Prepare Environment File

```bash
cp .env.production .env.production.local
# Edit .env.production.local with your values
```

Required variables to set:
- `APP_KEY` - Generate with: `php artisan key:generate --show`
- `DB_PASSWORD` - Secure database password
- `DB_ROOT_PASSWORD` - Secure root password
- `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION` - SMTP settings

### 2. Deploy

**Linux/macOS:**
```bash
chmod +x deploy.sh
./deploy.sh production
```

**Windows:**
```powershell
.\deploy.ps1 -Environment production
```

### 3. Verify Deployment

- Frontend: https://rasul17.indevs.in
- API: https://rasul17.indevs.in/api
- Traefik Dashboard: http://rasul17.indevs.in:8080 (insecure, restrict in production)

## Manual Commands

```bash
# View logs
docker compose -f docker-compose.prod.yml logs -f

# Restart specific service
docker compose -f docker-compose.prod.yml restart backend

# Run migrations
docker compose -f docker-compose.prod.yml run --rm backend php artisan migrate --force

# Clear caches
docker compose -f docker-compose.prod.yml run --rm backend php artisan config:clear
docker compose -f docker-compose.prod.yml run --rm backend php artisan cache:clear

# Scale workers
docker compose -f docker-compose.prod.yml up -d --scale backend-worker=3

# Backup database
docker compose -f docker-compose.prod.yml exec db mysqldump -u root -p property_lease > backup.sql

# Restore database
docker compose -f docker-compose.prod.yml exec -T db mysql -u root -p property_lease < backup.sql
```

## SSL Certificates

Traefik automatically obtains Let's Encrypt certificates:
- Stored in `letsencrypt` Docker volume
- Renewed automatically (every 60 days)
- HTTP→HTTPS redirect enforced

## Environment Variables Reference

| Variable | Description | Required |
|----------|-------------|----------|
| `APP_KEY` | Laravel encryption key (32 chars) | Yes |
| `APP_ENV` | Environment (production) | Yes |
| `APP_DEBUG` | Debug mode (false) | Yes |
| `APP_URL` | Full URL with https | Yes |
| `FRONTEND_URL` | Frontend URL for CORS | Yes |
| `DB_PASSWORD` | Database user password | Yes |
| `DB_ROOT_PASSWORD` | Database root password | Yes |
| `MAIL_*` | SMTP configuration | Yes |
| `SESSION_DOMAIN` | Cookie domain | Yes |
| `SANCTUM_STATEFUL_DOMAINS` | CSRF trusted domains | Yes |
| `AWS_*` | S3 configuration (optional) | No |
| `OPENWA_*` | WhatsApp gateway (optional) | No |

## Health Checks

All services have health checks:
- **Traefik**: Dashboard on :8080
- **Frontend**: HTTP GET /
- **Backend**: HTTP GET /up (Laravel health endpoint)
- **MySQL**: mysqladmin ping
- **Redis**: redis-cli ping

## Monitoring

Access Traefik dashboard at `http://rasul17.indevs.in:8080` to monitor:
- Active routes
- SSL certificate status
- Request metrics
- Service health

## Troubleshooting

### Certificate Issues
```bash
# Check Traefik logs
docker compose -f docker-compose.prod.yml logs traefik

# Verify domain DNS
dig rasul17.indevs.in

# Force certificate renewal
docker compose -f docker-compose.prod.yml exec traefik \
  traefik certificatesresolvers.letsencrypt.acme.email=admin@rasul17.indevs.in
```

### Backend Not Accessible
```bash
# Check backend logs
docker compose -f docker-compose.prod.yml logs backend

# Test internal connectivity
docker compose -f docker-compose.prod.yml exec frontend curl http://backend:8000/up
```

### Database Connection Failed
```bash
# Check MySQL logs
docker compose -f docker-compose.prod.yml logs db

# Verify credentials
docker compose -f docker-compose.prod.yml exec db mysql -u property_lease -p
```

## Security Checklist

- [ ] Change all default passwords in `.env.production.local`
- [ ] Restrict Traefik dashboard (disable `api.insecure` or add auth)
- [ ] Enable firewall: only 80, 443, 22 (SSH) open
- [ ] Set up fail2ban for SSH
- [ ] Configure automated backups
- [ ] Set up log aggregation (Loki, ELK, etc.)
- [ ] Enable Redis AUTH if exposed
- [ ] Review CORS settings in `backend/config/cors.php`

## Rollback

```bash
# Stop current deployment
docker compose -f docker-compose.prod.yml down

# Restore previous database backup
docker compose -f docker-compose.prod.yml exec -T db mysql -u root -p property_lease < backup.sql

# Start previous version (tag images for versioning)
docker compose -f docker-compose.prod.yml up -d
```

## Support

For issues, check:
1. Service logs: `docker compose -f docker-compose.prod.yml logs -f <service>`
2. Traefik dashboard for routing issues
3. Laravel logs: `docker compose -f docker-compose.prod.yml exec backend tail -f storage/logs/laravel.log`