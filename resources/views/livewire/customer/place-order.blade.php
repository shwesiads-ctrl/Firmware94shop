<div class="f94-card max-w-lg">
    <h2 class="font-semibold text-slate-800 mb-3">Place an order</h2>

    @if ($resultMessage)
        <div class="mb-3 text-sm {{ $resultStatus === 'error' ? 'text-red-600' : 'text-emerald-600' }}">{{ $resultMessage }}</div>
    @endif

    <select wire:model.live="serviceId" class="w-full rounded-lg border-slate-300 mb-3">
        <option value="">Select a service</option>
        @foreach ($services as $service)
            <option value="{{ $service->id }}">{{ $service->name }} — {{ $service->base_price }} USD</option>
        @endforeach
    </select>

    @if ($selectedService)
        @foreach ($selectedService->fields as $field)
            <input type="text" wire:model="inputs.{{ $field->key }}" placeholder="{{ $field->label }}" class="w-full rounded-lg border-slate-300 mb-2">
        @endforeach
        @if ($selectedService->fields->isEmpty())
            <input type="text" wire:model="inputs.imei" placeholder="IMEI (end in 1-5 to simulate other outcomes)" class="w-full rounded-lg border-slate-300 mb-2">
        @endif
        <button wire:click="submit" class="f94-btn-primary w-full">Place order</button>
    @endif
</div>
