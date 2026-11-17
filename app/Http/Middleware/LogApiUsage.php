<?php

namespace App\Http\Middleware;

use App\Models\ApiUsageLog;
use Closure;
use Illuminate\Http\Request;

class LogApiUsage
{
    public function handle(Request $request, Closure $next)
    {
        // AUDIT FIX (BUG-005, P2, Phase 2.1): this used to reference the
        // global LARAVEL_START constant directly inside the try block below.
        // That constant is only defined by the `artisan` CLI script and
        // `public/index.php` — any other entry point (notably `vendor/bin/
        // pest` run directly, rather than via `php artisan test`) leaves it
        // undefined, which throws an Error that the try/catch below quietly
        // swallows and reports, silently dropping the usage-log row. That
        // defeats the point of API usage auditing in exactly the context
        // (automated tests) where we need it to be reliable. Captured here,
        // with a safe fallback, instead of read from the global constant.
        $start = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

        $response = $next($request);

        // Fire-and-forget; usage logging must never block or fail the request.
        try {
            ApiUsageLog::create([
                'api_credential_id' => optional($request->attributes->get('api_credential'))->id,
                'ip_address' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'status_code' => $response->getStatusCode(),
                'response_time_ms' => (int) ((microtime(true) - $start) * 1000),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }
}
