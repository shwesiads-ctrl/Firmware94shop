<?php

namespace App\Api\Inbound\RestV1;

use Illuminate\Http\Request;

class TransactionController
{
    public function index(Request $request)
    {
        $transactions = $request->user()->wallet
            ->transactions()
            ->latest('created_at')
            ->paginate(25);

        return response()->json($transactions);
    }
}
