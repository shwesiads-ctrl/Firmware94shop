<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Resolves the active locale for the request in this order:
 * signed-in user's saved preference -> session -> "lang" query param -> app default.
 * Persists the resolved locale back into the session so it sticks across requests.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $supported = config('app.supported_locales', ['en', 'my']);

        $locale = $request->query('lang')
            ?? $request->session()->get('locale')
            ?? optional($request->user())->locale
            ?? config('app.locale');

        if (! in_array($locale, $supported, true)) {
            $locale = config('app.fallback_locale', 'en');
        }

        App::setLocale($locale);
        $request->session()->put('locale', $locale);

        return $next($request);
    }
}
