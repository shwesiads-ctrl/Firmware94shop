# Architecture

## Layout

```
app/
  Core/                     domain namespaces reserved for Phase 3 (Catalog, Orders, etc.)
  Integrations/Suppliers/   SupplierAdapter contract + 4 live stubs + 4 deterministic mocks
  Api/Inbound/              DhruCompatible/ and RestV1/ controllers for the two inbound APIs
  Services/
    Orders/OrderEngine.php  the 11-step internal order workflow
    Wallet/WalletService.php immutable-ledger wallet mutations
    Pricing/PricingService.php role/reseller-group/override price resolution
    Payments/DepositService.php manual/USDT deposit review workflow
  Livewire/{Admin,Customer,Reseller,Storefront}/   per-layout interactive components
  Policies/, Http/Middleware/, Console/Commands/
resources/views/layouts/{admin,customer,reseller,guest}.blade.php   three independent UIs
resources/lang/{en,my}/      bilingual UI strings
database/migrations/         one file per related table group, in FK dependency order
tests/{Feature,Unit}/        Pest tests (see TESTING.md — written, not yet executed)
```

## Supplier integration pattern

Every supplier (DHRU Classic, GSM Theme, Fusion Pro, GSMSS) implements the same
`App\Integrations\Suppliers\Contracts\SupplierAdapter` interface:

```php
testConnection(): SupplierResult
getAccountInfo(): SupplierResult
getServices(): SupplierResult
getServiceDetails(string $ref): SupplierResult
placeOrder(array $payload): SupplierResult
getOrderStatus(string $ref): SupplierResult
getBulkOrderStatus(array $refs): SupplierResult
supports(string $capability): bool
```

`SupplierResult` is a uniform envelope with a `status` of `success | failed |
pending | processing | unknown | timeout | not_supported`. Every adapter method
returns one — nothing throws for a normal business outcome, so the order engine
never has to special-case a supplier.

`App\Integrations\Suppliers\SupplierManager` resolves, per supplier slug, either
the **mock** adapter (`AbstractMockSupplier` subclasses — deterministic, fake
credentials only) or the **live** adapter, based on `config('firmware94.suppliers.
<slug>.mode')`. Every live adapter class exists purely as an interface/architecture
placeholder: every method throws `RuntimeException` with an explicit message to
implement it against the real, documented API once the project owner supplies
that documentation — this is intentional per the master prompt ("do NOT invent
undocumented endpoints... never return a fake success response from production
adapters").

## Order engine (`App\Services\Orders\OrderEngine`)

Follows the exact 11-step workflow from the master prompt: validate user →
validate service → validate inputs → calculate price (`PricingService`) → check
balance → create `Order` → debit wallet (one DB transaction, idempotent) → send to
the highest-priority active `SupplierServiceMapping` → record the supplier
reference → update status → finalize.

- A **confirmed failure** from the supplier auto-refunds (new ledger row, never an
  edit).
- A **timeout or ambiguous ("unknown") response never auto-refunds** — the order
  sits in `Order::STATUS_UNKNOWN` until `ReconcileUnknownSupplierOrders` (scheduled
  every 5 minutes) or a staff member resolves it.
- Idempotency keys are enforced at the database level (`orders.idempotency_key`
  unique, `wallet_transactions.idempotency_key` unique) so a retried request can
  never create a duplicate order or double-debit a wallet, even under concurrent
  requests.

## Wallet ledger (`App\Services\Wallet\WalletService`)

`wallet_transactions` is append-only by convention and by code: every mutation
locks the wallet row (`lockForUpdate`), checks the idempotency key, and inserts a
new row — nothing in the codebase ever runs an `UPDATE` or `DELETE` against a
posted transaction. A correction is posted as a new offsetting row via
`WalletService::reverse()`. All amounts are `bcmath` strings on `DECIMAL(14,4)`
columns — never floats.

## Three independent UI layouts

`resources/views/layouts/admin.blade.php` (dark-navy collapsible sidebar),
`customer.blade.php`, and `reseller.blade.php` are separate Blade layouts with
separate navigation menus (see master prompt §5 Module E) — no shared "one menu
fits all roles" component. Route groups in `routes/web.php` gate each layout by
Spatie role (`role:super-admin,admin,staff`, `role:customer,distributor`,
`role:reseller,distributor`) in addition to each page's own authorization checks —
authorization is never just a hidden button.
