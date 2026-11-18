# Firmware94shop Laravel security changes (2026-11-18)

This GitHub repository is **Laravel 12**. The earlier ZIP and 22-file security patch are a **different plain-PHP project** and are not safe to copy into this repository. This branch ports only applicable safeguards into Laravel.

## Changes
- Lock deposits before approval/rejection; status and wallet credit share a transaction.
- Admin/super-admin only for financial deposit review; staff no longer gets payments.approve via the permission seeder.
- Login limit: five failures per email+IP in 15 minutes.
- Wallet transactions reject non-positive amounts and wrong replay wallet/type/amount/reference; lock wallet before replay validation.
- Order key replays validate owner, service, inputs, and channel and never re-dispatch.
- Mismatched wallet currency blocks charging; there is no guessed FX conversion.
- Supplier exceptions and uncertain results go to UNKNOWN, never automatic refund/retry. Confirmed failure refunds once transactionally, including reconciliation.
- Zero-cost services skip ledger mutations.
- New Pest tests and GitHub Actions CI.

## Not included and NOT verified
No Laravel S-PayWay callback exists to receive the legacy plain-PHP gateway patch. The Laravel application has Deposits, not the legacy Invoice system. Real gateway/supplier integration, secret encryption, actual MariaDB multi-connection race tests and production HTTP flows remain **unverified**. This branch must **not be treated as a production-ready payment release**.

## Deployment
Work on staging first. Back up code, DB and uploads. The code changes no schema or customer data.
After CI passes, **review before merging**. Existing databases may retain staff role permissions: after validation run `php artisan db:seed --class=RolePermissionSeeder`, inspect any directly assigned permissions, then confirm staff cannot approve deposits. Require bcmath and verify the application's currency matches wallets; mismatched requests will now fail safely.
