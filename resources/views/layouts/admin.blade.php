<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('nav.dashboard') }} — Firmware94shop Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full" x-data="{ sidebarOpen: false }">
    <div class="flex h-full">
        {{-- Dark navy, collapsible sidebar --}}
        <aside
            class="fixed inset-y-0 left-0 z-40 w-64 bg-f94-sidebar text-slate-200 transform transition-transform lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex items-center gap-2 px-5 h-16 border-b border-white/10">
                <span class="text-f94-cyan font-bold text-lg">F94</span>
                <span class="font-semibold">Firmware94shop</span>
            </div>
            <nav class="px-3 py-4 space-y-1 text-sm overflow-y-auto h-[calc(100%-4rem)]">
                @foreach ([
                    ['admin.dashboard', 'nav.dashboard'], ['admin.deposits.index', 'nav.deposits'],
                ] as [$route, $label])
                    <a href="{{ Route::has($route) ? route($route) : '#' }}"
                       class="block rounded-lg px-3 py-2 hover:bg-white/10 {{ request()->routeIs($route) ? 'bg-white/10 text-white' : '' }}">
                        {{ __($label) }}
                    </a>
                @endforeach
                @foreach (['nav.orders','nav.customers','nav.resellers','nav.suppliers','nav.integrations','nav.services','nav.categories','nav.pricing','nav.wallets','nav.payments','nav.withdrawals','nav.vouchers','nav.subscriptions','nav.announcements','nav.support_tickets','nav.reports','nav.api_logs','nav.settings','nav.security','nav.audit_logs'] as $label)
                    <span class="block rounded-lg px-3 py-2 text-slate-400 cursor-default" title="Phase 3">{{ __($label) }}</span>
                @endforeach
            </nav>
        </aside>

        <div class="flex-1 lg:pl-64 flex flex-col min-h-full">
            {{-- Top navigation --}}
            <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-6">
                <button class="lg:hidden text-slate-500" @click="sidebarOpen = !sidebarOpen">☰</button>
                <nav class="text-sm text-slate-500">
                    @isset($breadcrumbs)
                        @foreach ($breadcrumbs as $crumb) <span>{{ $crumb }}</span> @if (! $loop->last) <span class="mx-1">/</span> @endif @endforeach
                    @else
                        {{ $title ?? __('nav.dashboard') }}
                    @endisset
                </nav>
                <div class="flex items-center gap-3">
                    <x-locale-switcher />
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-sm text-slate-600 hover:text-f94-navy">{{ __('nav.logout') }}</button>
                    </form>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6">
                @if (session('status'))
                    <div class="f94-card mb-4 border-emerald-200 bg-emerald-50 text-emerald-700 text-sm">{{ session('status') }}</div>
                @endif
                {{ $slot ?? '' }}
                @yield('content')
            </main>
        </div>
    </div>
    @livewireScripts
</body>
</html>
