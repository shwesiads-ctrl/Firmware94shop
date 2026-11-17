# Phase 2.2 — API Verification Report

**Status: FAIL (1 new P1 fixed) / PASS (static security review) / NOT EXECUTED (no test runner)**

Both inbound APIs (`app/Api/Inbound/DhruCompatible/DhruCompatibleController.php`, all 6 classes under `app/Api/Inbound/RestV1/`), their shared middleware (`ResolveApiCredential`, `EnforceApiIpAllowlist`, `EnforceApiRateLimit`, `LogApiUsage`), and the IP-matching helper (`App\Support\Net\IpMatcher`) were re-read in full against every item in the master prompt's §10 checklist.

## 1. DHRU Classic API (`/api/index.php`)

| Check | Verdict |
|---|---|
| Authentication | PASS (logic) — `ResolveApiCredential` verifies `public_key` + hashed secret (`Hash::check`), rejects inactive credentials and suspended users, never re-exposes the secret. |
| Account information | PASS (logic) — `accountinfo` returns balance/currency only, scoped to the authenticated credential's own user via `$credential->user->wallet`. |
| Service list | PASS (logic) — `imeiservicelist`/`serverservicelist` filter `is_active = true`. |
| Order submission | **FAIL → FIXED (BUG-015, P1)** — see §3 below. |
| Order status | PASS (logic) — `getOrder`/`getOrderBulk` both scope the query by `where('user_id', $credential->user_id)` before matching on id/order_number; a credential cannot fetch another user's order by guessing an ID (confirmed by re-reading, matches "no cross-user access" requirement). |
| Invalid credentials | PASS (logic) — DHRU-shaped `{"STATUS":"error","ERROR":"..."}` 401, no stack trace, no hint whether the public_key or the secret was wrong (timing/information-disclosure hygiene). |
| Missing parameters | PASS (logic) — `$request->validate()` in `placeOrder()`; a missing `action` falls through the `match()`'s `default` arm with a safe 400. |
| Insufficient balance | PASS (logic) — Phase 2.1 BUG-003 fix, re-verified: caught explicitly, documented DHRU error shape, 402. |
| Duplicate order | PASS (logic) — Phase 2.1 BUG-004 fix, re-verified: caught explicitly, returns the existing order's identifiers rather than erroring. |
| Rate limiting | PASS (logic) — `EnforceApiRateLimit`, keyed by credential ID (falls back to IP for unauthenticated attempts), correctly placed in the route middleware stack after credential resolution. |
| IP restrictions | PASS (logic) — `EnforceApiIpAllowlist` + `IpMatcher`: correct CIDR byte/bitmask comparison re-traced by hand for both IPv4 and IPv6 (`inet_pton` length check prevents a v4 rule ever matching a v6 address or vice versa). |
| Error response format | PASS (logic) — every DHRU-path error consistently returns `{"STATUS":"error","ERROR":"..."}"`; no path found that leaks a raw exception message or stack trace to the client (framework's own debug-mode trace page is suppressed by `APP_DEBUG=false` in production, which is an environment/deployment setting, not a code path — documented in the Deployment Checklist). |

**Not claimed:** full DHRU Classic v6.1 compatibility — this was never claimed in Phase 2 or Phase 2.1 either, and remains **NOT VERIFIED** without the official DHRU documentation and real compatible test fixtures, per the master prompt's own instruction not to claim this without them.

## 2. Custom REST API (`/api/v1`)

| Check | Verdict |
|---|---|
| Authentication | PASS (logic) — Bearer `{public_key}.{secret}` parsed and verified identically to the DHRU path, same middleware. |
| Account information | PASS (logic) — `AccountController::show` returns only the authenticated user's own data. |
| Service listing | PASS (logic) — active-only, paginated. |
| Order creation | PASS (logic) — `orders.create` scope enforced (pre-existing), insufficient-balance/duplicate handled (Phase 2.1). |
| Order retrieval | PASS (logic) — `OrderController::show` does `abort_unless($order->user_id === $request->user()->id, 404)` — returns 404 (not 403) for another user's order, which avoids confirming the order ID even exists to an unauthorized caller. Good practice, unchanged. |
| Wallet transactions | PASS (logic) — `TransactionController::index` queries through `$request->user()->wallet->transactions()`, inherently scoped. |
| Unauthorized access | PASS (logic) for cross-user access (every query is scoped by the authenticated user); **see §4 below for a scope-enforcement gap found this phase (BUG-014, documented, not fixed).** |
| Invalid request payload | PASS (logic) — `$request->validate()` used consistently; no endpoint reads unvalidated input into a query or a mutation. |
| Rate limiting | PASS (logic) — same middleware as DHRU path. |
| Idempotency | PASS (logic) — `idempotency_key` required and validated in `OrderController::store()`; cross-user reuse rejected (Phase 2.1 BUG-001). |

## 3. BUG-015 (P1): DHRU `placeimeiorder` never checked the `orders.create` scope, unlike its REST equivalent

**Finding:** `OrderController::store()` (REST v1) has always required `$credential->hasScope('orders.create')` before calling `OrderEngine::placeOrder()`. `DhruCompatibleController::placeOrder()` calls the **exact same** `OrderEngine::placeOrder()` method for the same action, but — until this phase — performed **no scope check at all**. A credential deliberately issued without the `orders.create` scope (e.g. a read-only reporting integration key) could not place an order through `/api/v1/orders` (correctly blocked, 403) but **could** place one through `/api/index.php` with the identical key and no error — a straightforward authorization bypass via the alternate API surface, since both surfaces exist specifically so an existing DHRU-compatible reseller script and a new REST integration can both talk to the same account.

**Severity:** P1 — a real, demonstrable privilege-escalation path for any customer who issues scope-limited keys and assumes the scope is enforced consistently regardless of which endpoint is used.

**Fix:** added the identical `hasScope('orders.create')` guard, with the DHRU error shape, to `DhruCompatibleController::placeOrder()` (which `placeOrderBulk()` also goes through, since it calls `placeOrder()` per item). Confirmed this doesn't break `tests/Feature/Api/DhruCompatibleApiTest.php` — that file never exercises `placeimeiorder` and its `beforeEach()` credential has no scopes configured either way.

**Status: FIXED. NOT EXECUTED** (no test runner available this phase; a new test should be added alongside the existing `DhruCompatibleApiTest.php` suite — tracked in the Deployment Checklist as a follow-up, not fabricated here without being able to run it).

## 4. BUG-014 (P2, documented — not fixed): scope enforcement is inconsistent across REST v1 read endpoints

`api_credentials.scopes` is documented (in its own migration comment) as e.g. `["orders.create","orders.read","wallet.read"]`, implying scopes are meant to gate reads as well as the one write action. In fact, **only** `orders.create` (on order creation, both APIs, as of BUG-015's fix) is ever checked anywhere in the codebase. `GET /v1/orders`, `GET /v1/orders/{id}`, `GET /v1/transactions`, `GET /v1/wallet`, and `GET /v1/account` perform **no scope check at all** — any active credential belonging to the user can read all of it, regardless of what scopes it was issued with.

This was **not** fixed this phase, deliberately: `tests/Feature/Api/RestV1ApiTest.php` already creates its test credential with `scopes => ['orders.create']` only and asserts `GET /api/v1/account` and `GET /api/v1/wallet` both return `200 OK` — i.e. the *existing, already-authored* test suite encodes today's "any active credential can read" behavior as the intended contract, not a bug. Changing this now would mean overriding that existing contract (and rewriting its test fixtures) based on a guess about intent, which is exactly the kind of judgment call this phase's own instructions say to flag rather than unilaterally resolve. **Recommendation for the project owner:** either (a) decide read scopes should be enforced too, and update both the code and `RestV1ApiTest.php`'s fixtures together, or (b) narrow the `scopes` column's documented purpose/comment to reflect that only `orders.create` is actually gated today, so a future reader doesn't assume more protection exists than does.

## 5. Security tests (master prompt §10 "Security tests" checklist)

| Check | Verdict |
|---|---|
| No secret leakage | PASS — `secret_hash` is never selected into any API response; `ApiCredential::generate()`'s raw secret is returned once, at creation, and never logged (`LogApiUsage` only records method/path/status/timing, no request/response body). `.env.testing` (this phase) and `.env.example` (Phase 2) both contain zero real credentials. |
| No cross-user access | PASS (logic) — every query in every controller is scoped by the authenticated user/credential; re-verified line by line this phase, no exceptions found. |
| No raw exception disclosure | PASS (logic) — every expected-failure path (validation, insufficient balance, duplicate order, invalid credentials, missing scope) is caught and mapped to a documented JSON shape; relies on `APP_DEBUG=false` in any real deployment for the *unexpected*-exception case, which is an environment setting tracked in the Deployment Checklist, not a code defect. |
| No SQL injection | PASS — every single database query across both inbound APIs and their controllers uses Eloquent's query builder with bound parameters (`where()`, `whereIn()`, `findOrFail()`); zero raw SQL string concatenation found anywhere in `app/Api/`. |
| No mass-assignment vulnerability | PASS — every `Model::create()`/`update()` call in the API layer passes an explicitly-built array of named, validated fields; none passes `$request->all()` or an unfiltered `$validated` superset directly from client input without first being validated field-by-field. |
| No unsafe API logging | PASS — confirmed above; also no secrets appear in `storage/logs` paths referenced anywhere in this codebase. |
| No replay vulnerability where applicable | PASS (logic) — idempotency keys are the explicit replay defense for order creation and are cross-checked for ownership (Phase 2.1 BUG-001/002); there is no equivalent "replay a GET" concern since reads are naturally idempotent and scoped. |

## Summary

| Category | Verdict |
|---|---|
| DHRU Classic API | PASS (logic), 1 bug found and fixed (BUG-015) |
| REST v1 API | PASS (logic), 1 gap documented not fixed (BUG-014) |
| Security checklist (SQLi, mass-assignment, secret leakage, exception disclosure, replay) | PASS (logic), no new findings |
| Full DHRU Classic v6.1 compatibility | NOT VERIFIED (no official docs/fixtures available, unchanged from Phase 2/2.1) |
| Execution of any API test | NOT EXECUTED (no vendor/autoload.php) |
