<?php

namespace App\Http\Middleware;

use App\Models\ApiCredential;
use Closure;
use Illuminate\Http\Request;

/**
 * Authenticates inbound API calls — both the DHRU-compatible endpoint
 * (which sends "username" + "apiAccessKey" POST fields, per the classic
 * DHRU convention) and the REST v1 API (which sends a Bearer token in the
 * form "{public_key}.{secret}"). On success, attaches the resolved
 * ApiCredential to the request so downstream middleware/controllers never
 * see or need the raw secret again, and never expose it back to the client.
 */
class ResolveApiCredential
{
    public function handle(Request $request, Closure $next)
    {
        $publicKey = $request->input('username') ?? $this->bearerPublicKey($request);
        $secret = $request->input('apiAccessKey') ?? $this->bearerSecret($request);

        if (! $publicKey || ! $secret) {
            return $this->unauthorized($request, 'Missing API credentials.');
        }

        $credential = ApiCredential::where('public_key', $publicKey)->where('is_active', true)->first();

        if (! $credential || ! $credential->verifySecret($secret)) {
            return $this->unauthorized($request, 'Invalid API credentials.');
        }

        if ($credential->user->isSuspended()) {
            return $this->unauthorized($request, 'Account is suspended.');
        }

        $credential->forceFill(['last_used_at' => now()])->save();

        $request->attributes->set('api_credential', $credential);
        $request->setUserResolver(fn () => $credential->user);

        return $next($request);
    }

    private function bearerPublicKey(Request $request): ?string
    {
        $token = $request->bearerToken();
        return $token && str_contains($token, '.') ? explode('.', $token, 2)[0] : null;
    }

    private function bearerSecret(Request $request): ?string
    {
        $token = $request->bearerToken();
        return $token && str_contains($token, '.') ? explode('.', $token, 2)[1] : null;
    }

    private function unauthorized(Request $request, string $message)
    {
        // The DHRU-compatible endpoint has its own documented error shape;
        // REST v1 gets a plain JSON error. Both avoid leaking internals.
        if ($request->is('api/index.php')) {
            return response()->json(['ERROR' => $message, 'STATUS' => 'error'], 401);
        }

        return response()->json(['message' => $message], 401);
    }
}
