# Phase 2.2 — Real Environment Testing & Verification: Final Report

**Branch:** `test/phase-2.2` (from `audit/phase-2.1` @ `d580813`)
**Date:** 2026-11-17

This report answers exactly the questions the master prompt's §17/§18 require, using only the labels it specifies: PASS, FAIL, BLOCKED, PARTIALLY VERIFIED, NOT VERIFIED.

## 1. Overall status: **PARTIALLY VERIFIED**

Per §17's own completion criteria ("Phase 2.2 cannot be declared complete unless: dependencies install successfully, Laravel boots successfully, database migrations succeed, automated tests execute, wallet/order/API tests pass, frontend build succeeds") — **none of those execution-dependent criteria were met**, for one single, well-evidenced reason: this sandbox has no outbound access to Packagist, npm, or any package registry, and no MySQL/MariaDB server, client, or way to provision one. This was re-verified from scratch this phase (not assumed from Phase 2/2.1) with fresh `curl`, `composer install`, `npm install`, and even a `docker pull` attempt — all independently confirmed blocked. **The one thing this phase could and did deliver instead was the deepest static/code-level audit yet, which is how it found and fixed 4 new real bugs (3 of them P0) that two prior "complete" static audits had missed.**

## 2. Critical vulnerabilities found this phase: **4 new (3×P0, 1×P1), plus 2 documented design gaps**

- **BUG-010 (P0):** an exception from a supplier adapter (guaranteed on any "live" supplier today, since all four are intentionally unimplemented stubs) left orders permanently stuck with wallet funds already debited, un-refunded, un-flagged, and — critically — un-reachable by the reconciliation job, since it only queries `STATUS_UNKNOWN`, not `STATUS_SENT_TO_SUPPLIER`.
- **BUG-011 (P0):** the reconciliation job's confirmed-failure branch was dead code (`app(OrderEngine::class);` resolved an instance and did nothing with it) — a customer whose order the supplier explicitly rejected was never refunded by any automated path.
- **BUG-009 (P1):** `WalletService::debit()`/`credit()`/`hold()` had no guard against a non-positive amount; a negative amount silently reversed the mutation's direction while the ledger still recorded the original (now-wrong) type.
- **BUG-015 (P1):** the DHRU-compatible API's order-placement action never checked the `orders.create` scope its REST v1 sibling already enforces — an authorization bypass via the alternate API surface.
- **BUG-007 (P0, environment/packaging):** `ext-bcmath` — required by every wallet and pricing calculation — was never declared in `composer.json` and is absent from this sandbox; now declared, though the extension itself still needs installing wherever this actually deploys.
- **BUG-008 (P1):** the long-flagged `deposits.transaction_hash` uniqueness gap, properly investigated and resolved this phase (normalized, lifecycle-aware dedupe) rather than left open a third time.
- **BUG-012, BUG-014 (P2, documented, not fixed):** two genuine design inconsistencies (an unused DB column driving nothing; scope enforcement that covers one action out of nine) that need a product decision, not a unilateral code change — explained in full in the Bug Fix Log.

## 3. Bugs fixed: **6 of 8 findings fixed (BUG-007/008/009/010/011/015); 2 documented, not fixed (BUG-012/014)**

Full detail, root cause, and exact diff for every one: `docs/PHASE2_2_BUG_FIX_LOG.md`.

## 4. Files changed: **5 modified, 2 new app files, 1 new migration, 1 new test file, 1 modified `composer.json`, 11 new docs**

Full list in `docs/PHASE2_2_CHANGELOG.md`.

## 5. Database changes: **1 new additive migration (BUG-008), NOT EXECUTED**

No destructive command was run against any database this phase — there is no database server reachable in this sandbox to run anything against, destructive or otherwise. `docs/PHASE2_2_DATABASE_REPORT.md` has the full migration-ordering and constraint review (PASS, static) plus the new migration's design rationale.

## 6. Test results: **BLOCKED — 0 of 62 written tests executed**

`php artisan test` cannot run without `vendor/autoload.php`, which does not exist because `composer install` is blocked. This is reported with the exact number (62 written `test()` blocks across 11 files, 12 of them new this phase) and the exact blocking error, in `docs/PHASE2_2_TEST_REPORT.md`. **No test anywhere in this phase or either prior phase has ever actually executed.** Every PASS verdict in every Phase 2.2 report is explicitly a code-reading verdict, labeled as such, never conflated with an execution result.

## 7. API compatibility status: **NOT VERIFIED (unchanged) for full DHRU Classic v6.1 compatibility; PASS (logic) for the security/authorization checklist, with 1 bug found and fixed (BUG-015) and 1 gap documented (BUG-014)**

Full checklist walkthrough: `docs/PHASE2_2_API_TEST_REPORT.md`. No SQL injection, mass-assignment, secret-leakage, or raw-exception-disclosure issue was found anywhere in either inbound API.

## 8. UI verification status: **BLOCKED**

No build artifact exists (`npm run build` fails: `vite: not found`) and the application cannot boot, so there is nothing to point any browser at. `docs/PHASE2_2_UI_TEST_REPORT.md`.

## 9. Remaining issues (highest priority first)

1. **Run this in a real environment with network access** — this is not optional context, it is the literal single biggest risk across all three phases of this project. Nothing has ever executed.
2. Concurrent-load guarantees (wallet debit/credit, deposit double-approval) are reasoned about correctly but **cannot be proven** without real concurrent connections against real MySQL/MariaDB — flagged as the top execution priority in the Deployment Checklist.
3. BUG-012 and BUG-014 need a product decision, not more code, before they can be closed one way or the other.
4. `ext-bcmath` needs to be installed wherever this deploys (now that `composer.json` will correctly demand it).

## 10. Deployment readiness: **NOT READY**

Full checklist, in order, in `docs/PHASE2_2_DEPLOYMENT_CHECKLIST.md`. The short version: install dependencies for the first time ever, run the migrations for the first time ever, run the tests for the first time ever, then — and only then — this project will have its first real signal about whether any of three phases' worth of careful-but-never-executed code actually works.

---

**Everything is on `test/phase-2.2`, pushed to GitHub against `audit/phase-2.1`; `main` was never touched.**
