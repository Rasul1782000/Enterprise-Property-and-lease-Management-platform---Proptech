# Project Setup and Build

## Overview

This project is an enterprise application containing two independent components:

1. **backend/** - Laravel PHP application
2. **frontend/** - Angular web application

## Setup

### Backend (Laravel)

```powershell
cd backend
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

### Frontend (Angular)

```powershell
cd frontend
npm ci
npm start
```

### Local API stack (Docker)

The Laravel API, MySQL, Redis, and Floci run together through Docker Compose in
`frontend/deployment/`. Create `frontend/deployment/.env`, set unique local
secrets, then follow
[`frontend/deployment/DEPLOYMENT.md`](frontend/deployment/DEPLOYMENT.md).

## Optimization Highlights

- **Lockfile-driven installs:** `composer install` and `npm ci` reproduce an
  exact dependency tree.
- **Single Laravel API image:** one Dockerfile
  (`frontend/deployment/Dockerfile.backend`) serves the local stack and CI.
- **Named volumes:** MySQL and object data persist across container restarts.
- **Health-gated startup:** the API waits for the MySQL and Redis health
  checks before it starts.

## Project Structure

```
Enterprise Property and lease Manaement Portal/
├── backend/                    # Laravel PHP Application
│   ├── composer.json          # PHP dependencies
│   ├── .env.example           # Environment template
│   ├── app/                   # Application code
│   └── public/                # Public assets
└── frontend/                   # Angular Web Application
    ├── package.json          # Node.js dependencies
    ├── proxy.conf.json       # API proxy configuration
    ├── deployment/           # Docker/Compose/Jenkins files
    └── src/                  # Angular application code
```

## Configuration Details

### Backend (Laravel)
- **PHP Version:** ^8.2
- **Framework:** Laravel ^12.0
- **Database:** MySQL (SQLite/PostgreSQL also supported)
- **Access:** Dashboard and API routes are public; no authentication or authorization is configured.

### Frontend (Angular)
- **Framework:** Angular 22.2.0
- **Build System:** Node.js/npm
- **Styling:** Angular Material
- **Grid:** AG-Grid Community

## Technical Specifications

### Runtime Requirements
- **Node.js** 24.15+ (or 22.22.3+) with npm
- **PHP** 8.2+ with Composer
- **Docker** with Compose v2 (for the local API stack)

### Integration
- **Laravel API** → **Angular Frontend**
- **API Gateway:** http://localhost:8000/api
- **WebSocket Support:** Real-time updates
- **File Upload:** Secure storage integration

## Testing and Verification

### Automated Tests
```bash
# Backend tests
cd backend
php artisan test

# Frontend tests
cd frontend
npm run test
```

### Manual Testing
```powershell
# Check environment variables
cd backend
php artisan tinker

# Test API endpoints
curl http://localhost:8000/api/health
```

## Troubleshooting

### Common Issues

#### 1. Node Modules Not Found
**Symptom:** `npm ci` fails or hangs
**Solution:** Clear `node_modules` and `package-lock.json`, then rerun `npm ci`.

#### 2. PHP Extensions Missing
**Symptom:** `composer install` fails
**Solution:** Install the required PHP extensions and rerun `composer install`.

### Debugging Commands
```powershell
where node
where npm
where php
where composer
```

## Best Practices

### Development Workflow
1. **Setup Phase:** Install dependencies once (`composer install`, `npm ci`)
2. **Backend Development:** Work in `backend/` directory
3. **Frontend Development:** Work in `frontend/` directory
4. **Cross-Component Testing:** Test APIs between components

### Version Management
- Commit lockfiles (`composer.lock`, `package-lock.json`)
- Document environment-specific configurations
- Use `.gitignore` to prevent committing dependencies and secrets
- Back up configuration files

## CI/CD

[`frontend/deployment/Jenkinsfile`](frontend/deployment/Jenkinsfile) runs the
pipeline, and the frontend deploys to Vercel. See
[`VERCEL_DEPLOYMENT.md`](VERCEL_DEPLOYMENT.md) and
[`frontend/deployment/DEPLOYMENT.md`](frontend/deployment/DEPLOYMENT.md).

---

*Version: 2.0*
*Last Updated: 2026-10-11*
*Maintainers: Development Team*
