<?php

use App\Api\Inbound\DhruCompatible\DhruCompatibleController;
use App\Api\Inbound\RestV1\AccountController;
use App\Api\Inbound\RestV1\OrderController;
use App\Api\Inbound\RestV1\ServiceController;
use App\Api\Inbound\RestV1\TransactionController;
use App\Api\Inbound\RestV1\WalletController;
use App\Api\Inbound\RestV1\WebhookTestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| A. DHRU Classic-compatible API — master prompt §4A
|--------------------------------------------------------------------------
| POST /api/index.php — action is chosen by the "action" POST field so any
| existing DHRU-Classic-compatible reseller script can talk to this shop
| with no changes besides the base URL.
*/
Route::post('/index.php', [DhruCompatibleController::class, 'handle'])
    ->middleware(['f94.resolve_credential', 'api.ip_allowlist', 'api.rate_limit']);

/*
|--------------------------------------------------------------------------
| B. Firmware94shop REST API — master prompt §4B — base path /api/v1
|--------------------------------------------------------------------------
*/
Route::prefix('v1')
    ->middleware(['f94.resolve_credential', 'api.ip_allowlist', 'api.rate_limit'])
    ->group(function () {
        Route::get('/account', [AccountController::class, 'show']);
        Route::get('/services', [ServiceController::class, 'index']);
        Route::get('/services/{service}', [ServiceController::class, 'show']);
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::get('/wallet', [WalletController::class, 'show']);
        Route::post('/webhooks/test', [WebhookTestController::class, 'store']);
    });
