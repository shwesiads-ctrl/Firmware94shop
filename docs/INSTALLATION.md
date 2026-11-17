# Installation

## 1. Requirements

Checked in the target build environment (Ubuntu 24.04 LTS target per spec):

| Tool | Required | Notes |
|---|---|---|
| PHP | 8.2+ | 8.3.6 was available in the build sandbox |
| Composer | 2.x | 2.8.12 was available in the build sandbox |
| Node.js / npm | 18+ / 9+ | Node 22 / npm 10.9 were available in the build sandbox |
| MySQL / MariaDB | 8.x compatible | Not installed in the build sandbox — install separately |
| Redis | 6+ | 7.0.15 was available in the build sandbox |

**Important:** the sandbox this repository was written in had **no outbound network
access** (Packagist and the npm registry were both unreachable). None of the steps
below have actually been run. Run them yourself in an environment with normal
internet access.

## 2. Install dependencies

```bash
composer install
npm install
```

## 3. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
- `DB_*` — point at your MySQL/MariaDB instance (or set `DB_CONNECTION=sqlite` for
  a zero-config local try-out; create the file with `touch database/database.sqlite`)
- `REDIS_*` — point at your Redis instance
- `F94_PAY_USDT_TRC20_ADDRESS` / `F94_PAY_USDT_BEP20_ADDRESS` — leave blank until
  you have real receiving addresses; never hardcode these in code

## 4. Database

```bash
php artisan migrate --seed
```

This seeds roles/permissions, the four mock suppliers, and a small sample service
catalog. `DevelopmentDataSeeder` (sample distributor/reseller/customer users) only
runs automatically when `APP_ENV=local` or `testing` — **never run `migrate:fresh
--seed` against a database that has real data.**

## 5. Create the first Super Admin

There is no public admin registration route. Run:

```bash
php artisan f94:install-admin
```

and follow the prompts (name, email, a password of at least 12 characters).

## 6. Build frontend assets

```bash
npm run build
```

## 7. Queue & scheduler

```bash
php artisan queue:work --queue=default
php artisan schedule:work   # or a real cron entry calling `php artisan schedule:run` every minute
```

The scheduler drives `ReconcileUnknownSupplierOrders`, which re-checks orders stuck
in the `unknown` state every five minutes (see `routes/console.php`).

## 8. Run it

```bash
php artisan serve
```

## 9. Verify

```bash
php artisan about
php artisan route:list
php artisan test       # or: ./vendor/bin/pest
```

See [`TESTING.md`](TESTING.md) for what the test suite covers and how to point it
at a disposable database.
