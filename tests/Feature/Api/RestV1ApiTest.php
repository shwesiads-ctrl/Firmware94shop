<?php

use App\Models\ApiCredential;
use App\Models\User;
use App\Models\Wallet;

beforeEach(function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 75]);
    ['credential' => $this->credential, 'secret' => $this->secret] = ApiCredential::generate($user, ['scopes' => ['orders.create']]);
    $this->token = "{$this->credential->public_key}.{$this->secret}";
});

test('GET /api/v1/account requires a valid bearer token', function () {
    $this->getJson('/api/v1/account')->assertStatus(401);
});

test('GET /api/v1/account returns the authenticated reseller profile with a valid token', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")->getJson('/api/v1/account');

    $response->assertOk()->assertJsonPath('data.wallet_balance', '75.0000');
});

test('GET /api/v1/wallet returns balance fields', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")->getJson('/api/v1/wallet');

    $response->assertOk()->assertJsonStructure(['data' => ['balance', 'available_balance', 'locked_balance', 'currency']]);
});

test('an inactive API credential is rejected', function () {
    $this->credential->update(['is_active' => false]);

    $this->withHeader('Authorization', "Bearer {$this->token}")->getJson('/api/v1/account')->assertStatus(401);
});

test('API usage is logged for every authenticated request', function () {
    $this->withHeader('Authorization', "Bearer {$this->token}")->getJson('/api/v1/wallet');

    expect(\App\Models\ApiUsageLog::where('api_credential_id', $this->credential->id)->exists())->toBeTrue();
});
