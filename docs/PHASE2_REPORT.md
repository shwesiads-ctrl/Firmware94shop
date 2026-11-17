# Phase 2 Foundation — Status Report

Status legend: **IMPLEMENTED** (code written) · **TESTED** (a test exists) ·
**PASSED** (that test was actually run and passed) · **FAILED** · **BLOCKED** ·
**NOT IMPLEMENTED**.

## 0. Why nothing is marked PASSED

This repository was built from scratch (the GitHub repo had only a `LICENSE` and
an empty `README.md`) inside a cloud workspace with **no outbound network
access** — `repo.packagist.org` returned HTTP 403 from the environment's proxy
and the npm registry did not resolve. Every package install (`composer install`,
`npm install`) was therefore impossible, which means:

- There is no `vendor/` directory, so `php artisan` cannot run at all.
- Nothing could be migrated, seeded, booted, or tested.
- `php artisan test`, `php artisan route:list`, `php artisan about`, `npm run
  build` — **none of these were run.** Anything that looks like a result from
  them elsewhere in this repo would be fabricated, which the master prompt
  explicitly forbids ("Do not claim a test passed unless it was executed
  successfully"), so this report does not do that.

Every item below is scored only as **IMPLEMENTED**/**TESTED**/**NOT
IMPLEMENTED**/**BLOCKED** — never PASSED, because nothing has run.

## 1. What was implemented

| Module | Status |
|---|---|
| Laravel foundation (composer.json, bootstrap, config, directory structure) | IMPLEMENTED |
| Database migrations (39 tables, §5 Module B) | IMPLEMENTED |
| Models, factories, seeders | IMPLEMENTED |
| Authentication (register/login/logout/reset/verify, login history) | IMPLEMENTED |
| 2FA architecture (columns reserved, no enrollment UI) | NOT IMPLEMENTED (by design — see SECURITY.md) |
| Roles & permissions (6 roles, Spatie package, Gate::before for super-admin) | IMPLEMENTED |
| Supplier adapter contract + 4 mock adapters + 4 live stubs | IMPLEMENTED |
| Mock order engine (11-step workflow, idempotency, unknown-state handling) | IMPLEMENTED |
| Wallet ledger (immutable, bcmath, locking, holds, reversal) | IMPLEMENTED |
| DHRU Classic-compatible inbound API | IMPLEMENTED |
| Firmware94shop REST v1 inbound API | IMPLEMENTED |
| Three UI layouts (Admin/Customer/Reseller) + Livewire components | IMPLEMENTED |
| English + Myanmar localization | IMPLEMENTED (core strings; not every string in the app is translated yet) |
| Payment foundation (manual + USDT TRC20/BEP20, admin approval) | IMPLEMENTED |
| Automated tests (Pest) | IMPLEMENTED, **NOT EXECUTED** |
| Documentation set | IMPLEMENTED |

## 2. Actual project folder structure

See the tree in `docs/ARCHITECTURE.md` — it matches what's actually on disk in
this repository (verified by listing, not assumed).

## 3. Database tables created

39 tables, listed exhaustively in `docs/DATABASE.md`. **Status: IMPLEMENTED
(migration files written), NOT TESTED (migrations have never been run against
any database).**

## 4. Routes created

All routes are defined in `routes/web.php` and `routes/api.php`. **`php artisan
route:list` was never run** (no `vendor/`), so this report cannot paste its
output — read the route files directly instead; they are short and explicit.

## 5. Authentication and roles implemented

See §1. Super Admin can only be created via `php artisan f94:install-admin` —
there is no public admin registration route anywhere in `routes/web.php`.

## 6. Admin / Customer / Reseller UI screenshots

**NOT PRODUCED.** Taking a screenshot requires a booted, running application,
which requires `vendor/` and a database — neither exists in this environment.
Once you run `docs/INSTALLATION.md`, the three layouts are at `/admin`,
`/account`, and `/reseller`.

## 7. Mock API test results

**NOT RUN.** `tests/Feature/Api/DhruCompatibleApiTest.php` and
`RestV1ApiTest.php` exist and describe expected behavior; they have not been
executed. See `docs/TESTING.md`.

## 8. Automated test results

**NOT RUN.** 9 test files, ~40 individual test cases, covering auth, database
integrity, the wallet ledger, the order engine, all 4 mock suppliers, both
inbound APIs, and pricing math. See `docs/TESTING.md` for the full index. None
have been executed — there is no pass/fail result to report yet.

## 9. Frontend build result

**NOT RUN.** `npm install`/`npm run build` require the npm registry, which was
unreachable. `package.json`, `vite.config.js`, and `tailwind.config.js` are all
in place and ready to run once you have network access.

## 10. Security review findings

See `docs/SECURITY.md` for the full breakdown of what's enforced vs.
deliberately deferred. No live penetration testing was performed (nor would it
mean anything against code that has never booted).

## 11. Known limitations

- **Nothing in this repository has been executed.** This is the single most
  important caveat — treat every file as a first draft that compiles by
  inspection, not by a passing test run.
- Live supplier adapters (`DhruClassicAdapter`, `GsmThemeAdapter`,
  `FusionProAdapter`, `GsmssAdapter`) are interface-only stubs that throw —
  intentional, pending real documentation from the project owner.
- Only a sample service catalog is seeded; the real catalog needs to be entered
  through an admin UI that Phase 3 would build out.
- Myanmar translation covers navigation/auth/validation strings, not literally
  every string in every view.
- 2FA, signed webhooks, and real blockchain payment verification are explicitly
  out of scope (see SECURITY.md).

## 12. Exact commands to run locally

See `docs/INSTALLATION.md` in full; summary:

```bash
composer install
cp .env.example .env && php artisan key:generate
# edit .env: DB_*, REDIS_*
php artisan migrate --seed
php artisan f94:install-admin
npm install && npm run build
php artisan serve
php artisan test
```

## 13. Exact next steps for Phase 3

1. Run the install steps above in an environment with real network access;
   fix whatever the first `composer install` / `php artisan test` run surfaces
   (some class/package-version mismatch is likely on a first real run of
   hand-written code — review especially `composer.json` version constraints).
2. Once green, replace the four live supplier adapter stubs with real
   implementations against official DHRU Classic v6.1 / GSM Theme / Fusion Pro /
   GSMSS documentation, behind the existing `F94_SUPPLIER_*_MODE=live` flags.
3. Build out the admin CRUD screens the sidebar currently lists as placeholders
   (Customers, Resellers, full Suppliers/Services/Pricing management, Reports,
   Vouchers, Subscriptions).
4. Implement real on-chain verification for USDT deposits, or keep manual
   admin review as the permanent policy — this is a product decision, not a
   technical blocker.
5. Add 2FA enrollment/challenge UI using the already-reserved `users` columns.
