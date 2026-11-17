# Phase 2.2 — Wallet, Deposit & Order Security Test Report

**Status: PASS (static/code-level verification) / NOT EXECUTED (no test runner available)**

Every scenario below was verified by hand-tracing the actual source in `app/Services/Wallet/WalletService.php`, `app/Services/Payments/DepositService.php`, and `app/Services/Orders/OrderEngine.php` against the fixed code on this branch, and a corresponding test exists in `tests/Feature/Security/AuditPhase22RegressionTest.php` (new) and `tests/Feature/Wallet/WalletLedgerTest.php` / `tests/Feature/Security/AuditPhase21RegressionTest.php` (pre-existing). **None of these tests were executed** — `php artisan test` cannot run without `vendor/autoload.php` (see `docs/PHASE2_2_DEPENDENCY_REPORT.md`). Every verdict below is a code-reading verdict, reported honestly as such, not a test-run result.

## 1. Wallet security — 14 required scenarios

| # | Scenario | Verdict | Basis |
|---|---|---|---|
| 1 | Valid credit | PASS (logic) | `credit()` adds via `bcadd`, posts ledger row; `WalletLedgerTest`-style coverage exists. |
| 2 | Valid debit | PASS (logic) | `debit()` subtracts via `bcsub` after balance check; covered by `WalletLedgerTest::'a debit reduces the balance...'`. |
| 3 | Insufficient balance | PASS (logic) | `bccomp($available, $amount,4) < 0` throws `InsufficientBalanceException` before any mutation; covered. |
| 4 | Zero amount | **FIXED this phase (BUG-009)** | Previously allowed (posted a no-op ledger row); now rejected by `assertPositiveAmount()`. New test: `AuditPhase22RegressionTest::'BUG-009d'`. |
| 5 | Negative amount | **FIXED this phase (BUG-009, P1)** | Previously **flipped the effective direction of the mutation** — see finding write-up in `docs/PHASE2_2_BUG_FIX_LOG.md`. New tests: `BUG-009a/b/c`. |
| 6 | Decimal precision | PASS (logic) | All arithmetic is `bcmath` string-based to 4 places; no float ever touches a balance. Covered by `AuditPhase22RegressionTest::'decimal precision...'`. Note: this entire guarantee depends on `ext-bcmath` being installed — see BUG-007 (Environment Report). |
| 7 | Duplicate idempotency key | PASS (logic) | Both `debit()`/`credit()` check `WalletTransaction::where('idempotency_key', ...)` first and return the existing row as a no-op; covered by `WalletLedgerTest::'replaying the same idempotency key...'`. |
| 8 | Cross-user idempotency attempt | PASS (logic) | At the `OrderEngine` layer (not wallet layer directly) — Phase 2.1 BUG-001 fix; covered by `AuditPhase21RegressionTest::'BUG-001'`. |
| 9 | Cross-wallet idempotency attempt | PASS (logic) | Phase 2.1 BUG-002 fix: `WalletOwnershipException` thrown when the same key resolves to a different `wallet_id`; covered by `AuditPhase21RegressionTest::'BUG-002'` and re-verified in `AuditPhase22RegressionTest`. |
| 10 | Concurrent debit | NOT VERIFIED (requires real concurrent DB connections) | `Wallet::lockForUpdate()` inside `DB::transaction()` is the correct pessimistic-locking pattern for this under MySQL/MariaDB (`SELECT ... FOR UPDATE` serializes concurrent writers on the same row). SQLite (the only DB this sandbox could ever reach, and only hypothetically) does not support true row-level concurrent locking the same way, so even if tests could run here, this specific guarantee could only be genuinely proven against real MySQL/MariaDB under actual parallel load (e.g. `pest --parallel` hitting the same wallet row from multiple processes, or a dedicated script spawning concurrent DB connections). This is flagged as the single highest-value test to run first in a real environment — see Deployment Checklist. |
| 11 | Concurrent credit | NOT VERIFIED | Same mechanism and same caveat as #10. |
| 12 | Transaction rollback | PASS (logic) | Every mutation is wrapped in `DB::transaction()`; an exception thrown after the balance write (e.g. from `postLedgerRow()` failing a unique-constraint check) rolls back the balance write too, because both happen inside the same transaction closure. Covered conceptually by `WalletLedgerTest::'a debit larger than the available balance...'` (exception before any write) — a test that forces failure *after* the balance write but *before* commit would strengthen this further; not currently present. |
| 13 | Double-credit prevention | PASS (logic) | Covered by the deposit lock-then-check pattern (Phase 2.1 BUG-006) — `Deposit::lockForUpdate()` before the status check means a second concurrent `approve()` call sees `status != pending` and throws, never reaching `wallets->credit()` twice. See §2 below. |
| 14 | Ledger consistency | PASS (logic) | No code path anywhere in `WalletService` performs `UPDATE`/`DELETE` on a posted `wallet_transactions` row; `reverse()` posts a new offsetting row only. Confirmed by re-reading the full file — no `WalletTransaction::where(...)->update(...)` or `->delete()` call exists anywhere in the codebase (`grep` returned zero matches). |

