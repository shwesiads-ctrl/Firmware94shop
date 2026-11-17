<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy
{
    public function view(User $user, SupportTicket $ticket): bool
    {
        return $ticket->user_id === $user->id || $user->hasAnyRole(['admin', 'staff']);
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        return $ticket->user_id === $user->id || $user->hasAnyRole(['admin', 'staff']);
    }
}
