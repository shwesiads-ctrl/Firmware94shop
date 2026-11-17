@extends('layouts.guest')
@section('content')
    <h1 class="text-lg font-semibold text-slate-800 mb-4">Reset your password</h1>
    @if (session('status'))
        <div class="mb-4 text-sm text-emerald-600">{{ session('status') }}</div>
    @endif
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <input type="email" name="email" required placeholder="Email" class="w-full rounded-lg border-slate-300">
        <button type="submit" class="f94-btn-primary w-full">Send reset link</button>
    </form>
@endsection
