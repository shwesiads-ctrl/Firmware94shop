<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class EnforceApiRateLimit
{
    public function handle(Request $request, Closure $next)
    {
        $credential = $request->attributes->get('api_credential');
        $key = 'f94-api:'.($credential?->id ?? $request->ip());
        $max = $credential?->rate_limit_per_minute ?? config('firmware94.api.rate_limit_per_minute');

        if (RateLimiter::tooManyAttempts($key, $max)) {
            abort(429, 'Too many requests. Please slow down.');
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
