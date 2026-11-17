<?php

namespace App\Livewire\Customer;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $user = Auth::user();

        return view('livewire.customer.dashboard', [
            'wallet' => $user->wallet,
            'recentOrders' => $user->orders()->latest()->take(5)->get(),
        ]);
    }
}
