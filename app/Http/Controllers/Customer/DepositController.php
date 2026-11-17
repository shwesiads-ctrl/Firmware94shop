<?php

namespace App\Http\Controllers\Customer;

use App\Models\PaymentMethod;
use App\Services\Payments\DepositService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DepositController
{
    public function create()
    {
        return view('customer.deposits.create', ['methods' => PaymentMethod::where('is_active', true)->get()]);
    }

    public function store(Request $request, DepositService $deposits): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'network' => ['nullable', 'string'],
            'transaction_hash' => ['nullable', 'string', 'max:255'],
            'proof' => ['nullable', 'image', 'max:5120'],
        ]);

        $deposit = $deposits->submit(
            $request->user(),
            $validated['payment_method_id'],
            (string) $validated['amount'],
            $validated['network'] ?? null,
            $validated['transaction_hash'] ?? null,
            $request->file('proof'),
        );

        return redirect()->route('customer.dashboard')
            ->with('status', "Deposit #{$deposit->id} submitted and is pending admin review.");
    }
}
