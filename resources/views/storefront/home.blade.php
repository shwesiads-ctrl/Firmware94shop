<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Firmware94shop — GSM Digital Services Marketplace</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50">
    <header class="bg-f94-navy text-white">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <span class="font-bold text-lg">F94 <span class="font-normal text-slate-200">Firmware94shop</span></span>
            <div class="flex items-center gap-4 text-sm">
                <x-locale-switcher dark />
                @auth
                    <a href="{{ route('customer.dashboard') }}" class="hover:text-f94-cyan">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="hover:text-f94-cyan">Login</a>
                    <a href="{{ route('register') }}" class="hover:text-f94-cyan">Register</a>
                @endauth
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-12">
        <h1 class="text-3xl font-bold text-f94-navy mb-2">GSM Digital Services Marketplace</h1>
        <p class="text-slate-500 mb-10">Phase 2 Foundation build — development preview.</p>

        <div class="grid md:grid-cols-4 gap-4">
            @foreach ([
                ['nav.cat_imei', 'imei-carrier-icloud-frp'],
                ['nav.cat_server', 'server-tools-cards'],
                ['nav.cat_rent', 'rent-shared-boxes'],
                ['nav.cat_file', 'file-firmware-link'],
            ] as [$label, $slug])
                <div class="f94-card">
                    <h2 class="font-semibold text-slate-800">{{ __($label) }}</h2>
                    <p class="text-xs text-slate-400 mt-1">Sample development catalog</p>
                </div>
            @endforeach
        </div>
    </main>
</body>
</html>
