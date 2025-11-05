# Deployment

## Server Requirements

- PHP 8.1+, required PHP extensions (incl. OpenSSL)
- MySQL
- Node.js for building assets (optional if committing built assets)

## Steps

1. Set environment variables (`.env`).
2. Install dependencies: `composer install --no-dev --optimize-autoloader`.
3. Build assets: `npm ci && npm run prod` (or use pre-built assets).
4. Run migrations: `php artisan migrate --force`.
5. Cache config/routes/views:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

6. Queue/Workers (if using async): configure Supervisor/PM2 and set `QUEUE_CONNECTION` accordingly.
7. Web server: configure document root to `public/`, include `.htaccess` (or Nginx equivalent).

## Backups

- DB dumps via `spatie/db-dumper` (if configured) or native tooling.

## Zero-Downtime (optional)

- Consider Envoy/Deployer, health checks, and rolling migrations.
