# Phase 2.2 — Database Verification Report

**Status: BLOCKED (execution) / PASS (static schema review)**

## 1. Commands executed

```
$ php artisan migrate:status
$ php artisan migrate:fresh --env=testing
$ php artisan db:seed --env=testing
```

All three fail identically and immediately with the same `vendor/autoload.php` fatal error documented in `docs/PHASE2_2_BOOT_REPORT.md` — Laravel cannot boot without Composer's autoloader, so no migration can run, against any database, real or disposable. **No destructive command was run against any database** (there is no database connection possible at all in this sandbox — no MySQL/MariaDB server exists, see `docs/PHASE2_2_ENVIRONMENT_REPORT.md` §1/§3). `migrate:fresh` was attempted only against `--env=testing`'s in-memory SQLite target, never against anything resembling production, per the master prompt's explicit rule.

## 2. Static schema review (all 13 migration files read in full)

Migration ordering was traced by hand for every foreign key, to confirm no migration references a table that doesn't exist yet at that point in the sequence:

| Migration | References | Target exists earlier? |
|---|---|---|
| `000100_users` | — | n/a (first) |
| `000400_permission` | — | n/a (teams-optional FKs are all internal to this file) |
| `001000_reseller` | `users` | ✅ (000100) |
| `001100_api` | `users` | ✅ |
| `001200_supplier` | `suppliers` (self, same file, created before dependents) | ✅ |
| `001300_catalog` | `service_categories` (self), `suppliers` | ✅ (001200) |
| `001400_pricing` | `services`, `reseller_groups`, `reseller_profiles` | ✅ (001300, 001000) |
| `001500_wallet` | `users` | ✅ |
| `001600_order` | `users`, `services`, `orders` (self), `suppliers` | ✅ |
| `001700_payment` | `users`, `deposits` (self), `payment_methods` (self) | ✅ |
| `001800_system` | `users`, `support_tickets` (self) | ✅ |

**Result: no forward-reference errors found.** `migrate:fresh` would not be expected to fail on ordering grounds (this cannot be confirmed by actually running it — reported as NOT VERIFIED for runtime behavior, PASS for static ordering).

## 3. Unique constraints and indexes — reviewed for correctness

Confirmed present and correctly placed: `users.email`, `wallets.user_id`, `orders.order_number`, `orders.idempotency_key`, `wallet_transactions.idempotency_key`, `api_credentials.public_key`, `services.slug`, `service_categories.slug`, `suppliers.slug`, `reseller_groups.slug`, `payment_methods.code`, `system_settings.key`, `currency_settings.code`, `reseller_profiles.user_id`, `supplier_capabilities(supplier_id, capability)`, `supplier_service_mappings(service_id, supplier_id)`, `reseller_group_members(reseller_group_id, reseller_profile_id)`.

These two global-uniqueness columns are the DB-level backstop behind the Phase 2.1 application-level ownership checks (BUG-001/BUG-002): `orders.idempotency_key` and `wallet_transactions.idempotency_key` are each globally unique, consistent with `OrderEngine`/`WalletService` now additionally checking *ownership* of the row that uniqueness resolves to, rather than relying on uniqueness alone.

### ⚠️ Finding carried forward and resolved this phase — BUG-008 (P1)

`deposits.transaction_hash` (nullable string) has **no uniqueness constraint** — flagged in Phase 2.1 as a deliberately-unfixed, flagged gap. This phase's master prompt (§8) asked for the gap to be investigated properly rather than patched with a blind `unique()` call. Investigation and conclusion:

- **Existing duplicate records:** none can exist yet (no database has ever been populated in either phase); the query to check before applying this in a real environment is documented below.
- **Network/token context:** a transaction hash is only meaningful scoped to the chain it was mined on (TRC20 vs BEP20); a bare `unique()` on the column alone would be semantically wrong even if duplicates happened to be rare in practice.
- **Normalization:** hex hashes can be resubmitted with different case; without normalizing first, a unique index can be trivially defeated by re-casing the same string.
- **Deposit lifecycle:** a rejected deposit (e.g. typo'd hash, rejected by an admin) must not permanently block the correct hash being submitted later — a naive global unique constraint would do exactly that.
- **Database compatibility:** target stack (MySQL 8 / MariaDB) has no native partial/filtered unique index (unlike PostgreSQL), so "unique only among non-rejected rows" needs a portable mechanism.

**Resolution applied:**
1. New additive migration `database/migrations/2026_01_01_000100_add_transaction_hash_dedupe_to_deposits_table.php` adds a **stored generated column** `transaction_hash_dedupe_key` (`CASE WHEN status='rejected' OR transaction_hash IS NULL THEN NULL ELSE LOWER(CONCAT(network,':',transaction_hash)) END`) with a unique index on that column. Generated columns recompute automatically when `status` changes (e.g. on reject), so a hash is freed up the moment its deposit is rejected — no manual bookkeeping needed. This does not touch the original 2025 migration.
2. `DepositService::submit()` now normalizes (`trim` + `strtolower`) the transaction hash before storing it, so the raw column and the generated dedupe key agree, and the normalization isn't solely dependent on the DB expression.

**Pre-flight check to run before applying this migration to any populated database** (not run here — no DB available):
```sql
SELECT network, LOWER(transaction_hash) AS norm_hash, COUNT(*)
FROM deposits
WHERE transaction_hash IS NOT NULL AND status != 'rejected'
GROUP BY network, LOWER(transaction_hash)
HAVING COUNT(*) > 1;
```
Any rows returned must be resolved manually (determine which deposit is the real one) before the migration can run — a stored generated column's unique index will reject the migration outright if duplicates already exist.

**Status: PASS (design reviewed, migration written) / NOT VERIFIED (never executed against a real database).**

### Other static observations (not treated as bugs — no fix applied)

- `reseller_prices` has two nullable FKs (`reseller_group_id`, `reseller_profile_id`) with a comment that exactly one should be set, but nothing at the DB layer enforces "exactly one, not both/neither." `PricingService::priceFor()` queries each column independently and checks the profile-level override first, so a row with both set does not cause incorrect double-matching in the current code path — this is a data-modeling looseness, not an active bug, and is left unfixed per the "do not refactor unrelated code" rule. Recommended for a future CHECK constraint (MySQL 8.0.16+ supports `CHECK`) if someone wants to close it.
- `api_usage_logs` has no index on `created_at`. Stage 13 of this phase asks about "log rotation and cleanup" — at high volume, a cleanup job (`DELETE ... WHERE created_at < ?`) would table-scan. Noted as a P3 performance observation for a future pass, not fixed here (no functional defect, no test would fail because of it).

## Summary

| Check | Status |
|---|---|
| `migrate:status` | BLOCKED |
| `migrate:fresh --env=testing` | BLOCKED |
| `db:seed --env=testing` | BLOCKED |
| Migration ordering / FK forward-reference check | PASS (static) |
| Unique constraint / index review | PASS (static) |
| `deposits.transaction_hash` dedupe (BUG-008) | FIXED (migration + app-layer normalization), NOT VERIFIED (not executed) |
