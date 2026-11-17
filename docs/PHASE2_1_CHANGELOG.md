# Phase 2.1 — Changelog

Branch: `audit/phase-2.1` (off `main` @ `eff1283`). `main` was not touched.

## Files modified

| File | Change |
|---|---|
| `app/Services/Orders/OrderEngine.php` | BUG-001: idempotency-key order lookup (both the pre-transaction check and the in-transaction race recheck) now verifies `user_id` ownership before returning/accepting a match; throws `ValidationException` on mismatch instead of leaking another user's order |
| `app/Services/Wallet/WalletService.php` | BUG-002: `debit()`/`credit()` idempotency replay now verifies `wallet_id` ownership before returning a matched transaction; throws the new `WalletOwnershipException` on mismatch |
| `app/Services/Payments/DepositService.php` | BUG-006: `approve()`/`reject()` now lock the `Deposit` row (`lockForUpdate()`) inside a transaction before checking/changing its status, closing a double-approval race |
| `app/Api/Inbound/RestV1/OrderController.php` | BUG-003/004: `store()` now also catches `InsufficientBalanceException` (→ 402) and `DuplicateOrderException` (→ resolves and returns the already-created order, or 409) |
| `app/Api/Inbound/DhruCompatible/DhruCompatibleController.php` | Same as above, in the DHRU-compatible response shape |
| `app/Http/Middleware/LogApiUsage.php` | BUG-005: no longer reads the global `LARAVEL_START` constant directly; captures a locally-scoped fallback value at the start of `handle()` |

## New files

| File | Purpose |
|---|---|
| `app/Exceptions/WalletOwnershipException.php` | Thrown by `WalletService` when an idempotency key is replayed against a wallet it wasn't originally posted to (BUG-002) |
| `tests/Feature/Security/AuditPhase21RegressionTest.php` | One regression test (plus a "still works normally" guard test) per bug fixed in this audit |
| `docs/PHASE2_1_AUDIT_REPORT.md` | This audit's full findings, stage by stage |
| `docs/PHASE2_1_BUG_FIX_LOG.md` | Per-bug root cause / fix / test detail |
| `docs/PHASE2_1_TEST_REPORT.md` | Exact command results and what's blocked vs. verified |
| `docs/PHASE2_1_CHANGELOG.md` | This file |
| `docs/PHASE2_1_DEPLOYMENT_CHECKLIST.md` | Production readiness checklist |

## Database changes

**None.** No migration was added, changed, or removed in this audit. The
schema from Phase 2 was reviewed (`docs/PHASE2_1_AUDIT_REPORT.md` §D) and no
structural defect was found that required a migration to fix — all 6 bugs
fixed this pass were application-logic races/ownership gaps, not schema
gaps. One schema-level finding (`deposits.transaction_hash` not unique) was
identified but deliberately **not** acted on — see the bug fix log's "Not
fixed in this pass" section.

## API changes

**None to the request/response contract.** `OrderController::store` and
`DhruCompatibleController`'s `placeimeiorder` action can now return:
- REST v1: `402` with `{"message": "Insufficient wallet balance."}` (previously:
  an unhandled 500)
- REST v1: `409` with `{"message": "Duplicate order request could not be
  resolved."}` in the (rare) case a raced duplicate can't be resolved to an
  existing order (previously: an unhandled 500)
- REST v1: `422` with `{"idempotency_key": ["This idempotency key is already
  in use by another order."]}` when a key collides across users (previously:
  silently returned the other user's order with a `201`)
- DHRU-compatible: the equivalent `{"STATUS":"error","ERROR":"..."}` shapes at
  the same status codes

These are all *new, previously-undocumented* error responses for conditions
that previously either leaked data (BUG-001) or crashed (BUG-003/004) — no
existing successful response shape changed.

## Security improvements

See `docs/PHASE2_1_BUG_FIX_LOG.md` for full detail on BUG-001 through
BUG-006. In summary: closed a cross-user order-data disclosure path, a
latent cross-wallet ledger disclosure path, a wallet double-credit race on
deposit approval, and two unhandled-exception paths in both inbound APIs that
could surface as unstructured 500s for expected business conditions.
