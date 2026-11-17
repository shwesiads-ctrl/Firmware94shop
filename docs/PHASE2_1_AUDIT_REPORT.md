# Phase 2.1 — Full Repository Audit Report

**Repository:** `shwesiads-ctrl/firmware94shop`, branch audited: `main` @ `eff1283`
**Audit branch:** `audit/phase-2.1`
**Method:** static code review (file-by-file reading of every app/, database/,
routes/, config/, bootstrap/, and tests/ file) plus `php -l` syntax verification
across all 145 PHP files. See §0 for why execution-based verification (boot,
migrate, test run, npm build) could not be performed, and `docs/
PHASE2_1_TEST_REPORT.md` for the precise, itemized status of every command the
master prompt asked for.

## 0. Environment status (re-verified at the start of this audit)

Re-checked, not assumed, before starting:

```
$ curl https://repo.packagist.org/packages.json
CONNECT tunnel failed, response 403
$ curl https://registry.npmjs.org/
Could not resolve host
$ php -v / composer -V / node -v / npm -v   → all present (PHP 8.3.6, Composer 2.8.12, Node 22, npm 10.9)
```

Same result as the Phase 2 build: PHP/Composer/Node/npm binaries are available,
but there is no route to Packagist or the npm registry from this sandbox, so
`vendor/` and `node_modules/` cannot be created. **Everything in stages B, C, and
K that requires a booted application or installed dependencies is BLOCKED by
environment, not by a code defect**, and is reported as such rather than
guessed at.

## A. Repository & Architecture Audit — status: **PASS (static)**

