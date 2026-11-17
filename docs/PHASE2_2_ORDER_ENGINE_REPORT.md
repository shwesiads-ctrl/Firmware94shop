# Phase 2.2 — Order Engine Testing Report

**Status: FAIL (2 new P0 defects found) → FIXED / NOT EXECUTED (no test runner available)**

`app/Services/Orders/OrderEngine.php` and `app/Jobs/ReconcileUnknownSupplierOrders.php` were re-read in full, end to end, specifically hunting for the scenarios this phase's master prompt calls out that Phase 2.1 did not test: supplier timeout/unknown reconciliation, safe retry, and refund safety under confirmed failure. Two genuine, previously-unnoticed P0 defects were found this way — not fabricated to fill a quota; both are real logic errors demonstrated below and fixed with the smallest safe change.

## BUG-010 (P0): an exception from the supplier adapter left funds debited with the order stuck forever

**Where:** `OrderEngine::sendToSupplier()`, the call `$adapter->placeOrder($payload)` (previously unguarded, no `try`/`catch` anywhere in the method).

**Root cause:** the method set the order to `STATUS_SENT_TO_SUPPLIER` and then called the adapter directly. Every one of the four "live" supplier adapters (`DhruClassicAdapter`, `GsmThemeAdapter`, `FusionProAdapter`, `GsmssAdapter`) is intentionally architecture-only and **unconditionally throws `RuntimeException`** on every method call until real supplier documentation is supplied — this is correct, deliberate behavior per the original master prompt ("never fabricate a response"). But nothing caught that exception. It propagated straight out of `placeOrder()` to the caller (an HTTP controller, a Livewire action, or the DHRU-compatible API) as an uncaught 500.

**Impact:** by the time `sendToSupplier()` is called, the wallet has already been debited (inside the preceding `DB::transaction()`, which already committed). The thrown exception meant:
- The order was permanently stuck at `STATUS_SENT_TO_SUPPLIER` — not `STATUS_UNKNOWN`.
- `ReconcileUnknownSupplierOrders` only ever queries `WHERE status = Order::STATUS_UNKNOWN` (confirmed by reading that job) — it would **never** pick this order up.
- No refund, no staff flag, no automatic recovery path of any kind existed for this state. The customer's money was debited with nothing to show for it and no system-level way to notice.
- This is not a hypothetical: any attempt to exercise a "live" supplier today (a misconfigured `.env` flipping one `F94_SUPPLIER_*_MODE=live` without real credentials yet, which is an easy operator mistake given the four flags exist specifically to be flipped later) reproduces this on the very first order.

**Severity:** P0 — direct, permanent, undetectable loss of customer funds with no recovery path.

**Fix:** wrapped the `$adapter->placeOrder($payload)` call in `try { } catch (\Throwable $e) { }`. On catch, a `supplier_orders` row is still recorded (status `unknown`, the exception message kept in `response_payload` for diagnostics) and the order is routed through the existing, already-correct `markUnknown()` path — the same safe resting state a genuine timeout/ambiguous response already uses. This does not guess success or failure; it defers to reconciliation/staff, consistent with the "never auto-refund an order whose supplier acceptance is unknown" rule.

**Regression test:** `tests/Feature/Security/AuditPhase22RegressionTest.php::'BUG-010'` — drives an order through the real `GsmssAdapter` (forced to `live` mode via `config()`), confirms the order lands at `STATUS_UNKNOWN` (not stuck, not a 500) and the wallet balance reflects the debit without being stuck or doubled.

## BUG-011 (P0): confirmed supplier failure during reconciliation was silently never refunded

**Where:** `ReconcileUnknownSupplierOrders::handle()`, the `else` branch for a confirmed (`isFinal()`, not `isSuccessful()`) supplier failure.

**Root cause:** the code read:
```php
app(\App\Services\Orders\OrderEngine::class);
$order->recordEvent('status_updated', ['result' => 'failed_confirmed_by_reconciliation']);
```
`app(OrderEngine::class)` resolves an instance from the container and **does nothing with it** — no method is called. This is dead code, almost certainly a leftover from an incomplete implementation (the comment above it, "safe to resolve via the same controlled path OrderEngine uses for a direct failure", describes intent that was never carried out, and `finalizeFailure()` was `private` anyway, so it could not have been called from here even if someone had tried).

