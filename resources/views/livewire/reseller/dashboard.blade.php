<div class="f94-card">
    <p class="text-xs text-slate-400 uppercase">Wallet</p>
    <p class="text-2xl font-semibold text-f94-teal">{{ $wallet->balance ?? 0 }} USD</p>
    <p class="text-sm text-slate-500 mt-2">Group: {{ $profile?->resellerGroup?->name ?? '—' }}</p>
</div>
