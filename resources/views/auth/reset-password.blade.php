@extends('layouts.guest')
@section('content')
    <h1 class="text-lg font-semibold text-slate-800 mb-4">Choose a new password</h1>
    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <input type="email" name="email" required placeholder="Email" value="{{ old('email', $request->email) }}" class="w-full rounded-lg border-slate-300">
        <input type="password" name="password" required placeholder="New password" class="w-full rounded-lg border-slate-300">
        <input type="password" name="password_confirmation" required placeholder="Confirm new password" class="w-full rounded-lg border-slate-300">
        <button type="submit" class="f94-btn-primary w-full">Reset password</button>
    </form>
@endsection
