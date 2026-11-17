# Database

39 tables across 12 migration files (grouped by related tables, in FK dependency
order — see filenames under `database/migrations/`).

## ERD (textual — see note below on diagram tooling)

```
users 1───1 wallets 1───* wallet_transactions
users 1───1 reseller_profiles *───1 reseller_groups
users 1───* api_credentials 1───* api_ip_rules
users 1───* orders *───1 services *───1 service_categories
orders 1───* order_inputs
orders 1───* order_events
orders 1───* supplier_orders *───1 suppliers
services 1───* service_fields
services 1───* supplier_service_mappings *───1 suppliers
services 1───* pricing_rules
services 1───* reseller_prices *───1 reseller_groups|reseller_profiles
suppliers 1───1 supplier_credentials
suppliers 1───* supplier_capabilities
suppliers 1───* supplier_connection_logs
users 1───* deposits *───1 payment_methods
deposits 1───* payment_callbacks
users 1───* support_tickets 1───* support_ticket_replies
```

No diagram-rendering tool was available in the build sandbox (no network to fetch
one, no GUI). Paste the block above into any text-to-ERD tool (e.g. Mermaid's
`erDiagram`, dbdiagram.io) to get a visual version — the relationships are listed
exhaustively in the model files under `app/Models/`.

## Design decisions worth flagging

- **Money is always `DECIMAL`, never `FLOAT`/`DOUBLE`.** `services.base_price`,
  every `wallet*` amount column, `pricing_rules.adjustment_value`, etc. are all
  `decimal(12,4)` or `decimal(14,4)`. Application code does arithmetic on them as
  strings via `bcmath`, never casts to PHP float.
- **`wallet_transactions` is an append-only ledger.** No `updated_at` column, no
  code path that updates or deletes a row. See `docs/ARCHITECTURE.md`.
- **`spatie/laravel-permission`'s schema is included directly** in
  `2025_01_01_000400_create_permission_tables.php` rather than relying on
  `vendor:publish`, so the schema is reviewable in this repo before `composer
  install` ever runs. If you regenerate it via the package's own publish command
  instead, delete this migration file first to avoid a duplicate-table conflict.
- **`api_tokens` (separate from `api_credentials`) was deliberately not created.**
  The master prompt lists both `api_credentials` and "`api_tokens` where
  required"; Laravel Sanctum's own `personal_access_tokens` table (added by the
  Sanctum package migration) already covers token-based auth for any session/SPA
  use case, so a second bespoke table would just duplicate it. `api_credentials`
  covers the DHRU-compatible + REST v1 API-key use case instead.
- **`user_sessions`** from the spec is split into two concerns that are usually
  conflated: Laravel's own `sessions` table (used by the `database` session
  driver) and a purpose-built, explicit `user_login_histories` table for actual
  login-history reporting (IP, user agent, success/failure) — the latter is what
  the Admin "Security"/"Audit Logs" screens will read from in Phase 3.
- **`audit_logs` and `order_events`** are both append-only, JSON-context event
  logs — intentionally not normalized further, since their whole purpose is a
  flexible, queryable timeline rather than a strongly-typed table per event type.
