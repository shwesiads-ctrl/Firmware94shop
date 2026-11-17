# Phase 2.2 — Changelog

Branch: `test/phase-2.2` (from `audit/phase-2.1` @ `d580813`).

## Fixed

- **BUG-007 (P0):** declared `"ext-bcmath": "*"` in `composer.json` — previously undeclared despite `WalletService` and `PricingService` both depending on it unconditionally. (`composer.json`)
- **BUG-008 (P1):** added a normalized, lifecycle-aware uniqueness constraint for `deposits.transaction_hash`, resolving the gap Phase 2.1 flagged but deliberately left open. (`database/migrations/2026_01_01_000100_add_transaction_hash_dedupe_to_deposits_table.php` [new], `app/Services/Payments/DepositService.php`)
- **BUG-009 (P1):** `WalletService::debit()`/`credit()`/`hold()` now reject zero/negative amounts instead of silently mutating the balance in the wrong direction. (`app/Services/Wallet/WalletService.php`, `app/Exceptions/InvalidWalletAmountException.php` [new])
- **BUG-010 (P0):** `OrderEngine::sendToSupplier()` now catches any exception from the supplier adapter and routes the order to `STATUS_UNKNOWN` instead of leaving it permanently stuck with funds debited. (`app/Services/Orders/OrderEngine.php`)
- **BUG-011 (P0):** `ReconcileUnknownSupplierOrders` now actually resolves a confirmed supplier failure (refund + status update) instead of doing nothing; `OrderEngine::finalizeFailure()` changed from `private` to `public` to make this possible. (`app/Jobs/ReconcileUnknownSupplierOrders.php`, `app/Services/Orders/OrderEngine.php`)
- **BUG-015 (P1):** `DhruCompatibleController::placeOrder()` now requires the `orders.create` scope, matching the REST v1 `OrderController::store()` behavior it previously bypassed. (`app/Api/Inbound/DhruCompatible/DhruCompatibleController.php`)

## Documented, not fixed (requires a product decision — see Bug Fix Log for full reasoning)

- **BUG-012 (P2):** `suppliers.mode` DB column has no effect on adapter resolution; only env config does.
- **BUG-014 (P2):** only the `orders.create` scope is enforced anywhere; `orders.read`/`wallet.read`/`account.read` are implied but unchecked, and an existing test (`RestV1ApiTest.php`) already encodes the current (unscoped-read) behavior as intended.

## Added

- `tests/Feature/Security/AuditPhase22RegressionTest.php` — 12 regression tests for BUG-008/009/010/011/015.
- `.env.testing` — isolated SQLite/array/sync testing environment, no real credentials.
- `docs/PHASE2_2_*.md` — 9 new report files (Environment, Dependency, Boot, Database, Security Test, Order Engine, API Test, Supplier Test, UI Test, Queue/Redis/Scheduler, Test, Bug Fix Log, this Changelog, and the Deployment Checklist).

## Environment / tooling notes (not code changes)

- Re-confirmed the sandbox's network policy blocks `composer install`, `npm install`, and anything needing Packagist/npm/Docker Hub — identical root cause to Phase 2 and Phase 2.1, re-verified with fresh commands rather than assumed carried over.
- Local Redis was actually started and exercised directly in this sandbox (bypassing the blocked Composer install) and genuinely passed — see `docs/PHASE2_2_QUEUE_REDIS_REPORT.md`.
- No destructive command was ever run against any database — there is no database server reachable at all in this sandbox.

## Not changed

- `main` branch: untouched. `audit/phase-2.1`: untouched (this work is entirely on `test/phase-2.2`).
- No unrelated refactors were made; every change above maps to one specific, demonstrated bug.
