<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->can('orders.view');
    }

    public function manage(User $user, Order $order): bool
    {
        return $user->can('orders.manage');
    }

    public function create(User $user): bool
    {
        return ! $user->isSuspended();
    }
}
