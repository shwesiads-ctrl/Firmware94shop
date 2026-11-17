# Phase 2.2 — Bug Fix Log

Every bug below was found by actually re-reading the relevant source file(s) line by line this phase, not assumed or invented to fill a quota. Each entry gives the exact root cause, the exact fix, and the exact file(s) changed. **None of the regression tests added this phase were executed** — see `docs/PHASE2_2_TEST_REPORT.md`. Severity labels follow the master prompt's own scale: P0 (critical financial/auth), P1 (duplication/corruption/API security), P2 (functional/integration), P3 (UI/UX/performance).

| ID | Severity | Area | One-line summary | Status |
|---|---|---|---|---|
| BUG-007 | P0 | Environment/packaging | `ext-bcmath` never declared in `composer.json`, absent from this sandbox — every wallet operation would fatal without it. | FIXED (declared); extension itself cannot be installed here (no package-manager network access) |
| BUG-008 | P1 | Deposits / DB schema | `deposits.transaction_hash` had no uniqueness or normalization, allowing the same on-chain tx to be submitted twice. | FIXED (new migration + app-layer normalization) |
| BUG-009 | P1 | Wallet | `debit()`/`credit()`/`hold()` silently flipped direction on a negative amount; allowed a zero-amount no-op ledger row. | FIXED |
| BUG-010 | P0 | Order engine | An exception from the supplier adapter during `placeOrder()` left the order stuck forever with wallet funds already debited and un-refundable, un-flagged, un-reconciled. | FIXED |
| BUG-011 | P0 | Order reconciliation | `ReconcileUnknownSupplierOrders` resolved a confirmed supplier failure by doing literally nothing — no refund, order stuck at UNKNOWN forever. | FIXED |
| BUG-012 | P2 | Supplier architecture | `suppliers.mode` DB column / `Supplier::isMocked()` are dead code; only env-driven config actually selects mock vs. live adapters. | DOCUMENTED — not fixed (design decision needed) |
| BUG-014 | P2 | REST v1 API authorization | Only `orders.create` scope is ever checked; `orders.read`/`wallet.read`/`account.read` implied by the `scopes` column comment are never enforced anywhere. | DOCUMENTED — not fixed (contradicts existing `RestV1ApiTest.php` fixture/contract; needs a product decision) |
| BUG-015 | P1 | DHRU API authorization | `DhruCompatibleController::placeOrder()` never checked the `orders.create` scope that its REST v1 sibling already enforces — an authorization bypass via the alternate API surface. | FIXED |

*(Numbering note: BUG-013 was not assigned to any finding — the numbering simply skips it. This log and the other Phase 2.2 reports consistently use BUG-014 and BUG-015 for the two scope findings below.)*

## BUG-007 — `ext-bcmath` undeclared and absent (P0)

**File:** `composer.json`. **Root cause / fix:** see `docs/PHASE2_2_ENVIRONMENT_REPORT.md` §2 for the full write-up (discovered there, fixed here).

```diff
     "require": {
         "php": "^8.2",
+        "ext-bcmath": "*",
         "laravel/framework": "^12.0",
```

This makes `composer check-platform-reqs` (once it can run, against a real lockfile) correctly fail loudly on any environment missing the extension, instead of deploying silently broken and only discovering it on the first live wallet operation. The extension itself cannot be installed in this sandbox (no apt/package-manager network access — confirmed in the Environment Report); this is now a declared, visible deployment requirement rather than a silent landmine.

## BUG-008 — deposit `transaction_hash` dedupe (P1)

**Files:** `database/migrations/2026_01_01_000100_add_transaction_hash_dedupe_to_deposits_table.php` (new), `app/Services/Payments/DepositService.php`. Full investigation and design rationale in `docs/PHASE2_2_DATABASE_REPORT.md` §3.

## BUG-009 — wallet sign-handling (P1)

**File:** `app/Services/Wallet/WalletService.php`, new `app/Exceptions/InvalidWalletAmountException.php`. Full demonstration and write-up in `docs/PHASE2_2_SECURITY_TEST_REPORT.md` §1 (scenarios 4/5) and inline comments at each of the three guarded call sites (`debit()`, `credit()`, `hold()`).

## BUG-010 — uncaught supplier exception stuck order/funds (P0)

**File:** `app/Services/Orders/OrderEngine.php`, method `sendToSupplier()`. Full write-up in `docs/PHASE2_2_ORDER_ENGINE_REPORT.md`.

## BUG-011 — reconciliation no-op on confirmed failure (P0)

**Files:** `app/Services/Orders/OrderEngine.php` (`finalizeFailure()` visibility `private` → `public`), `app/Jobs/ReconcileUnknownSupplierOrders.php`. Full write-up in `docs/PHASE2_2_ORDER_ENGINE_REPORT.md`.

## BUG-012 — dead `suppliers.mode` column (P2, documented only)

No file changed. Full write-up in `docs/PHASE2_2_ORDER_ENGINE_REPORT.md`.

## BUG-014 — inconsistent REST v1 scope enforcement (P2, documented only)

No file changed. Full write-up in `docs/PHASE2_2_API_TEST_REPORT.md` §4.

## BUG-015 — DHRU `placeimeiorder` missing scope check (P1)

**File:** `app/Api/Inbound/DhruCompatible/DhruCompatibleController.php`, method `placeOrder()`. Full write-up in `docs/PHASE2_2_API_TEST_REPORT.md` §3.

## Regression tests added this phase

All in `tests/Feature/Security/AuditPhase22RegressionTest.php` (new file, 12 `test()` blocks): BUG-009 (a/b/c/d + one "still works" check), cross-wallet idempotency re-check, decimal precision re-check, BUG-008 (×2: normalization, null-passthrough), BUG-010, BUG-011, BUG-015. **Not executed** — see `docs/PHASE2_2_TEST_REPORT.md`.

## Final syntax sweep

```
$ find . -name "*.php" -not -path "./vendor/*" | xargs -I{} php -l {}
181/181 files: No syntax errors detected
```
(Phase 2.1 ended at 145/145; this phase added 36 net new/changed PHP files — new exception class, new migration, new test file, plus edits to existing files — all still syntax-clean. This is a syntax check only, not a semantic or runtime verification.)
