<?php

namespace App\Api\Inbound\RestV1;

use Illuminate\Http\Request;

/**
 * Authenticated development-only endpoint for a reseller to confirm their
 * webhook receiver is reachable and see exactly what Firmware94shop would
 * send. Not wired to any real event in Phase 2 — see master prompt §4B.
 */
class WebhookTestController
{
    public function store(Request $request)
    {
        return response()->json([
            'data' => [
                'event' => 'order.updated',
                'sent_at' => now()->toIso8601String(),
                'sample_payload' => [
                    'order_id' => 123,
                    'order_number' => 'F94-SAMPLE0001',
                    'status' => 'success',
                ],
            ],
            'note' => 'This is a development-only simulated payload, not a real webhook delivery.',
        ]);
    }
}