## 2. Deposit double-approval test (required stage 8)

**Mechanism reviewed (not executed):** `DepositService::approve()`/`reject()` both open a `DB::transaction()`, then immediately `Deposit::lockForUpdate()->findOrFail(...)` before checking `status !== STATUS_PENDING`. Under MySQL/MariaDB with `SELECT ... FOR UPDATE`, a second concurrent `approve()` call for the same deposit blocks on the row lock until the first transaction commits, then reads the now-`approved` status and throws `RuntimeException` — it never reaches `$this->wallets->credit(...)`. This is the exact fix from Phase 2.1's BUG-006 and is unchanged this phase (re-verified by re-reading the file; see `docs/PHASE2_2_BUG_FIX_LOG.md` for what *was* changed this phase, which is a different concern — the `transaction_hash` dedupe, BUG-008).

**Expected outcome once this can be run against real MySQL:**
- Only one of two concurrent `approve()` calls succeeds; the other throws `RuntimeException` with a safe, generic message (no stack trace, no SQL fragment).
- Exactly one `wallets->credit()` call occurs, verifiable by `wallet_transactions` having exactly one row with `idempotency_key = "deposit-credit:{id}"` (the key is a no-op replay target even in the hypothetical case both got far enough to call `credit()` — defense in depth on top of the row lock).
- `deposit.status` ends at exactly `approved`, never anything else.
- `AuditLogger::record('deposit.approved', ...)` fires exactly once.

**Status: NOT VERIFIED (requires real concurrent connections against MySQL/MariaDB, unavailable in this sandbox).** Reported honestly as such — this is precisely the class of guarantee a static read can make plausible but never prove.

## 3. `transaction_hash` uniqueness — see `docs/PHASE2_2_DATABASE_REPORT.md` §3 (BUG-008) for the full investigation and the migration proposal. Summary: fixed via a stored generated dedupe column (network+hash, excluding rejected deposits) plus application-layer normalization in `DepositService::submit()`.

## 4. Order engine — see `docs/PHASE2_2_API_TEST_REPORT.md` and `docs/PHASE2_2_BUG_FIX_LOG.md` for order-specific findings (reconciliation, refund safety, supplier-state handling — all re-verified unchanged from Phase 2.1, no new defects found there this phase beyond BUG-009's interaction with the refund path, which is safe — see BUG-009 write-up).

## Summary

| Category | Verdict |
|---|---|
| Wallet scenarios 1–3, 6, 7, 12, 14 | PASS (logic, re-verified) |
| Wallet scenarios 4, 5 | FAIL → FIXED this phase (BUG-009) |
| Wallet scenarios 8, 9 | PASS (logic, Phase 2.1 fix re-verified) |
| Wallet scenarios 10, 11 | NOT VERIFIED (needs real concurrent DB) |
| Wallet scenario 13 / deposit double-approval | PASS (logic) / NOT VERIFIED (needs real concurrent DB) |
| `transaction_hash` dedupe | FIXED (BUG-008) |
