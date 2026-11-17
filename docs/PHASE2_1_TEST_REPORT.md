# Phase 2.1 — Test Report

## Environment

- Sandbox: cloud container, no outbound network access to Packagist or the
  npm registry (re-verified at the start of this audit — see
  `docs/PHASE2_1_AUDIT_REPORT.md` §0). PHP 8.3.6 / Composer 2.8.12 / Node 22 /
  npm 10.9 binaries are present; `vendor/` and `node_modules/` cannot be
  created here.
- No disposable MySQL/MariaDB instance was available in this sandbox either
  (confirmed in the original Phase 2 build: `mysql --version` → "no mysql
  client"); SQLite `:memory:` is configured in `phpunit.xml` for exactly this
  reason, but it still requires `vendor/autoload.php` to run at all.

## Commands requested by the master prompt (§3.K) — exact results

| Command | Result |
|---|---|
| `composer validate` | **BLOCKED** — no `vendor/`, and `composer validate` itself needs to resolve the lock file against the registry for a full check; not run |
| `php artisan about` | **BLOCKED** — no `vendor/autoload.php`, `artisan` cannot execute |
| `php artisan route:list` | **BLOCKED** — same reason |
| `php artisan migrate:fresh --env=testing` | **BLOCKED** — same reason |
| `php artisan test` | **BLOCKED** — same reason |
| `npm run build` | **BLOCKED** — no `node_modules/` (npm registry unreachable) |

None of these were run. None of this report claims otherwise. This matches
the original Phase 2 build's limitation exactly — nothing changed about the
sandbox's network access between that build and this audit.

## What WAS actually executed

`php -l` (PHP's own syntax linter, which needs no dependencies) against every
PHP file in the repository, before and after the Phase 2.1 fixes:

```
$ find app database routes bootstrap config tests -name "*.php" | wc -l
145
$ find app database routes bootstrap config tests -name "*.php" -print0 \
    | xargs -0 -n1 php -l 2>&1 | grep -c "No syntax errors detected"
145
```

**145/145 files pass `php -l` with zero syntax errors**, including all 6 files
touched by the Phase 2.1 bug fixes and the 2 new files (`WalletOwnershipException.php`,
`tests/Feature/Security/AuditPhase21RegressionTest.php`). This confirms the
code is well-formed PHP; it does **not** confirm it boots, autoloads
correctly, or behaves as intended at runtime — only an actual `php artisan
test` run (by someone with real network access) can confirm that.

## Test suite inventory (written this pass + inherited from Phase 2)

| File | New in 2.1? | Status |
|---|---|---|
| `tests/Feature/Security/AuditPhase21RegressionTest.php` | **Yes** | Written, **NOT EXECUTED** |
| `tests/Feature/Auth/RegistrationAndLoginTest.php` | No (Phase 2) | Written, **NOT EXECUTED** |
| `tests/Feature/Auth/RolesAndPermissionsTest.php` | No | Written, **NOT EXECUTED** |
| `tests/Feature/Database/SchemaIntegrityTest.php` | No | Written, **NOT EXECUTED** |
| `tests/Feature/Wallet/WalletLedgerTest.php` | No | Written, **NOT EXECUTED** |
| `tests/Feature/Orders/OrderEngineTest.php` | No | Written, **NOT EXECUTED** |
| `tests/Feature/Suppliers/MockSupplierTest.php` | No | Written, **NOT EXECUTED** |
| `tests/Feature/Api/DhruCompatibleApiTest.php` | No | Written, **NOT EXECUTED** |
| `tests/Feature/Api/RestV1ApiTest.php` | No | Written, **NOT EXECUTED** |
| `tests/Unit/PricingServiceTest.php` | No | Written, **NOT EXECUTED** |

No test anywhere in this repository, in this build or the Phase 2.1 audit, has
ever actually been run. Every "regression test" referenced in
`docs/PHASE2_1_BUG_FIX_LOG.md` is a prediction of correct behavior traced by
hand against the fixed code, not a confirmed pass.

### A note on BUG-005's regression test specifically

The real failure mode for BUG-005 requires the `LARAVEL_START` constant to be
*literally undefined* in the PHP process — which only happens outside the
`artisan`/`public/index.php` entry points. A Pest test itself runs inside
whatever bootstrap invoked it, so it cannot force that constant to be
undefined from within a test case. The written regression test instead pins
the externally-observable behavior the fix guarantees (a usage-log row is
written for a normal authenticated call) rather than the internal mechanism —
true "did this specific constant-undefined path regress" coverage would need
an out-of-process check (e.g., a tiny standalone PHP script invoking the
middleware directly), which is out of scope for a Pest HTTP test and was not
added.

## PASS / FAIL / BLOCKED / NOT VERIFIED — summary

| Area | Status |
|---|---|
| Static syntax validity (`php -l`, 145/145 files) | **PASS** |
| Static architecture/schema review | **PASS** |
| `composer validate` / `npm audit` | **BLOCKED** |
| Laravel boot / route registration / `artisan about` | **BLOCKED** |
| Database migration execution | **BLOCKED** |
| Full automated test suite execution | **BLOCKED** |
| Frontend build (`npm run build`) | **BLOCKED** |
| UI/browser verification | **BLOCKED** |
| Wallet/order engine logic (by code trace) | **PASS** (post-fix) |
| Live supplier API compatibility | **NOT VERIFIED** (no official docs supplied; mocks only) |

## Remaining risks (carried forward, not fixed this pass)

1. **`deposits.transaction_hash` is not unique** — see
   `docs/PHASE2_1_BUG_FIX_LOG.md` "Not fixed in this pass."
2. **Nothing in this repository, across two build passes, has ever actually
   executed.** This is the single largest residual risk. A first real
   `composer install && php artisan test` run in an environment with normal
   network access should be treated as the true "first contact" with this
   code, and is very likely to surface issues neither static review nor
   `php -l` can catch (version mismatches, missing `use` statements that
   happen to resolve to the wrong class, Livewire/Blade runtime errors, etc.).
