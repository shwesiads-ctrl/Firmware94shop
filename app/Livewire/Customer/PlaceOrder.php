<?php

namespace App\Livewire\Customer;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Service;
use App\Services\Orders\OrderEngine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Customer-facing "place an order" form — drives the Mock Order Engine
 * directly, so a customer can exercise the full Phase 2 workflow (price
 * calc, wallet debit, mock supplier dispatch, status) from the UI.
 */
class PlaceOrder extends Component
{
    public ?int $serviceId = null;
    public array $inputs = [];
    public ?string $resultMessage = null;
    public ?string $resultStatus = null;

    public function updatedServiceId(): void
    {
        $this->inputs = [];
    }

    public function submit(OrderEngine $engine): void
    {
        $service = Service::findOrFail($this->serviceId);

        try {
            $order = $engine->placeOrder(
                Auth::user(),
                $service,
                $this->inputs,
                (string) Str::uuid(),
                'storefront',
            );

            $this->resultStatus = $order->status;
            $this->resultMessage = "Order {$order->order_number} created — status: {$order->status}.";
        } catch (InsufficientBalanceException $e) {
            $this->resultStatus = 'error';
            $this->resultMessage = 'Insufficient wallet balance. Please add funds first.';
        } catch (ValidationException $e) {
            $this->resultStatus = 'error';
            $this->resultMessage = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.customer.place-order', [
            'services' => Service::where('is_active', true)->with('fields')->get(),
            'selectedService' => $this->serviceId ? Service::with('fields')->find($this->serviceId) : null,
        ]);
    }
}
