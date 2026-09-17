# Production deployment

## Required configuration

1. Install PHP 8.2 or newer with the GD extension. Copy `.env.example` and set `APP_ENV=production`, `APP_DEBUG=false`, a generated `APP_KEY`, and the public HTTPS `APP_URL`.
2. Configure a dedicated database account, production mail delivery, the filesystem disk, and secure session cookies (`SESSION_SECURE_COOKIE=true`).
3. Configure AWS Rekognition only if image scanning is required. Otherwise leave it disabled and keep the documented moderation fallback.
4. Set Turnstile keys if CAPTCHA is enabled.

## Release commands

```bash
composer install --no-dev --classmap-authoritative
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

Point the web server document root at `public/`. Never expose the repository root, `.env`, `storage`, or `vendor` directly.

Create the administrator interactively after the schema exists:

```bash
php artisan admin:create admin@example.com
```

## Scheduler

The application currently dispatches no background jobs, so it does not require a queue worker. Run the Laravel scheduler every minute for upload and stale reading-activity cleanup:

```cron
* * * * * cd /path/to/waasabi && php artisan schedule:run >> /dev/null 2>&1
```

## Web server

Apache can use `public/.htaccess`; ensure `mod_rewrite` is enabled and `AllowOverride All` applies to the public directory. For Nginx, route missing files to `index.php` using Laravel's standard configuration.

## Verification and rollback

- Check `/`, a project, search, login, password reset mail, publish, upload, and collaboration flows.
- Confirm security headers and HTTPS redirects at the edge.
- Back up the database and uploaded files before migrations.
- Roll back application code only when its schema remains compatible. Restore the database backup if a migration is not safely reversible.
