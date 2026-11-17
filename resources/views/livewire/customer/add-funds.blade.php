<div class="f94-card max-w-lg">
    @if ($status) <div class="mb-3 text-sm text-emerald-600">{{ $status }}</div> @endif
    <select wire:model="paymentMethodId" class="w-full rounded-lg border-slate-300 mb-2">
        <option value="">Payment method</option>
        @foreach ($methods as $method) <option value="{{ $method->id }}">{{ $method->label }}</option> @endforeach
    </select>
    <input type="number" wire:model="amount" placeholder="Amount (USD)" class="w-full rounded-lg border-slate-300 mb-2">
    <input type="text" wire:model="network" placeholder="Network (TRC20/BEP20)" class="w-full rounded-lg border-slate-300 mb-2">
    <input type="text" wire:model="transactionHash" placeholder="Transaction hash" class="w-full rounded-lg border-slate-300 mb-2">
    <input type="file" wire:model="proof" class="w-full mb-3">
    <button wire:click="submit" class="f94-btn-primary w-full">Submit deposit</button>
</div>
