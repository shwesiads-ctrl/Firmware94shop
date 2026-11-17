@extends('layouts.admin')
@section('content')
    <h1 class="text-xl font-semibold text-slate-800 mb-6">{{ __('nav.dashboard') }}</h1>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        @foreach ([
            ['Pending Orders', \App\Models\Order::whereIn('status', ['pending','reserved','sent_to_supplier','processing'])->count()],
            ['Unknown Orders', \App\Models\Order::where('status', 'unknown')->count()],
            ['Pending Deposits', \App\Models\Deposit::where('status', 'pending')->count()],
            ['Total Resellers', \App\Models\ResellerProfile::count()],
        ] as [$label, $value])
            <div class="f94-card">
                <p class="text-xs text-slate-400 uppercase">{{ $label }}</p>
                <p class="text-2xl font-semibold text-f94-navy mt-1">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="f94-card">
        <h2 class="font-semibold text-slate-800 mb-3">Supplier connections</h2>
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400">
                <tr><th class="py-2">Supplier</th><th>Mode</th><th>Active</th></tr>
            </thead>
            <tbody>
                @foreach (\App\Models\Supplier::all() as $supplier)
                    <tr class="border-t border-slate-100">
                        <td class="py-2">{{ $supplier->name }}</td>
                        <td><span class="f94-badge {{ $supplier->mode === 'mock' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $supplier->mode }}</span></td>
                        <td>{{ $supplier->is_active ? 'Yes' : 'No' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
