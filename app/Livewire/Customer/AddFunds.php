<?php

namespace App\Livewire\Customer;

use App\Models\PaymentMethod;
use App\Services\Payments\DepositService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class AddFunds extends Component
{
    use WithFileUploads;

    public ?int $paymentMethodId = null;
    public string $amount = '';
    public string $network = '';
    public string $transactionHash = '';
    public $proof;
    public ?string $status = null;

    public function submit(DepositService $deposits): void
    {
        $this->validate([
            'paymentMethodId' => ['required', 'exists:payment_methods,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'proof' => ['nullable', 'image', 'max:5120'],
        ]);

        $deposits->submit(Auth::user(), $this->paymentMethodId, $this->amount, $this->network ?: null, $this->transactionHash ?: null, $this->proof);

        $this->reset('amount', 'network', 'transactionHash', 'proof');
        $this->status = 'Deposit submitted — pending admin review.';
    }

    public function render()
    {
        return view('livewire.customer.add-funds', ['methods' => PaymentMethod::where('is_active', true)->get()]);
    }
}
