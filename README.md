# Firmware94shop

**GSM Digital Services Marketplace & Reseller API Platform** — Phase 2 Foundation.

Laravel 12 / PHP 8.2+ / Livewire 3 / Tailwind CSS / MySQL / Redis.

> **Status:** This is a from-scratch Phase 2 Foundation build. The code was written
> by hand in an environment with **no outbound network access** (Composer/npm
> package registries were unreachable), so `composer install`, migrations, and the
> test suite have **not been executed or verified to boot**. See
> [`docs/PHASE2_REPORT.md`](docs/PHASE2_REPORT.md) for the full, honest status of
> every item, and [`docs/INSTALLATION.md`](docs/INSTALLATION.md) to actually run it.

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
# configure DB_* and REDIS_* in .env, then:
php artisan migrate --seed
php artisan f94:install-admin
npm install && npm run build
php artisan serve
```

Full details, including how to run this against a disposable test database and
the test suite, are in [`docs/INSTALLATION.md`](docs/INSTALLATION.md).

## Documentation

- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — module layout, supplier adapter pattern, order engine
- [`docs/DATABASE.md`](docs/DATABASE.md) — schema, ERD description, design decisions
- [`docs/API_OVERVIEW.md`](docs/API_OVERVIEW.md) — DHRU-compatible + REST v1 inbound APIs
- [`docs/SECURITY.md`](docs/SECURITY.md) — what's enforced, what's deliberately NOT implemented yet
- [`docs/TESTING.md`](docs/TESTING.md) — how to run the test suite, and what it covers
- [`docs/PHASE2_REPORT.md`](docs/PHASE2_REPORT.md) — the final, line-by-line status report

## What this is not

This is a **foundation**, not a finished product. Supplier integrations are mock
simulators only — no real DHRU Classic, GSM Theme, Fusion Pro, or GSMSS credentials
or endpoints are used anywhere. See `docs/PHASE2_REPORT.md` for exactly what's next.
