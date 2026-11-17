@extends('layouts.customer')
@section('content')
    <div class="f94-card max-w-lg">
        <h1 class="text-lg font-semibold text-slate-800 mb-4">{{ __('nav.my_profile') }}</h1>
        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <div>
                <label class="block text-sm text-slate-600 mb-1">Name</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" class="w-full rounded-lg border-slate-300">
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" class="w-full rounded-lg border-slate-300">
            </div>
            <div>
                <label class="block text-sm text-slate-600 mb-1">Language</label>
                <select name="locale" class="w-full rounded-lg border-slate-300">
                    @foreach (config('app.supported_locales') as $locale)
                        <option value="{{ $locale }}" @selected($user->locale === $locale)>{{ strtoupper($locale) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="f94-btn-primary">Save changes</button>
        </form>
    </div>
@endsection
