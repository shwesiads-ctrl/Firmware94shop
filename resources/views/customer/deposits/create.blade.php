@extends('layouts.customer')
@section('content')
    <div class="f94-card max-w-lg">
        <h1 class="text-lg font-semibold text-slate-800 mb-1">{{ __('nav.add_funds') }}</h1>
        <p class="text-xs text-slate-400 mb-4">
            Deposits are reviewed by an admin before your wallet is credited. We never auto-approve from a screenshot or transaction hash alone.
        </p>

        <form method="POST" action="{{ route('customer.deposits.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm text-slate-600 mb-1">Payment method</label>
                <select name="payment_method_id" required class="w-full rounded-lg border-slate-300">
                    @foreach ($methods as $method)
                        <option value="{{ $method->id }}">{{ $method->label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Amount (USD)</label>
                <input type="number" step="0.01" min="1" name="amount" required class="w-full rounded-lg border-slate-300">
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Network (for USDT)</label>
                <input type="text" name="network" placeholder="TRC20 or BEP20" class="w-full rounded-lg border-slate-300">
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Transaction hash</label>
                <input type="text" name="transaction_hash" class="w-full rounded-lg border-slate-300">
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Payment proof (screenshot)</label>
                <input type="file" name="proof" accept="image/*" class="w-full">
            </div>
            <button type="submit" class="f94-btn-primary w-full">Submit deposit</button>
        </form>
    </div>
@endsection
