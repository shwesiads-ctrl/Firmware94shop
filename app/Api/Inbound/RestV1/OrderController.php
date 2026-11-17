<?php

namespace App\Api\Inbound\RestV1;

use App\Models\ApiCredential;
use App\Models\Order;
use App\Models\Service;
use App\Services\Orders\OrderEngine;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController
{
    public function __construct(private OrderEngine $orderEngine)
    {
    }

    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)->latest()->paginate(25);

        return response()->json($orders);
    }

    public function show(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return response()->json(['data' => $order->load('events', 'supplierOrders')]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_id' => ['required', 'exists:services,id'],
            'inputs' => ['required', 'array'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ]);

        /** @var ApiCredential|null $credential */
        $credential = $request->attributes->get('api_credential');

        if ($credential && ! $credential->hasScope('orders.create')) {
            return response()->json(['message' => 'This API key does not have the orders.create scope.'], 403);
        }

        try {
            $order = $this->orderEngine->placeOrder(
                $request->user(),
                Service::findOrFail($validated['service_id']),
                $validated['inputs'],
                $validated['idempotency_key'],
                'reseller_rest_api',
                $credential,
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }

        return response()->json(['data' => $order], 201);
    }
}
