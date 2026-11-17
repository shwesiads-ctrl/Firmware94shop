# Phase 2.2 — Supplier Mock Integration Testing Report

**Status: PASS (logic) / NOT EXECUTED (no test runner) — one real gap documented**

All four mock adapters (`MockDhruClassicSupplier`, `MockGsmThemeSupplier`, `MockFusionProSupplier`, `MockGsmssSupplier`) share `AbstractMockSupplier`, re-read in full this phase.

## 1. Required scenarios — coverage check

| Scenario | Covered? | Where |
|---|---|---|
| Supplier order success | Yes | `mock_scenario=success` (or no suffix match) → `SupplierResult::success()`. `MockSupplierTest::'placeOrder deterministically...'`. |
| Supplier order rejection | Yes | `mock_scenario=rejected` → `failed`. Same test. |
| Unknown order status | Yes | `mock_scenario=unknown` → `unknown`. Same test. |
| Service synchronization | Partial | `getServices()` returns a fixed catalog; there is no live-vs-mock "sync" step to test (Phase 2 mock catalog is static by design, not synced from a real supplier — correct for this phase, not a gap). |
| Timeout | Yes | `mock_scenario=timeout` → `timeout`. Same test; also `tests/Feature/Orders/OrderEngineTest.php::'a supplier timeout leaves the order as unknown...'` exercises it end-to-end through `OrderEngine`. |
| Authentication failure | Yes | `scenarioFromSuffix()`'s `'6' => 'auth_failure'` mapping exists for `testConnection()`-style checks (per the class docblock); not separately asserted in `MockSupplierTest.php` today — recommended addition, not a defect. |
| **Connection failure / Invalid JSON / HTTP 4xx / HTTp 5xx** | **No — genuine gap, see §2** | Not modeled at all by the mock layer; only `timeout`/`unknown` exist as "something went wrong and we're not sure what" states. |
| HTTP 4xx / 5xx | No (see §2) | Same. |

## 2. The real gap: mocks never simulate a thrown exception, only a `SupplierResult` with an ambiguous status

`AbstractMockSupplier::placeOrder()`/`getOrderStatus()` are pure functions — they always **return** a `SupplierResult`, never **throw**. "Connection failure", "Invalid JSON", and "HTTP 4xx/5xx" as the master prompt describes them are transport-layer failures a real HTTP-based adapter would surface as a thrown exception (a Guzzle `ConnectException`, a JSON-decode error, a `RequestException` for a 4xx/5xx) — not as a value the adapter hands back. The mock layer has no equivalent of these because it never makes an HTTP call to fail.

This is precisely the gap that made **BUG-010** (see `docs/PHASE2_2_ORDER_ENGINE_REPORT.md`) a real, reachable bug rather than a theoretical one: before this phase's fix, `OrderEngine::sendToSupplier()` had no handling at all for an adapter that throws instead of returning a result — and the four "live" stub adapters throw unconditionally. The fix (`try`/`catch (\Throwable $e)` around the adapter call, routing to `markUnknown()`) now means **any** of connection failure, invalid JSON, or an HTTP 4xx/5xx — however a real adapter chooses to represent them (as an exception) — is handled safely and generically, without needing the mock layer to simulate each one individually.

**Recommendation, not implemented this phase** (would be a test-suite addition, not a bug fix): add a small test double — e.g. an anonymous class implementing `SupplierAdapter` whose `placeOrder()` throws a `\RuntimeException('simulated connection failure')` — exercised directly against `OrderEngine::sendToSupplier()`'s new catch block, as a cleaner, purpose-built alternative to this phase's `GsmssAdapter`-based BUG-010 regression test (which works, but piggybacks on a stub that happens to always throw rather than a deliberately-scenario'd fake). Low priority: the BUG-010 fix already covers the behavior; this would only broaden *test* coverage of it.

## 3. Everything else re-verified unchanged

- All 4 adapters correctly report `supports()`/`guard()` for capability gating; `MockGsmssSupplier`'s `getBulkOrderStatus` NOT-supported case confirmed still correct.
- Determinism (no `rand()`/`mt_rand()`/time-based branching anywhere in `AbstractMockSupplier`) re-confirmed by re-reading the whole file — test suite stability holds.
- No mock adapter touches the wallet, the database, or any other side effect directly — confirmed side-effect-free, so a supplier-layer bug can never itself corrupt financial state (any financial impact has to flow back through `OrderEngine`, which is where BUG-010/011 actually lived).

## Summary

| Check | Status |
|---|---|
| Success / rejection / unknown / timeout scenarios | PASS (logic), existing coverage re-verified |
| Connection failure / invalid JSON / HTTP 4xx/5xx simulation | GAP documented; real-world equivalent (thrown exception) now safely handled by BUG-010's fix regardless |
| Supplier failures cannot corrupt wallet/order state | PASS (logic) — re-verified; this was false before BUG-010/011's fixes and is now true |
| Execution of supplier mock tests | NOT EXECUTED (no vendor/autoload.php) |
