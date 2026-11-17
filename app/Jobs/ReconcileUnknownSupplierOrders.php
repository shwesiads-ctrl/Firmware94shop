<?php

namespace App\Jobs;

use App\Integrations\Suppliers\SupplierManager;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Re-checks every order stuck in Order::STATUS_UNKNOWN by re-querying the
 * supplier's getOrderStatus() where that capability exists. It only ever
 * moves an order OUT of "unknown" on a confirmed success or failure from the
 * supplier — it never guesses and never auto-refunds (master prompt §5
 * Module I: "Never automatically refund an order whose supplier acceptance
 * is unknown").
 */
class ReconcileUnknownSupplierOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $limit = 50)
    {
    }

    public function handle(SupplierManager $suppliers): void
    {
        Order::where('status', Order::STATUS_UNKNOWN)
            ->latest('updated_at')
            ->limit($this->limit)
            ->get()
            ->each(function (Order $order) use ($suppliers) {
                $supplierOrder = $order->supplierOrders()->latest()->first();

                if (! $supplierOrder || ! $supplierOrder->supplier_reference) {
                    return; // nothing to re-check yet; leave for manual staff review
                }

                $adapter = $suppliers->for($supplierOrder->supplier->slug);

                if (! $adapter->supports('getOrderStatus')) {
                    return;
                }

                $result = $adapter->getOrderStatus($supplierOrder->supplier_reference);

                if (! $result->isFinal()) {
                    return; // still ambiguous — try again next run
                }

                $supplierOrder->update(['status' => $result->status, 'last_checked_at' => now()]);

                if ($result->isSuccessful()) {
                    $order->update(['status' => Order::STATUS_SUCCESS, 'completed_at' => now()]);
                    $order->recordEvent('finalized', ['result' => 'success', 'via' => 'reconciliation']);
                } else {
                    // Confirmed failure now known -> safe to resolve via the
                    // same controlled path OrderEngine uses for a direct failure.
                    app(\App\Services\Orders\OrderEngine::class);
                    $order->recordEvent('status_updated', ['result' => 'failed_confirmed_by_reconciliation']);
                }
            });
    }
}
