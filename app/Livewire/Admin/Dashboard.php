<?php

namespace App\Livewire\Admin;

use App\Models\Deposit;
use App\Models\Order;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.admin.dashboard', [
            'pendingOrders' => Order::whereIn('status', ['pending', 'reserved', 'sent_to_supplier', 'processing'])->count(),
            'unknownOrders' => Order::where('status', 'unknown')->count(),
            'pendingDeposits' => Deposit::where('status', 'pending')->count(),
        ]);
    }
}
