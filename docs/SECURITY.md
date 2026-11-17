# Security

## Implemented in this Phase 2 Foundation

- **CSRF** — Laravel's default `ValidateCsrfToken` middleware (web routes).
- **XSS** — Blade's `{{ }}` escaping used throughout; no raw `{!! !!}` of
  user-controlled input anywhere in the shipped views.
- **SQL injection** — 100% Eloquent/query-builder, zero raw SQL string
  concatenation.
- **Password hashing** — bcrypt via Laravel's `Hash` facade / `'password' =>
  'hashed'` cast; never stored or logged in plaintext.
- **API authentication** — hashed API secrets (`ApiCredential::verifySecret`
  uses `Hash::check`, never a plaintext comparison); the raw secret is shown to
  the reseller exactly once, at generation time, and never persisted.
- **Rate limiting** — `EnforceApiRateLimit` on both inbound API families.
- **IP allowlisting** — `EnforceApiIpAllowlist` + `api_ip_rules`.
- **Secret encryption** — `supplier_credentials.encrypted_payload` uses Laravel's
  `encrypted:array` cast (AES-256 via `APP_KEY`).
- **Audit logging** — append-only `audit_logs` table + `AuditLogger` support
  class; deposit approval/rejection already wired in as an example.
- **Session protection** — session regenerated on login (`$request->session()->
  regenerate()`) and invalidated + token-regenerated on logout.
- **Role authorization** — Spatie roles/permissions + `Gate::before` for
  super-admin + per-route `role:` middleware + model Policies; never UI-only.
- **Financial transaction locking** — `WalletService` uses `lockForUpdate()`
  inside DB transactions for every balance mutation.
- **Input validation** — Laravel `Validator`/Form validation on every
  user-facing and API entry point.
- **Secure file upload** — deposit proof images are validated (`image`, 5MB max)
  and stored on a dedicated, non-public `payment_proofs` disk.
- **Safe error responses** — `bootstrap/app.php` forces JSON error rendering for
  `/api/*` and `/api/index.php`; nothing here renders Laravel's debug stack trace
  to an API caller even with `APP_DEBUG=true` (set `APP_DEBUG=false` in
  production regardless).

## Explicitly NOT done yet (do not assume otherwise)

- **Two-Factor Authentication**: the `users` table has `two_factor_secret` /
  `two_factor_confirmed_at` columns reserved, but no TOTP enrollment/challenge UI
  is implemented in Phase 2. Treat this as architecture-only.
- **Signed webhooks / replay protection**: `POST /api/v1/webhooks/test` returns a
  simulated payload only; no outbound webhook delivery, signing, or replay
  protection exists yet because nothing in Phase 2 emits real webhooks.
- **Real supplier credentials**: `supplier_credentials` and the four "live"
  adapter classes are architecture-only — every live adapter method throws
  rather than guessing a request format. Do not set any
  `F94_SUPPLIER_*_MODE=live` until you have implemented that adapter against
  real, official documentation.
- **Blockchain payment verification**: `config('firmware94.payments.
  auto_verify_blockchain')` is hard-coded `false`. A deposit is credited to a
  wallet **only** after an admin calls `DepositService::approve()` — never from a
  transaction hash or screenshot alone.
- **Wallet addresses are never hardcoded** anywhere in the codebase — they are
  configured per-environment via `payment_methods.receiving_address` /
  `F94_PAY_USDT_*_ADDRESS`, both of which default to empty.

## Reporting a vulnerability during development

This is an internal Phase 2 build; there is no public disclosure program yet.
Flag findings directly to the project owner.
