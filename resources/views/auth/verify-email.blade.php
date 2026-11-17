@extends('layouts.guest')
@section('content')
    <h1 class="text-lg font-semibold text-slate-800 mb-4">Verify your email</h1>
    <p class="text-sm text-slate-600 mb-4">We've sent a verification link to your email address.</p>
    @if (session('status') === 'verification-link-sent')
        <div class="mb-4 text-sm text-emerald-600">A new verification link has been sent.</div>
    @endif
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="f94-btn-primary w-full">Resend verification email</button>
    </form>
@endsection
