# API Overview

## A. DHRU Classic-compatible API

`POST /api/index.php`, `action` POST field selects the operation, `username` +
`apiAccessKey` POST fields authenticate (matched against `api_credentials.
public_key` / a hash of the secret — the raw secret is **never** stored).

| action | status |
|---|---|
| `accountinfo` | implemented (mock wallet balance) |
| `imeiservicelist` | implemented |
| `placeimeiorder` | implemented — drives the real `OrderEngine` against mock suppliers |
| `getimeiorder` | implemented |
| `placeimeiorderbulk` | implemented (loops `placeimeiorder`) |
| `getimeiorderbulk` | implemented |
| `serverservicelist` | implemented (same catalog; Phase 3 can split by category) |
| anything else | returns `{"STATUS":"error","ERROR":"Unsupported or undocumented action."}` — never a fabricated success |

Example:
```bash
curl -X POST https://your-host/api/index.php \
  -d action=accountinfo -d username=<public_key> -d apiAccessKey=<secret>
```

## B. Firmware94shop REST API — base path `/api/v1`

Bearer auth: `Authorization: Bearer <public_key>.<secret>` (both issued together,
once, by `ApiCredential::generate()` — see `app/Livewire/Reseller/ApiKeys.php`).

| Method & path | Status |
|---|---|
| `GET /api/v1/account` | implemented |
| `GET /api/v1/services` | implemented, price resolved per-caller via `PricingService` |
| `GET /api/v1/services/{id}` | implemented |
| `POST /api/v1/orders` | implemented — same `OrderEngine`, idempotency key required in body |
| `GET /api/v1/orders` | implemented |
| `GET /api/v1/orders/{id}` | implemented |
| `GET /api/v1/transactions` | implemented |
| `GET /api/v1/wallet` | implemented |
| `POST /api/v1/webhooks/test` | implemented — returns a **simulated** sample payload only, not wired to any real event |

Both API families share:
- **IP allowlisting** (`api_ip_rules`, enforced by `EnforceApiIpAllowlist` — a
  credential with configured rules is only ever callable from those IPs)
- **Rate limiting** (`EnforceApiRateLimit`, per-credential-or-IP, configurable via
  `api_credentials.rate_limit_per_minute` / `F94_API_RATE_LIMIT_PER_MINUTE`)
- **Usage logging** (`api_usage_logs`, written by `LogApiUsage` middleware on
  every request, success or failure)
- **No supplier credentials are ever exposed** to a reseller client — the
  response shapes above never include `supplier_credentials` data.

Neither API fabricates a successful response for an action it cannot actually
perform — see `docs/SECURITY.md`.
