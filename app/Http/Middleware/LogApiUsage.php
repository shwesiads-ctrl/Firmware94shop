<?php

namespace App\Http\Middleware;

use App\Models\ApiUsageLog;
use Closure;
use Illuminate\Http\Request;

class LogApiUsage
{
    public function handle(Request $request, Closure $next)
    {
        // Tests/queue requests may not define the public index.php LARAVEL_START constant.
        $startedAt = microtime(true);
        $response = $next($request);

        // Fire-and-forget; usage logging must never block or fail the request.
        try {
            ApiUsageLog::create([
                'api_credential_id' => optional($request->attributes->get('api_credential'))->id,
                'ip_address' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'status_code' => $response->getStatusCode(),
                'response_time_ms' => max(0, (int) ((microtime(true) - $startedAt) * 1000)),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }
}
