<?php

use App\Jobs\ReconcileUnknownSupplierOrders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Orders left in "unknown" state (supplier timeout / ambiguous response) are
// never auto-refunded. This job only surfaces them for staff review and
// re-checks status with the supplier where that capability exists.
Schedule::job(new ReconcileUnknownSupplierOrders)->everyFiveMinutes();

// Housekeeping
Schedule::command('queue:prune-batches')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
