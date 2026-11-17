# Phase 2.1 — Deployment Checklist

This assumes you're deploying the `audit/phase-2.1` branch (or `main` after
merging it) for the first time to a real server. Nothing on this list has
been executed from this sandbox (see `docs/PHASE2_1_TEST_REPORT.md`) — treat
it as a checklist to work through, not a report of things already verified.

## Before you start

- [ ] Merge `audit/phase-2.1` into `main` via a reviewed PR — do not fast-forward
      without reading `docs/PHASE2_1_BUG_FIX_LOG.md` first.
- [ ] Run `composer install --no-dev --optimize-autoloader` on a real machine
      with registry access, and actually run `php artisan test` before this
      checklist proceeds any further. If anything fails, fix it before
      deploying — nothing here has been confirmed to boot yet.

## Environment configuration

- [ ] `APP_ENV=production`, `APP_DEBUG=false` (never `true` in production —
      `bootstrap/app.php` already forces JSON error rendering for `/api/*`,
      but debug mode would still leak stack traces on web routes)
- [ ] `APP_KEY` generated fresh for this environment (`php artisan key:generate
      --force`) — never reuse a key from development or another environment
- [ ] `APP_URL` set to the real HTTPS domain
- [ ] `DB_*` pointed at the production MySQL 8 / MariaDB instance, with a
      dedicated, least-privilege database user (not root)
- [ ] `REDIS_*` pointed at a production Redis instance (used for cache,
      sessions if configured, and the default queue connection)
- [ ] `F94_PAY_USDT_TRC20_ADDRESS` / `F94_PAY_USDT_BEP20_ADDRESS` set to your
      real receiving addresses — these are blank by default and must be set
      per-environment; they are never hardcoded in code
- [ ] `MAIL_*` configured for a real transactional mail provider (password
      reset / email verification will not work on the default `log` driver)
- [ ] `F94_API_RATE_LIMIT_PER_MINUTE` reviewed for your expected reseller
      traffic volume

## Database preparation

- [ ] `php artisan migrate --force` on the real database (never
      `migrate:fresh` against data you care about)
- [ ] `php artisan db:seed --class=Database\\Seeders\\RolePermissionSeeder` and
      `SupplierSeeder` at minimum — **do not** run `DevelopmentDataSeeder` in
      production (it only auto-runs for `local`/`testing` environments by
      design, but double-check before any manual seed command)
- [ ] `php artisan f94:install-admin` to create the first real Super Admin —
      do this over a secure connection, never commit the password anywhere
- [ ] Verify backups are configured for the database *before* go-live, not
      after (see "Backup and recovery" below)

## Queue & scheduler

- [ ] A real queue worker running under a process supervisor (systemd,
      Supervisor, etc.) — `php artisan queue:work --queue=default --tries=3`;
      do not rely on `php artisan serve`'s synchronous request cycle
- [ ] A real cron entry: `* * * * * php artisan schedule:run` — this is what
      actually fires `ReconcileUnknownSupplierOrders` every 5 minutes; without
      it, orders stuck in `unknown` status are never reconciled automatically
- [ ] Confirm the queue `failed_jobs` table is being monitored/alerted on

## Cache configuration

- [ ] `php artisan config:cache`, `route:cache`, `view:cache` as part of your
      deploy pipeline (run *after* confirming `.env` is final for this
      environment — `config:cache` bakes in the current `.env` values)
- [ ] Redis `CACHE_STORE`/`REDIS_CACHE_DB` confirmed separate from the queue's
      Redis database/connection to avoid eviction policy surprises

## HTTPS

- [ ] TLS terminated at the load balancer/reverse proxy or directly on the
      app server — `AppServiceProvider::boot()` already calls
      `URL::forceScheme('https')` when `APP_ENV=production`, but that doesn't
      by itself add a certificate
- [ ] HSTS and secure cookie flags — `config/session.php`'s `secure` is read
      from `SESSION_SECURE_COOKIE`; set it to `true` in production

## Backup and recovery

- [ ] Automated MySQL/MariaDB backups with a tested restore procedure —
      wallet ledger data (`wallet_transactions`) is append-only and
      financially critical; losing it is not acceptable
- [ ] `storage/app/payment-proofs` (deposit proof uploads) included in backup
      scope — it's a private, non-public disk by design, so it's easy to
      forget it's not covered by a "backup public uploads" script

## Security checklist

- [ ] Confirm `F94_SUPPLIER_*_MODE` is still `mock` for every supplier until
      a live adapter has actually been implemented against real, official
      documentation and reviewed — see `docs/SECURITY.md` and the 4 "live"
      adapter stub classes, which throw on purpose
- [ ] Confirm no `.env` file (with real secrets) is committed — `.gitignore`
      already excludes it, but verify on the actual deploy target too
- [ ] Rotate `api_credentials` secrets for any key created during this audit
      or testing before go-live
- [ ] Review `docs/SECURITY.md`'s "Explicitly NOT done yet" section — 2FA,
      signed webhooks, and real blockchain verification are not implemented;
      confirm that's acceptable for your launch scope or block launch until
      addressed
- [ ] Set up log shipping/alerting for the `security` log channel
      (`storage/logs/security.log`) and for repeated `InsufficientBalanceException`/
      `DuplicateOrderException`/`WalletOwnershipException` occurrences, which
      would now surface as clean 402/409/4xx responses post-fix but are still
      worth watching for abuse patterns
