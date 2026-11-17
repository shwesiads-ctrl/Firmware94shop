<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wallet;

class WalletPolicy
{
    public function view(User $user, Wallet $wallet): bool
    {
        return $wallet->user_id === $user->id || $user->can('wallet.view');
    }

    public function manage(User $user, Wallet $wallet): bool
    {
        return $user->can('wallet.manage');
    }
}
