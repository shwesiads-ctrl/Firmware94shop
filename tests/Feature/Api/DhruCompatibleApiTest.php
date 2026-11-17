<?php

use App\Models\ApiCredential;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Models\Wallet;

beforeEach(function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 50]);
    ['credential' => $this->credential, 'secret' => $this->secret] = ApiCredential::generate($user);
});

function dhruCall(array $overrides = []): array
{
    return array_merge([
        'username' => test()->credential->public_key,
        'apiAccessKey' => test()->secret,
    ], $overrides);
}

test('accountinfo returns the wallet balance for a valid credential', function () {
    $response = $this->post('/api/index.php', dhruCall(['action' => 'accountinfo']));

    $response->assertOk()->assertJson(['STATUS' => 'success', 'BALANCE' => '50.0000']);
});

test('an invalid API secret is rejected with a DHRU-shaped error, never a 500', function () {
    $response = $this->post('/api/index.php', [
        'action' => 'accountinfo', 'username' => $this->credential->public_key, 'apiAccessKey' => 'wrong-secret',
    ]);

    $response->assertStatus(401)->assertJsonStructure(['ERROR', 'STATUS']);
});

test('imeiservicelist lists active services only', function () {
    $category = ServiceCategory::factory()->create();
    Service::factory()->create(['service_category_id' => $category->id, 'is_active' => true]);
    Service::factory()->create(['service_category_id' => $category->id, 'is_active' => false]);

    $response = $this->post('/api/index.php', dhruCall(['action' => 'imeiservicelist']));

    $response->assertOk();
    expect($response->json('SERVICES'))->toHaveCount(1);
});

test('an unsupported/undocumented action returns a clear error, never a fabricated success', function () {
    $response = $this->post('/api/index.php', dhruCall(['action' => 'totally_made_up_action']));

    $response->assertStatus(400)->assertJson(['STATUS' => 'error']);
});
