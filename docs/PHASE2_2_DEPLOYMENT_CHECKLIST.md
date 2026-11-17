# Phase 2.2 — Deployment Checklist

This repository has **never been executed** in any sandbox across Phase 2, 2.1, or 2.2 — every item below exists because of that, not in spite of it. Nothing here should be skipped on the assumption "it's probably fine since the code reads correctly."

## Before anything else: run it, once, anywhere real

1. On a machine with real internet access: `composer install`, `npm install`.
2. Confirm `ext-bcmath` is installed (`php -m | grep bcmath`) — **BUG-007**. If missing, install it (`apt install php8.3-bcmath` or your distro's equivalent) before anything else; every wallet operation will fatal without it.
3. `cp .env.testing .env` (or build a real `.env` from `.env.example`) against a real, disposable MySQL 8 or MariaDB database — never a production or customer database.
4. `php artisan key:generate`
5. `php artisan migrate:fresh --seed` — watch for the first real signal this whole project has ever had: does the schema actually create successfully? Do the seeders run?
6. `php artisan test` — this is the first time any of the ~62 written tests will actually execute. Expect some to fail or need adjustment; that is normal for code that has never run, not a sign anything here was dishonest about it.

## High-value things to specifically verify with real execution (flagged NOT VERIFIED throughout this phase's reports)

- **Concurrent debit/credit and concurrent order submission** against real MySQL with `SELECT ... FOR UPDATE` under genuine parallel connections (not sequential test calls) — this is the single most important thing a static review cannot prove. Suggested approach: a small script spawning several real concurrent HTTP requests or DB connections at the same wallet row.
- **Deposit double-approval** under real concurrency — same caveat, same suggested approach, two processes calling `DepositService::approve()` for the same deposit at the same instant.
- The `deposits.transaction_hash` dedupe migration (BUG-008) — run the duplicate-detection query in `docs/PHASE2_2_DATABASE_REPORT.md` §3 against any existing populated database **before** running that migration.
- `npm run build` — confirm Vite/Tailwind/Alpine actually produce a working asset bundle; this phase could not even start that check.
- Full UI click-through of all three layouts (Admin/Customer/Reseller), auth pages, wallet pages, order pages, EN/MY locale switching — this phase was 100% blocked on this (`docs/PHASE2_2_UI_TEST_REPORT.md`).

## Decisions needed from the project owner (not resolved by this audit on purpose)

- **BUG-012:** should editing a supplier's `mode` column actually change which adapter runs, or should that column be removed/relabeled since only the env var matters today?
- **BUG-014:** should `orders.read`/`wallet.read`/`account.read` scopes actually be enforced on the REST v1 read endpoints? If yes, `tests/Feature/Api/RestV1ApiTest.php`'s fixtures need updating alongside the code change.

## Before flipping any supplier to `live` mode

- All four live adapters (`DhruClassicAdapter`, `GsmThemeAdapter`, `FusionProAdapter`, `GsmssAdapter`) are intentionally unimplemented and will throw on every call until real, documented credentials and API specs are supplied — this is correct, deliberate behavior, not a bug. BUG-010's fix means this will now fail *safely* (order → `STATUS_UNKNOWN`, no stuck funds) rather than catastrophically, but "safely blocked" is not the same as "working" — do not flip a supplier to live until its adapter is actually implemented against real documentation.

## Lower-priority hardening (observations, not bugs — optional follow-up)

- Add `->withoutOverlapping()` to the `ReconcileUnknownSupplierOrders` schedule entry.
- Switch `LOG_CHANNEL` to `daily` (with rotation) for any real deployment; `single` has no rotation.
- Consider a `CHECK` constraint on `reseller_prices` to enforce "exactly one of `reseller_group_id`/`reseller_profile_id` is set."
- Consider an index on `api_usage_logs.created_at` if/when a log-cleanup job is added.

## Branch status

- `test/phase-2.2` is ready to push and open as a PR against `audit/phase-2.1` (not `main`) — this audit builds on, and should merge into, the audit branch first, consistent with how Phase 2.1 related to Phase 2.
- `main` has not been touched by this phase, confirmed via `git log main..test/phase-2.2`.
