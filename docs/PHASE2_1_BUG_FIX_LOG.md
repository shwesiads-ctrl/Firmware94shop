# Phase 2.1 — Bug Fix Log

Every entry: bug ID, severity, file(s), root cause, fix applied, regression
test. **"Regression test result" is honest about what could and could not be
executed** — see the note at the top of each section and
`docs/PHASE2_1_TEST_REPORT.md` for why.

---

## BUG-001 — Cross-user order disclosure via idempotency key reuse

- **Severity:** P0 (critical — financial/authorization data exposure)
- **File:** `app/Services/Orders/OrderEngine.php`, lines 58 and 75 (pre-fix)
- **Root cause:** `placeOrder()`'s idempotency replay check,
  `Order::where('idempotency_key', $idempotencyKey)->first()`, matched on the
  key alone. The REST v1 API (`OrderController::store`) accepts an arbitrary
  caller-supplied `idempotency_key` string with no server-side namespacing.
  A second caller who submitted (or simply reused/guessed) the same key as an
  existing order received that order's full data — `price_charged`, `status`,
  `service_id`, `order_number` — regardless of who actually owned it. The
  in-transaction race-recheck a few lines down had the identical gap.
- **Impact:** Any authenticated API caller could potentially read another
  user's or reseller's order details by supplying a guessed or previously
  observed idempotency key. This is exactly the "cross-user idempotency
  protection" / "ownership verification" failure mode the audit was
  specifically commissioned to find.
- **Fix applied:** Both lookups (the pre-transaction check and the
  `lockForUpdate()` race-recheck inside the transaction) now compare
  `$existing->user_id` against the calling user's ID. A mismatch throws
  `ValidationException` ("This idempotency key is already in use by another
  order.") instead of ever returning the other user's `Order`. A match (same
  user replaying their own key) behaves exactly as before.
- **Regression test:** `tests/Feature/Security/AuditPhase21RegressionTest.php`
  — `'BUG-001: a second user cannot retrieve another user's order...'` and the
  companion "regression guard" test confirming same-user replay still works.
  **Written, not executed** — see test report.

## BUG-002 — Cross-wallet ledger disclosure via idempotency key reuse

- **Severity:** P1
- **File:** `app/Services/Wallet/WalletService.php`, `debit()` and `credit()`
- **Root cause:** Identical pattern to BUG-001, one layer down:
  `WalletTransaction::where('idempotency_key', $idempotencyKey)->first()` had
  no check that the matched row belonged to the wallet the caller asked to
  mutate. Every current caller in this codebase namespaces its keys well
  enough (`order-debit:{uuid}`, `deposit-credit:{id}`, etc.) that this should
  never fire in today's call graph — but `WalletService` is the single
  shared choke point for all financial mutations, and a future caller or a
  key-generation bug elsewhere could trigger it silently.
- **Impact:** A key collision across two wallets would silently return the
  wrong wallet's transaction object to the caller instead of failing — a
  latent cross-account ledger disclosure risk, not currently exploitable
  through any existing route, but a real gap in a financial primitive the
  audit was asked to treat as "critical priority."
- **Fix applied:** Both `debit()` and `credit()` now check
  `$existing->wallet_id !== $wallet->id` and throw the new
  `App\Exceptions\WalletOwnershipException` on mismatch, rather than silently
  returning the other wallet's row. Same-wallet replay (the actual, intended
  use case) is unchanged.
- **Regression test:** same file — `'BUG-002: replaying an idempotency key
  against a different wallet...'` plus a same-wallet regression guard.
  **Written, not executed.**

## BUG-003 — `InsufficientBalanceException` unhandled in both inbound APIs

- **Severity:** P1
- **Files:** `app/Api/Inbound/RestV1/OrderController.php`,
  `app/Api/Inbound/DhruCompatible/DhruCompatibleController.php`
