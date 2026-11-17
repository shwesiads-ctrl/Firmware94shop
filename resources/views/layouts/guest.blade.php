<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Firmware94shop' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <span class="text-2xl font-bold text-f94-navy">F94</span>
            <p class="text-slate-500 text-sm">Firmware94shop</p>
        </div>
        <div class="f94-card">
            {{ $slot ?? '' }}
            @yield('content')
        </div>
    </div>
</body>
</html>
