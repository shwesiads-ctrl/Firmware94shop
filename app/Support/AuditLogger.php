<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/** Append-only audit trail writer. Never update/delete an AuditLog row. */
class AuditLogger
{
    public static function record(string $action, ?Model $auditable = null, array $before = [], array $after = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => Request::ip(),
            'created_at' => now(),
        ]);
    }
}