**Impact:** when the supplier *positively confirms* an order failed (not ambiguous — `isFinal()` and not successful), the job only updated the `supplier_orders` row and logged an event. **It never updated the parent `order.status` and never issued the refund.** The order stayed at `STATUS_UNKNOWN` forever, even though the entire point of this job is to resolve exactly that state once the supplier's answer is no longer ambiguous. A customer whose order the supplier explicitly rejected would never get their money back through any automated path — contradicting the master prompt's own "Refund safety" and "Status reconciliation" requirements (stage 9) for the one case (confirmed failure) where an automatic refund is explicitly supposed to happen.

**Severity:** P0 — same class as BUG-010 (money stuck, this time after an explicit, confirmed supplier rejection — the single clearest case where a refund should be automatic and guaranteed).

**Fix:** changed `OrderEngine::finalizeFailure()` from `private` to `public` (pure visibility change, zero behavior change to its existing callers) and replaced the dead `app(OrderEngine::class);` line with an actual call: `app(OrderEngine::class)->finalizeFailure($order, 'Confirmed failed by reconciliation: ...')`. This runs the order through the exact same tested refund path (`finalizeFailure()`) used for a direct, synchronous supplier rejection — no new logic, no duplicated refund code.

**Regression test:** `tests/Feature/Security/AuditPhase22RegressionTest.php::'BUG-011'` — places an order that lands at `STATUS_UNKNOWN` (mock timeout scenario), manually gives it a supplier reference whose status re-check resolves to a confirmed rejection, runs the reconciliation job, and asserts the order ends at `STATUS_REFUNDED` with the wallet balance restored.

## BUG-012 (P2, documented — not fixed this phase)

`SupplierManager::for($slug)` resolves mock-vs-live **only** from `config('firmware94.suppliers.<slug>.mode')`, which is populated from env vars (`F94_SUPPLIER_*_MODE`). The `suppliers` database table has its own `mode` column, and `App\Models\Supplier::isMocked()` reads it — but nothing in the codebase ever calls `isMocked()`, and `SupplierManager` never queries the `suppliers` table at all. The DB column and the model method are **dead** with respect to actually choosing an adapter.

This is a genuine design inconsistency, not a crash or a financial-correctness bug, so it was not "fixed" under this phase's smallest-safe-fix discipline (the right resolution — should the DB row override config, should config override the DB row, should they be reconciled into one source of truth — is a product decision, exactly the kind of judgment call Phase 2.1 deferred on `transaction_hash` before this phase properly investigated it). Flagged here so it doesn't get silently rediscovered as a surprise later: **an admin who edits a supplier's `mode` in the database (or via a future admin UI built against that column) will see no effect on which adapter actually runs** — only editing the environment variable does anything.

## Other required scenarios — static verdicts (re-verified, no behavior change found)

| Scenario | Verdict |
|---|---|
| Valid order | PASS (logic) — unchanged from Phase 2.1 analysis. |
| Invalid service | PASS (logic) — `is_active` check, `ValidationException`. |
| Invalid parameters | PASS (logic) — `validateInputs()` via `Validator::make()`. |
| Insufficient wallet balance | PASS (logic) — propagates from `WalletService::debit()`. |
| Duplicate order | PASS (logic) — Phase 2.1 BUG-001 fix, re-verified unchanged. |
| Cross-user idempotency | PASS (logic) — same. |
| Concurrent order submission | NOT VERIFIED — same caveat as wallet concurrency (needs real MySQL). |
| Supplier success / rejection / timeout / unknown status | PASS (logic), re-verified via `match()` in `applySupplierResult()`. |
| Status reconciliation | **FAIL → FIXED (BUG-011)**. |
| Safe retry | PASS (logic) — idempotency key reuse returns the existing order rather than re-executing any side effect. |
| Refund safety | **FAIL → FIXED (BUG-010, BUG-011)**; the "never auto-refund on unknown" rule itself was never violated — the bugs were about failing to refund on *confirmed* failure and failing to safely reach "unknown" at all when the adapter throws. |
| Correct order ownership | PASS (logic) — Phase 2.1 fix, re-verified unchanged. |

## Summary

| Check | Status |
|---|---|
| BUG-010 (adapter exception → stuck order/funds) | FAIL → FIXED |
| BUG-011 (reconciliation confirmed-failure no-op) | FAIL → FIXED |
| BUG-012 (DB `suppliers.mode` unused) | DOCUMENTED, not fixed (design decision needed) |
| All other required order scenarios | PASS (logic) / NOT VERIFIED (concurrency, needs real DB) |
| Execution of new/existing order tests | NOT EXECUTED (no vendor/autoload.php) |
