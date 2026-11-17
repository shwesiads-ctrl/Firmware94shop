<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('nav.reseller_dashboard') }} — Firmware94shop</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full">
    <header class="bg-f94-teal text-white">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="{{ route('home') }}" class="font-bold text-lg">F94 <span class="font-normal">Reseller</span></a>
            <nav class="hidden md:flex items-center gap-4 text-sm">
                @foreach (['reseller_dashboard' => 'reseller.dashboard'] as $label => $route)
                    <a href="{{ route($route) }}" class="hover:text-f94-cyan">{{ __('nav.'.$label) }}</a>
                @endforeach
                <x-locale-switcher dark />
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="hover:text-f94-cyan">{{ __('nav.logout') }}</button>
                </form>
            </nav>
        </div>
    </header>

    <div class="max-w-6xl mx-auto px-4 py-6 flex gap-6">
        <aside class="hidden lg:block w-64 shrink-0">
            <div class="f94-card">
                @foreach (['wallet','service_catalog','order_management','group_pricing','api_access','api_documentation','api_keys','ip_whitelist','api_usage_logs','transactions','invoices','support','reports'] as $item)
                    <a href="#" class="block py-1.5 text-sm text-slate-600 hover:text-f94-teal">{{ __('nav.'.$item) }}</a>
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
