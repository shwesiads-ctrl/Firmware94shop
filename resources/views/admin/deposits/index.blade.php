@extends('layouts.admin')
@section('content')
    <h1 class="text-xl font-semibold text-slate-800 mb-6">{{ __('nav.deposits') }}</h1>

    <div class="f94-card overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400">
                <tr><th class="py-2">#</th><th>User</th><th>Amount</th><th>Method</th><th>Status</th><th>Proof</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($deposits as $deposit)
                    <tr class="border-t border-slate-100">
                        <td class="py-2">{{ $deposit->id }}</td>
                        <td>{{ $deposit->user->name }}</td>
                        <td>{{ $deposit->amount }} {{ $deposit->currency }}</td>
                        <td>{{ $deposit->paymentMethod->label }}</td>
                        <td>
                            <span class="f94-badge {{ match($deposit->status) {
                                'approved' => 'bg-emerald-100 text-emerald-700',
                                'rejected' => 'bg-red-100 text-red-700',
                                default => 'bg-amber-100 text-amber-700',
                            } }}">{{ $deposit->status }}</span>
                        </td>
                        <td>{{ $deposit->transaction_hash ?: '—' }}</td>
                        <td class="text-right space-x-2">
                            @if ($deposit->status === 'pending')
                                <form method="POST" action="{{ route('admin.deposits.approve', $deposit) }}" class="inline">
                                    @csrf
                                    <button class="text-emerald-600 hover:underline">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.deposits.reject', $deposit) }}" class="inline"
                                      onsubmit="return confirm('Reject this deposit?')">
                                    @csrf
                                    <input type="hidden" name="note" value="Rejected by admin">
                                    <button class="text-red-600 hover:underline">Reject</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $deposits->links() }}</div>
    </div>
@endsection
