<?php

namespace App\Api\Inbound\RestV1;

use Illuminate\Http\Request;

class WalletController
{
    public function show(Request $request)
    {
        $wallet = $request->user()->wallet;

        return response()->json(['data' => [
            'balance' => (string) $wallet->balance,
            'available_balance' => $wallet->availableBalance(),
            'locked_balance' => (string) $wallet->locked_balance,
            'currency' => $wallet->currency,
        ]]);
    }
}
