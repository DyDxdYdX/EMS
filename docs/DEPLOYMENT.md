# Deployment Guide

## Documentation Status

Updated September 2026. The stack has been updated to **Laravel 13, Livewire 4, Flux UI, and Pest 5**. Application timezone is `Asia/Kuala_Lumpur`. Update this document if a hosting platform or pipeline is added.

Related: [Architecture](ARCHITECTURE.md) · [Decisions](DECISIONS.md)

## Requirements

From the repository:

| Requirement | Source |
| --- | --- |
| PHP `^8.3` (PHP 8.5 supported) | `composer.json` |
| Node.js 20.19+ or 22.12+ | Vite 8 (`vite-plus`, `@tailwindcss/vite`) |
| Composer | PHP dependencies |
| npm | `package-lock.json` is present; `composer setup` runs `npm install` |
| Database | MySQL in `.env.example`; SQLite is the config default if `DB_CONNECTION` is unset |


PHP extensions expected by Laravel 13 include ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, pdo, session, tokenizer, and xml. For MySQL also enable `pdo_mysql`.

Web server: PHP-FPM behind Nginx or Apache with `public/` as the document root (standard Laravel). This repo does not ship Nginx/Apache config.

Laravel Sail is in `require-dev`, but there is **no** `docker-compose.yml` or `Dockerfile` in the project. Sail is not a ready-made deploy path until it is installed and committed.

There is **no** GitHub Actions workflow for deploy. `.github/dependabot.yml` only updates GitHub Actions weekly.

## Environment Variables

Names from `.env.example`. Use placeholders in real environments. Never commit secrets.

```env
APP_NAME=
APP_ENV=
APP_KEY=
APP_DEBUG=
APP_URL=

APP_LOCALE=
APP_FALLBACK_LOCALE=
APP_FAKER_LOCALE=

APP_MAINTENANCE_DRIVER=

BCRYPT_ROUNDS=

LOG_CHANNEL=
LOG_STACK=
LOG_DEPRECATIONS_CHANNEL=
LOG_LEVEL=

DB_CONNECTION=
DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=
SESSION_LIFETIME=
SESSION_ENCRYPT=
SESSION_PATH=
SESSION_DOMAIN=

BROADCAST_CONNECTION=
FILESYSTEM_DISK=
QUEUE_CONNECTION=

CACHE_STORE=

MEMCACHED_HOST=

REDIS_CLIENT=
REDIS_HOST=
REDIS_PASSWORD=
REDIS_PORT=

MAIL_MAILER=
MAIL_SCHEME=
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=

VITE_APP_NAME=
```

Passkeys and email password reset are disabled. `PASSKEYS_USER_HANDLE_SECRET` is unused.

Set `APP_URL` to the public HTTPS origin. The application timezone is hardcoded to `Asia/Kuala_Lumpur` in `config/app.php` (not an env flag).

Do not copy production values into git.

## Production Setup

**Recommended Laravel steps** (not automated by this repository):

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan key:generate --force   # only on first deploy, never rotate casually
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Create the first user with artisan or a production seeder. Do not seed `test@example.com` on a live farm. After migrate, run `FarmSeeder` (once implemented) so grades, expense categories, and `eggs_per_tray = 30` exist.

Permissions: the web user must write to `storage/` and `bootstrap/cache/`.

`storage:link` is standard Laravel. This app does not currently serve farm uploads.

### Queues and scheduler

`.env.example` uses `QUEUE_CONNECTION=database`. The application defines **no** jobs. A queue worker is not required for current features.

`routes/console.php` only registers the sample `inspire` command. A cron entry for `php artisan schedule:run` is **not required** for current features.

If you later add queued mail or reports, run a worker and scheduler then.

## Deployment

**No hosting platform is defined** in this repo (no Forge, Vapor, Docker, or CI deploy files).

Recommended for a small PHP app:

1. Provision MySQL and a PHP 8.3+ host.
2. Deploy code to the server.
3. Point the web root at `public/`.
4. Set production env values (`APP_ENV=production`, `APP_DEBUG=false`, real `APP_KEY`, HTTPS `APP_URL`).
5. Run the production setup commands above on each release.

Local development often uses Laravel Herd (this project lives under a Herd directory) or `composer run dev`. That is not a production setup.

## Backup

- Back up the MySQL database on a regular schedule (daily is enough for a small farm once data exists).
- Uploaded files: only relevant if you later store logos or attachments on disk. Back up `storage/app/` if that happens.
- Keep `APP_KEY` and `.env` in a secret manager, not in the database dump’s public notes.

This repository does not include backup scripts.

## Rollback

Lightweight approach:

1. Keep the previous release directory (or git tag).
2. Point the web root back to that release.
3. If a migration ran and cannot be reversed safely, restore the last database backup. Do not rely on `migrate:rollback` in production without testing.

Laravel 13 blocks some destructive DB commands in production (`DB::prohibitDestructiveCommands`).

## Security Checklist

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] Unique `APP_KEY`
- [ ] HTTPS, and `APP_URL` uses `https://`
- [ ] Strong `DB_PASSWORD`; env file not web-accessible
- [ ] Public registration **disabled**. Create users by seeder or artisan — not `/register`
- [ ] Confirm the login page does not offer Sign up
- [ ] `APP` timezone is `Asia/Kuala_Lumpur` (set in `config/app.php`)
- [ ] Run farm seed data (grades, expense categories, tray size) without the local test user
- [ ] Database backups tested
- [ ] `storage/` and `bootstrap/cache/` writable only by the app user
- [ ] `composer install --no-dev` on the server
- [ ] Do not seed `test@example.com` in production
