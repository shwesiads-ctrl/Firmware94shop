@extends('layouts.guest')
@section('content')
    <h1 class="text-lg font-semibold text-slate-800 mb-4">Sign in</h1>

    @if ($errors->any())
        <div class="mb-4 text-sm text-red-600">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm text-slate-600 mb-1">Email</label>
            <input type="email" name="email" required autofocus class="w-full rounded-lg border-slate-300">
        </div>
        <div>
            <label class="block text-sm text-slate-600 mb-1">Password</label>
            <input type="password" name="password" required class="w-full rounded-lg border-slate-300">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember"> Remember me
        </label>
        <button type="submit" class="f94-btn-primary w-full">Sign in</button>
    </form>

    <div class="mt-4 flex justify-between text-sm">
        <a href="{{ route('password.request') }}" class="text-f94-teal">Forgot password?</a>
        <a href="{{ route('register') }}" class="text-f94-teal">Create account</a>
    </div>
@endsection