- **Root cause:** `OrderEngine::placeOrder()` throws
  `InsufficientBalanceException` (via `WalletService::debit()`) when the
  caller's balance is too low — an extremely common, entirely expected
  outcome. Both controllers' `try`/`catch` blocks caught only
  `ValidationException`, so this exception propagated unhandled into
  Laravel's default exception renderer: an undocumented, generic error
  response instead of the clean 4xx the audit calls for ("safe error
  responses").
- **Impact:** Every reseller/customer integration calling either inbound API
  with insufficient balance got an undocumented failure shape instead of a
  predictable one — a real integration-compatibility bug, not just a
  cosmetic one.
- **Fix applied:** Both controllers now also catch
  `InsufficientBalanceException` and return a clean `402` (REST v1:
  `{"message": "Insufficient wallet balance."}`; DHRU-compatible:
  `{"STATUS":"error","ERROR":"Insufficient wallet balance."}`).
- **Regression test:** `'BUG-003: the REST v1 API returns a clean 402...'`
  and the DHRU-compatible equivalent. **Written, not executed.**

## BUG-004 — `DuplicateOrderException` unhandled in both inbound APIs

- **Severity:** P2
- **Files:** same two controllers as BUG-003
- **Root cause:** The same gap as BUG-003, for the other exception
  `OrderEngine` can throw: two genuinely concurrent requests with the same
  (same-user) idempotency key can race past the pre-transaction check and
  collide at the `lockForUpdate()` recheck, where the loser gets
  `DuplicateOrderException`. Neither controller caught it.
- **Impact:** A legitimate retry race (e.g., a client's HTTP timeout-and-retry
  logic) would surface as an unhandled 500 instead of the caller simply
  getting back the order the other, successful request already created.
- **Fix applied:** Both controllers now catch `DuplicateOrderException`,
  re-query the now-committed order by `idempotency_key` + the caller's own
  `user_id`, and return it normally (201) — falling back to a `409` only if
  that re-query somehow finds nothing.
- **Regression test:** `'BUG-004: a direct call that hits the in-transaction
  duplicate check is handled...'` — exercises the exact code path the
  controllers' new `catch` blocks are written against. **Written, not
  executed.**

## BUG-005 — `LogApiUsage` silently drops rows outside the `artisan` bootstrap

- **Severity:** P2
- **File:** `app/Http/Middleware/LogApiUsage.php`
- **Root cause:** The middleware read the global `LARAVEL_START` constant
  directly inside its own `try`/`catch(\Throwable)` block. That constant is
  only ever defined by the `artisan` CLI script and `public/index.php`. Any
  other process entry point — notably running the test suite via
  `vendor/bin/pest` directly rather than `php artisan test` — leaves it
  undefined, which throws an `Error` that the surrounding `catch` swallows and
  merely `report()`s, silently skipping the `ApiUsageLog::create()` call.
- **Impact:** API usage auditing (a control the audit explicitly asked to
  verify) would silently stop recording rows depending on how the process
  was started — exactly the kind of "fails open and nobody notices" bug that
  defeats the purpose of an audit log.
- **Fix applied:** The middleware now captures
  `defined('LARAVEL_START') ? LARAVEL_START : microtime(true)` into a local
  variable at the top of `handle()`, before calling `$next($request)`, and
  uses that local value for the duration calculation — no more dependency on
  a constant that may not exist.
- **Regression test:** `'BUG-005: API usage is still logged when LARAVEL_START
  is not defined...'`. **Written, not executed** — see test report for why a
  fully faithful reproduction (actually undefining a PHP constant mid-run) is
  not possible from inside a test, and what this test does instead.

## BUG-006 — Deposit double-approval race → possible double wallet credit

- **Severity:** P1
- **File:** `app/Services/Payments/DepositService.php`,
  `approve()` and `reject()`
- **Root cause:** Both methods checked `$deposit->status !== STATUS_PENDING`
  *before* opening a database transaction or taking any row lock. Two
  concurrent `approve()` calls for the same deposit (an admin double-clicking
  "Approve", or any retried request) could both read `status === 'pending'`
  before either one's `UPDATE` committed, and both would then proceed to
  credit the wallet — a double credit for a single real-world deposit. This
  is precisely the "wallet credit atomicity" / "duplicate deposit protection"
  failure mode §I of the audit asks to verify.
- **Impact:** Financial — a user's wallet could be credited twice for one
  deposit under concurrent admin action, with no application-level guard
  against it (the database has no unique constraint that would have caught
  this either, since each approval posts a new, distinctly-keyed wallet
  transaction by design).
- **Fix applied:** Both `approve()` and `reject()` now run their entire body,
  including the pending-status check, inside `DB::transaction()` after
  `Deposit::lockForUpdate()->findOrFail($deposit->id)` — so the second
  concurrent caller blocks until the first commits, then sees the
  already-approved/-rejected status and is rejected with the same
  `RuntimeException` as before (just now race-safe).
- **Regression test:** `'BUG-006: a deposit cannot be approved twice...'` and
  `'...a rejected deposit cannot later be approved'`. **Written, not
  executed.**

---

## Not fixed in this pass (recorded, not actioned)

- **`deposits.transaction_hash` has no uniqueness constraint** — a user could
  submit the same real transaction hash in two separate deposit requests,
  creating two pending rows for one real payment. Flagged in
  `docs/PHASE2_1_AUDIT_REPORT.md` §I. Not fixed here because the correct
  behavior (reject outright? flag for review? scope uniqueness per
  network+hash rather than globally?) is a product decision, and the bug fix
  policy for this pass is "smallest safe fix," not a design change made
  without that direction.
