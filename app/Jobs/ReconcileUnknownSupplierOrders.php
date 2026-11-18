<?php

namespace App\Jobs;

use App\Integrations\Suppliers\SupplierManager;
use App\Models\Order;
use App\Services\Orders\OrderEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Status-only reconciliation. Never re-submit an unknown order to supplier.
 * Confirmed failure passes through OrderEngine's atomic refund path.
 */
class ReconcileUnknownSupplierOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $limit = 50)
    {
    }

    public function handle(SupplierManager $suppliers, OrderEngine $orders): void
    {
        Order::where('status', Order::STATUS_UNKNOWN)
            ->oldest('updated_at')
            ->limit($this->limit)
            ->get()
            ->each(function (Order $order) use ($suppliers, $orders) {
                $supplierOrder = $order->supplierOrders()->latest()->first();

                if (! $supplierOrder || ! $supplierOrder->supplier_reference) {
                    return; // Manual review required
                }

                try {
                    $adapter = $suppliers->for($supplierOrder->supplier->slug);
                    if (! $adapter->supports('getOrderStatus')) {
                        return;
                    }

                    $result = $adapter->getOrderStatus($supplierOrder->supplier_reference);
                    if (! $result->isFinal()) {
                        return;
                    }
                } catch (Throwable $e) {
                    report($e);
                    return; // Stay UNKNOWN; never guess acceptance or refund
                }

                $supplierOrder->update(['status' => $result->status, 'last_checked_at' => now()]);
                $orders->resolveConfirmedSupplierResult($order, $result);
            });
    }
}
