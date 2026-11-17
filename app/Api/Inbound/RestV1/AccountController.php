<?php

namespace App\Api\Inbound\RestV1;

use Illuminate\Http\Request;

class AccountController
{
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'wallet_balance' => (string) $user->wallet?->balance,
                'currency' => $user->wallet?->currency ?? 'USD',
            ],
        ]);
    }
}
