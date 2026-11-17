@extends('layouts.guest')
@section('content')
    <h1 class="text-lg font-semibold text-slate-800 mb-4">Create a customer account</h1>

    @if ($errors->any())
        <ul class="mb-4 text-sm text-red-600 list-disc pl-5">
            @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm text-slate-600 mb-1">Name</label>
            <input type="text" name="name" required class="w-full rounded-lg border-slate-300">
        </div>
        <div>
            <label class="block text-sm text-slate-600 mb-1">Email</label>
            <input type="email" name="email" required class="w-full rounded-lg border-slate-300">
        </div>
        <div>
            <label class="block text-sm text-slate-600 mb-1">Password</label>
            <input type="password" name="password" required class="w-full rounded-lg border-slate-300">
        </div>
        <div>
            <label class="block text-sm text-slate-600 mb-1">Confirm password</label>
            <input type="password" name="password_confirmation" required class="w-full rounded-lg border-slate-300">
        </div>
        <button type="submit" class="f94-btn-primary w-full">Create account</button>
    </form>

    <p class="mt-4 text-xs text-slate-400">
        Reseller and distributor accounts are created by an administrator after approval, not through this form.
    </p>
@endsection
