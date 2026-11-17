<?php

namespace App\Http\Controllers\Admin;

use App\Models\Deposit;
use App\Services\Payments\DepositService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DepositReviewController
{
    public function index()
    {
        return view('admin.deposits.index', [
            'deposits' => Deposit::with(['user', 'paymentMethod'])->latest()->paginate(20),
        ]);
    }

    public function approve(Request $request, Deposit $deposit, DepositService $deposits): RedirectResponse
    {
        $this->authorizeStaff($request);
        $deposits->approve($deposit, $request->user(), $request->input('note'));

        return back()->with('status', "Deposit #{$deposit->id} approved and wallet credited.");
    }

    public function reject(Request $request, Deposit $deposit, DepositService $deposits): RedirectResponse
    {
        $this->authorizeStaff($request);
        $validated = $request->validate(['note' => ['required', 'string']]);
        $deposits->reject($deposit, $request->user(), $validated['note']);

        return back()->with('status', "Deposit #{$deposit->id} rejected.");
    }

    private function authorizeStaff(Request $request): void
    {
        abort_unless($request->user()->can('payments.approve'), 403);
    }
}
