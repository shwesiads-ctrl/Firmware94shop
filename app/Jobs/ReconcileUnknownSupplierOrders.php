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
                    // AUDIT FIX (BUG-011, P0, Phase 2.2): this branch used to
                    // resolve an OrderEngine instance from the container and
                    // do absolutely nothing with it — a no-op. The order was
                    // left at STATUS_UNKNOWN forever and the customer was
                    // never refunded even once the supplier had positively
                    // confirmed the order failed. Now that we have a
                    // CONFIRMED (isFinal()) failure from the supplier — not
                    // a guess — it is safe to run it through the exact same
                    // controlled refund path OrderEngine uses for a direct,
                    // synchronous failure.
                    app(\App\Services\Orders\OrderEngine::class)
                        ->finalizeFailure($order, 'Confirmed failed by reconciliation: '.($result->message ?? 'supplier reported failure.'));
                    $order->recordEvent('status_updated', ['result' => 'failed_confirmed_by_reconciliation']);
                }
            });
    }
}
