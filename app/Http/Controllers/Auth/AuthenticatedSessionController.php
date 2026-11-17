<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $this->recordLoginHistory($request, successful: false);

            return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if ($user->isSuspended()) {
            Auth::logout();
            return back()->withErrors(['email' => __('auth.suspended')]);
        }

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $this->recordLoginHistory($request, successful: true);

        return redirect()->intended($this->redirectPathFor($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function recordLoginHistory(Request $request, bool $successful): void
    {
        $user = Auth::user() ?? \App\Models\User::where('email', $request->input('email'))->first();

        if (! $user) {
            return;
        }

        $user->loginHistories()->create([
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'successful' => $successful,
            'failure_reason' => $successful ? null : 'invalid_credentials',
            'created_at' => now(),
        ]);
    }

    private function redirectPathFor($user): string
    {
        return match (true) {
            $user->hasAnyRole(['super-admin', 'admin', 'staff']) => route('admin.dashboard'),
            $user->hasRole('reseller') => route('reseller.dashboard'),
            $user->hasRole('distributor') => route('reseller.dashboard'),
            default => route('customer.dashboard'),
        };
    }
}
