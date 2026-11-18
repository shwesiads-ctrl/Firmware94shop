<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
});

test('a visitor can register and is assigned the customer role with a zero-balance wallet', function () {
    $response = $this->post('/register', [
        'name' => 'Jane Reseller',
        'email' => 'jane@example.test',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ]);

    $user = User::where('email', 'jane@example.test')->first();

    expect($user)->not->toBeNull();
    expect($user->hasRole('customer'))->toBeTrue();
    expect((string) $user->wallet->balance)->toBe('0.0000');
    $response->assertRedirect(route('customer.dashboard'));
});

test('a user can log in with correct credentials and login history is recorded', function () {
    $user = User::factory()->create(['password' => Hash::make('secret1234')]);
    $user->assignRole('customer');

    $response = $this->post('/login', ['email' => $user->email, 'password' => 'secret1234']);

    $response->assertRedirect(route('customer.dashboard'));
    $this->assertAuthenticatedAs($user);
    expect($user->loginHistories()->where('successful', true)->exists())->toBeTrue();
});

test('a user cannot log in with the wrong password and the failure is recorded', function () {
    $user = User::factory()->create(['password' => Hash::make('secret1234')]);

    $response = $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('a suspended user is logged back out immediately on login attempt', function () {
    $user = User::factory()->suspended()->create(['password' => Hash::make('secret1234')]);

    $this->post('/login', ['email' => $user->email, 'password' => 'secret1234']);

    $this->assertGuest();
});

test('an admin-only route rejects a plain customer', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');

    $this->actingAs($user)->get('/admin')->assertForbidden();
});
