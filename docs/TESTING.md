# Testing

## Honesty notice

**The test suite below has been written but NOT executed.** The sandbox this
repository was built in has no outbound network access, so `composer install`
could not run, there is no `vendor/` directory, and therefore no `php artisan
test` / `./vendor/bin/pest` could be invoked. Nothing in this document or in
`docs/PHASE2_REPORT.md` claims otherwise. Run the suite yourself after
`composer install` and report back what actually passes.

## How to run it

```bash
composer install
cp .env.example .env.testing   # or rely on phpunit.xml's <php> env overrides
php artisan key:generate --env=testing
./vendor/bin/pest
# or
php artisan test
```

Tests use `RefreshDatabase` against `sqlite :memory:` (configured in
`phpunit.xml`), so they never touch your real MySQL database. Never point
`DB_CONNECTION`/`DB_DATABASE` in a real `.env` at a production database and then
run `php artisan migrate:fresh --seed` or the test suite against it.

## What's covered, file by file

| File | Covers |
|---|---|
| `tests/Feature/Auth/RegistrationAndLoginTest.php` | registration → customer role + zero-balance wallet, login/logout, wrong password, suspended-account login block, role-gated route rejection |
| `tests/Feature/Auth/RolesAndPermissionsTest.php` | all 6 roles seed correctly, super-admin Gate bypass, a customer is denied a staff-only permission |
| `tests/Feature/Database/SchemaIntegrityTest.php` | every Phase 2 table exists post-migration; unique constraints (user email, one wallet per user) |
| `tests/Feature/Wallet/WalletLedgerTest.php` | debit/credit math, idempotency-key replay protection, insufficient-balance exception, hold/release, reversal-never-edits-original, a repeated-debit race scenario |
| `tests/Feature/Orders/OrderEngineTest.php` | success → debit; supplier rejection → auto-refund; supplier timeout → `unknown` status and **no** refund; idempotent replay; suspended user blocked |
| `tests/Feature/Suppliers/MockSupplierTest.php` | all 4 mock suppliers: connection, service listing, every documented `placeOrder`/`getOrderStatus` outcome, determinism (same input → same output), GSMSS's deliberate `getBulkOrderStatus` NotSupported path |
| `tests/Feature/Api/DhruCompatibleApiTest.php` | `accountinfo`, `imeiservicelist` (active-only), invalid credentials → 401 without a 500, undocumented action → explicit error |
| `tests/Feature/Api/RestV1ApiTest.php` | bearer-token auth required, account/wallet responses, inactive-credential rejection, usage logging |
| `tests/Unit/PricingServiceTest.php` | base price, percent markup (bcmath-correct), fixed-price override |

## What's intentionally NOT covered yet

- Frontend/browser-level UI tests (Dusk or similar) — Phase 2 ships server-side
  Feature tests for route access per role, not pixel-level UI assertions.
- Live supplier adapters — they are architecture-only and throw by design (see
  `docs/SECURITY.md`); there is nothing live to test yet.
- Queue/scheduler timing of `ReconcileUnknownSupplierOrders` — the job's query
  logic can be unit-tested; actual cron timing is an ops concern, not a unit test.
