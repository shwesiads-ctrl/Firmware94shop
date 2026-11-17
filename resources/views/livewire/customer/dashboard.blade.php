<div class="f94-card">
    <p class="text-xs text-slate-400 uppercase">Balance</p>
    <p class="text-2xl font-semibold text-f94-navy">{{ $wallet->balance ?? 0 }} USD</p>
    <div class="mt-4 space-y-1">
        @foreach ($recentOrders as $order)
            <div class="flex justify-between text-sm border-t border-slate-100 py-1.5">
                <span>{{ $order->order_number }}</span><span class="text-slate-400">{{ $order->status }}</span>
            </div>
        @endforeach
    </div>
</div>
