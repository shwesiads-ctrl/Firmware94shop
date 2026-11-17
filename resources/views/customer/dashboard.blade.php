@extends('layouts.customer')
@section('content')
    @php $wallet = auth()->user()->wallet; @endphp
    <h1 class="text-xl font-semibold text-slate-800">{{ __('nav.dashboard') }}</h1>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @foreach ([
            ['Current Balance', ($wallet->balance ?? 0).' USD'],
            ['Unpaid Balance', '0.0000 USD'],
            ['Total Receipts', auth()->user()->orders()->count()],
            ['Locked Amount', ($wallet->locked_balance ?? 0).' USD'],
            ['Successful Orders', auth()->user()->orders()->where('status', 'success')->count()],
            ['Processing Orders', auth()->user()->orders()->whereIn('status', ['pending','reserved','sent_to_supplier','processing'])->count()],
            ['Failed Orders', auth()->user()->orders()->where('status', 'failed')->count()],
        ] as [$label, $value])
            <div class="f94-card">
                <p class="text-xs text-slate-400 uppercase">{{ $label }}</p>
                <p class="text-xl font-semibold text-f94-navy mt-1">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <div class="f94-card">
            <h2 class="font-semibold text-slate-800 mb-3">Recent Orders</h2>
            @forelse (auth()->user()->orders()->latest()->take(5)->get() as $order)
                <div class="flex justify-between text-sm py-1.5 border-t border-slate-100">
                    <span>{{ $order->order_number }}</span>
                    <span class="text-slate-400">{{ $order->status }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-400">No orders yet.</p>
            @endforelse
        </div>
        <div class="f94-card">
            <h2 class="font-semibold text-slate-800 mb-3">{{ __('nav.announcements') }}</h2>
            @forelse (\App\Models\Announcement::where('audience', 'all')->latest()->take(3)->get() as $announcement)
                <div class="py-1.5 border-t border-slate-100 text-sm">
                    <p class="font-medium">{{ $announcement->title }}</p>
                    <p class="text-slate-500">{{ $announcement->body }}</p>
                </div>
            @empty
                <p class="text-sm text-slate-400">No announcements.</p>
            @endforelse
        </div>
    </div>
@endsection
