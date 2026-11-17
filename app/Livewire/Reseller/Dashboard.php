<?php

namespace App\Livewire\Reseller;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $user = Auth::user();

        return view('livewire.reseller.dashboard', [
            'wallet' => $user->wallet,
            'profile' => $user->resellerProfile,
        ]);
    }
}
