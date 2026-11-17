@extends('layouts.reseller')
@section('content')
    @php $wallet = auth()->user()->wallet; $profile = auth()->user()->resellerProfile; @endphp
    <h1 class="text-xl font-semibold text-slate-800">{{ __('nav.reseller_dashboard') }}</h1>

    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="f94-card">
            <p class="text-xs text-slate-400 uppercase">{{ __('nav.wallet') }}</p>
            <p class="text-xl font-semibold text-f94-teal mt-1">{{ $wallet->balance ?? 0 }} USD</p>
        </div>
        <div class="f94-card">
            <p class="text-xs text-slate-400 uppercase">Group</p>
            <p class="text-xl font-semibold mt-1">{{ $profile?->resellerGroup?->name ?? '—' }}</p>
        </div>
        <div class="f94-card">
            <p class="text-xs text-slate-400 uppercase">Approval status</p>
            <p class="text-xl font-semibold mt-1 capitalize">{{ $profile?->approval_status ?? 'n/a' }}</p>
        </div>
    </div>

    <div class="f94-card">
        <h2 class="font-semibold text-slate-800 mb-3">{{ __('nav.api_keys') }}</h2>
        @forelse (auth()->user()->apiCredentials as $credential)
            <div class="flex justify-between text-sm py-1.5 border-t border-slate-100">
                <span>{{ $credential->label ?? $credential->public_key }}</span>
                <span class="text-slate-400">{{ $credential->is_active ? 'Active' : 'Disabled' }}</span>
            </div>
        @empty
            <p class="text-sm text-slate-400">No API keys yet — generate one from the panel in a later phase.</p>
        @endforelse
    </div>
@endsection
