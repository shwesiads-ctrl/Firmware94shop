<?php

namespace App\Http\Middleware;

use App\Models\ApiCredential;
use Closure;
use Illuminate\Http\Request;

/**
 * Enforces per-credential IP allowlisting for both the DHRU-compatible
 * endpoint and the REST v1 API. A credential with no configured rules and
 * allowlisting disabled globally is allowed through; a credential with rules
 * configured is only ever allowed from those IPs, regardless of the global
 * default.
 */
class EnforceApiIpAllowlist
{
    public function handle(Request $request, Closure $next)
    {
        /** @var ApiCredential|null $credential */
        $credential = $request->attributes->get('api_credential');

        if (! $credential) {
            return $next($request);
        }

        $rules = $credential->ipRules()->pluck('ip_cidr')->all();

        if (empty($rules)) {
            if (config('firmware94.api.ip_allowlist_enabled_by_default')) {
                abort(403, 'No IP allowlist configured for this credential.');
            }

            return $next($request);
        }

        $ip = $request->ip();

        foreach ($rules as $cidr) {
            if (\App\Support\Net\IpMatcher::matches($ip, $cidr)) {
                return $next($request);
            }
        }

        abort(403, 'Request IP is not allowlisted for this API credential.');
    }
}