- `composer.json` — valid JSON, PSR-4 autoload map (`App\` → `app/`,
  `Database\Factories\`/`Database\Seeders\`) matches actual directory
  structure. Dependency versions are plausible Laravel 12-era constraints;
  **not verified against the real registry** (see §0) — `composer validate`
  itself could not be run.
- `package.json` — valid JSON; Vite + Tailwind + Alpine, matches
  `vite.config.js`/`tailwind.config.js`/`resources/js/app.js` references.
- `bootstrap/app.php` — routing, middleware groups, and exception JSON
  rendering for `api/*` are all wired consistently with what `routes/api.php`
  and the middleware classes expect. Middleware aliases (`role`,
  `api.ip_allowlist`, `api.rate_limit`, `f94.resolve_credential`) all resolve
  to classes that exist in `app/Http/Middleware/`.
- `bootstrap/providers.php` — all 4 listed providers exist and their `boot()`/
  `register()` methods reference classes that exist (`SupplierManager`,
  Livewire component classes, Policy classes).
- No duplicated business logic found between controllers/services — wallet
  mutation logic lives only in `WalletService`, order logic only in
  `OrderEngine`, consistent with `docs/ARCHITECTURE.md`'s stated design.
- No dead/orphaned files found (every migration, model, controller, and view
  referenced from this audit is reachable from `routes/` or a seeder).

## B. Dependency Installation — status: **BLOCKED (environment)**

`composer install` / `npm install` / `composer validate` / `npm audit` all
require network access to a package registry, which is unavailable here (§0).
**Not run.** `composer.json`/`package.json` were reviewed by inspection only
(§A). This is the same limitation recorded in `docs/PHASE2_REPORT.md` for the
original Phase 2 build — it has not changed.

## C. Laravel Boot Verification — status: **BLOCKED (environment)**

No `vendor/autoload.php` exists, so `php artisan` cannot run at all — not
`php artisan about`, not `route:list`, nothing. Routes, middleware, and service
bindings were instead traced by hand (§A) rather than confirmed at runtime.

## D. Database & Migration Audit — status: **PASS (static), NOT VERIFIED (execution)**

Reviewed all 12 migration files in `database/migrations/`:

- Ordering is correct for every foreign key: `users` → `wallets`/
  `reseller_profiles`/`api_credentials` → `orders` → `order_inputs`/
  `order_events`/`supplier_orders`, `suppliers` → `supplier_service_mappings`,
  etc. No migration references a table defined in a later-numbered file.
- Every `foreignId(...)->constrained(...)` call names an existing target
  table/column.
- Unique constraints present where expected: `users.email`,
  `wallets.user_id`, `orders.order_number`, `orders.idempotency_key`,
  `wallet_transactions.idempotency_key`, `api_credentials.public_key`,
  `suppliers.slug`, `permissions`/`roles` composite uniques.
- Money columns are `decimal`, never `float`/`double` — verified across all
  12 files, zero exceptions found.
- No duplicate or conflicting migrations found.

**Could not be verified:** actually running `php artisan migrate:fresh
--env=testing`, a rollback, or exercising this against a live MySQL/MariaDB
instance — all BLOCKED by §0. The schema was read, not executed.

## E. Wallet & Financial Security Audit — status: **FAIL → FIXED (see bug fix log)**

This is where the audit found its most serious issues. Full detail, root
cause, and fix for each is in `docs/PHASE2_1_BUG_FIX_LOG.md` — summarized here:

- **BUG-001 (P0):** `OrderEngine::placeOrder`'s idempotency-key lookup had no
  ownership check, so a caller who reused (or guessed) another user's
  idempotency key received that user's `Order` object back — a cross-user
  information disclosure. **Fixed.**
- **BUG-002 (P1):** `WalletService::debit()`/`credit()` had the identical gap
  one layer down — an idempotency key collision across two different wallets
  would silently return the other wallet's ledger row. **Fixed.**
- **BUG-006 (P1):** `DepositService::approve()`/`reject()` read-then-checked
  the deposit's status *before* taking a row lock, so two concurrent approve
  calls on the same deposit could both pass the "is it still pending" check
  and both credit the wallet — a double credit. **Fixed** with
  `lockForUpdate()` inside the transaction.

What was already correct and is confirmed, not just assumed:
- Every wallet mutation runs inside `DB::transaction()` with
  `Wallet::lockForUpdate()`.
- All arithmetic uses `bcmath` on `decimal(14,4)` columns — no float.
- `wallet_transactions` has no `updated_at`, and no code path anywhere in
  `app/` issues an `UPDATE` or `DELETE` against it. Corrections only ever
  `INSERT` a new offsetting row (`WalletService::reverse()`).
- Negative-balance bypass: `debit()` and `hold()` both compare against
  `availableBalance()` (balance minus locked) via `bccomp` before mutating —
  no path skips this check.

## F. Order Engine Audit — status: **FAIL → FIXED**

- **BUG-001** (above) also applies here — it's the same `OrderEngine` method.
- **BUG-003 (P1)** and **BUG-004 (P2):** `InsufficientBalanceException` and
  `DuplicateOrderException` thrown by the engine were not caught by either
  inbound API controller, so both would have surfaced as an unhandled 500
  instead of a documented error response. **Fixed** — both are now caught and
  converted to a safe 402/409 response (or, for `DuplicateOrderException`, the
  already-committed order from the winning request is returned).
- Confirmed correct: a supplier timeout/ambiguous response moves the order to
  `STATUS_UNKNOWN` and **never** triggers an automatic refund — refund only
  happens on a *confirmed* `failed`/`not_supported` result
  (`OrderEngine::applySupplierResult`). This was the single most emphasized
  requirement in the audit prompt and it holds up under review.
- Idempotency at the database level (`orders.idempotency_key` unique) backs
  up the application-level check even if the application-level logic were
  ever bypassed.

## G. Supplier API Compatibility — status: **PASS (mocks), NOT VERIFIED (live), by design**

- All 4 mock adapters (`MockDhruClassicSupplier`, `MockGsmThemeSupplier`,
  `MockFusionProSupplier`, `MockGsmssSupplier`) implement every
  `SupplierAdapter` method, return the uniform `SupplierResult` envelope, and
  are deterministic (same input → same output; verified by reading
  `AbstractMockSupplier`'s scenario-mapping logic — no `rand()`/`mt_rand()`/
  time-based branching exists anywhere in that class).
- `MockGsmssSupplier` correctly reports `getBulkOrderStatus` as unsupported
  (`supports()` returns `false`), and `AbstractMockSupplier::guard()` returns
  `SupplierResult::notSupported()` rather than faking a result — matches the
  "never invent capability support" rule.
- All 4 "live" adapter classes (`DhruClassicAdapter`, `GsmThemeAdapter`,
  `FusionProAdapter`, `GsmssAdapter`) throw `RuntimeException` from every
  method with an explicit "implement against real docs" message — correctly
  **not** faking live connectivity. This is unchanged from Phase 2 and is
  intentional, not a defect.
- Comparing against official API documentation (as the audit prompt asks) is
  **NOT VERIFIED** — no such documentation has been supplied to this session
  for any of the 4 suppliers, and inventing a comparison would violate the
  "do not invent API compatibility" rule. This remains an open item for
  whoever has access to the real supplier docs.

## H. Inbound API Security & Compatibility — status: **PASS, with fixes (see bug log)**

- `ResolveApiCredential` hashes-and-compares secrets via `Hash::check`
  (`ApiCredential::verifySecret`) — never a plaintext comparison — and the raw
  secret is never persisted (only `secret_hash`).
- Middleware order on both inbound routes is
  `f94.resolve_credential → api.ip_allowlist → api.rate_limit`, so the
  credential is always resolved before the IP/rate checks that depend on it —
  correct order, verified by reading `routes/api.php`.
- `EnforceApiIpAllowlist`: a credential with configured `api_ip_rules` rows is
  only ever allowed from those IPs; a credential with no rows falls back to
  the global `ip_allowlist_enabled_by_default` config flag. Verified against
  `App\Support\Net\IpMatcher`'s CIDR logic — correct bitmask comparison.
- Usage logging: fixed (BUG-005, P2) — see bug log; it used to silently drop
  log rows if the app booted outside the `artisan`/`public/index.php` entry
  points.
- `InsufficientBalanceException`/`DuplicateOrderException` handling: fixed
  (BUG-003/BUG-004).
- No supplier credentials are ever included in any API response shape
  returned to a reseller — confirmed by reading every `response()->json(...)`
  call in both controllers.

## I. Payment Security — status: **FAIL → FIXED**

- **BUG-006** (above): double-credit race on concurrent deposit approval.
  **Fixed.**
- Confirmed correct, unchanged: `config('firmware94.payments.
  auto_verify_blockchain')` is hard-coded `false`; nothing anywhere credits a
  wallet from a transaction hash or proof image alone — only
  `DepositService::approve()`, called only from an admin-authorized
  controller action (`payments.approve` permission), ever credits.
- Payment reference uniqueness: `transaction_hash` is **not** a unique column
  in the `deposits` migration. This is a genuine gap but was **not** fixed in
  this pass — a user could submit the same real transaction hash twice,
  creating two pending deposits for one real-world payment. An admin
  reviewing both would need to recognize the duplicate hash manually.
  **Recorded as a new finding (not a regression from Phase 2, not yet fixed)**
  — see "Remaining risks" in `docs/PHASE2_1_TEST_REPORT.md`. Fixing it well
  (same hash + different users, refunds, etc.) is a product decision beyond
  "smallest safe fix" scope for this pass.

## J. UI & UX Verification — status: **BLOCKED (environment)**

No browser automation tool is available in this session, and the application
cannot boot (§0/§C) for either server-side rendering or a build to exist to
load. **Not verified.** Blade templates were read for obvious structural
issues (unbalanced `@if`/`@endif`, undefined `$slot`/`@yield` mismatches) and
none were found, but this is not equivalent to seeing the pages render.

## K. Automated Testing — status: see `docs/PHASE2_1_TEST_REPORT.md`

Full command-by-command results are in the dedicated test report rather than
duplicated here.
