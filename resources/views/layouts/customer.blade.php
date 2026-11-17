<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('nav.dashboard') }} — Firmware94shop</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full" x-data="{ menuOpen: false }">
    <header class="bg-f94-navy text-white">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="{{ route('home') }}" class="font-bold text-lg">F94 <span class="font-normal text-slate-200">Firmware94shop</span></a>
            <nav class="hidden md:flex items-center gap-4 text-sm">
                <a href="{{ route('customer.dashboard') }}" class="hover:text-f94-cyan">{{ __('nav.dashboard') }}</a>
                <a href="{{ route('customer.deposits.create') }}" class="hover:text-f94-cyan">{{ __('nav.add_funds') }}</a>
                <x-locale-switcher dark />
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="hover:text-f94-cyan">{{ __('nav.logout') }}</button>
                </form>
            </nav>
            <button class="md:hidden" @click="menuOpen = !menuOpen">☰</button>
        </div>
    </header>

    <div class="max-w-6xl mx-auto px-4 py-6 flex gap-6">
        <aside class="hidden lg:block w-64 shrink-0">
            <div class="f94-card">
                <p class="text-xs font-semibold text-slate-400 uppercase mb-2">{{ __('nav.my_profile') }}</p>
                @foreach (['my_profile','add_funds','api_access','my_invoice','my_mail','my_statement','credit_transfer','recharge_voucher','email_preference','service_status','subscription','install_app','my_tickets','withdrawal_request','gift_certificate','last_login'] as $item)
                    <a href="#" class="block py-1.5 text-sm text-slate-600 hover:text-f94-navy">{{ __('nav.'.$item) }}</a>
                @endforeach
            </div>
        </aside>

        <main class="flex-1 space-y-6">
            @if (session('status'))
                <div class="f94-card border-emerald-200 bg-emerald-50 text-emerald-700 text-sm">{{ session('status') }}</div>
            @endif
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>
    @livewireScripts
</body>
</html>
