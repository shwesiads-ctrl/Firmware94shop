<div class="f94-card overflow-x-auto">
    <div class="mb-3">
        <select wire:model.live="status" class="rounded-lg border-slate-300 text-sm">
            <option value="">All statuses</option>
            @foreach (['pending','reserved','sent_to_supplier','processing','success','failed','unknown','refunded'] as $s)
                <option value="{{ $s }}">{{ $s }}</option>
            @endforeach
        </select>
    </div>
    <table class="w-full text-sm">
        <thead class="text-left text-slate-400"><tr><th class="py-2">Order #</th><th>User</th><th>Status</th><th>Price</th></tr></thead>
        <tbody>
            @foreach ($orders as $order)
                <tr class="border-t border-slate-100">
                    <td class="py-2">{{ $order->order_number }}</td>
                    <td>{{ $order->user->name }}</td>
                    <td><span class="f94-badge bg-slate-100 text-slate-700">{{ $order->status }}</span></td>
                    <td>{{ $order->price_charged }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="mt-4">{{ $orders->links() }}</div>
</div>
