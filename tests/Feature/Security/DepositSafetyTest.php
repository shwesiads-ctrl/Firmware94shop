<?php
use App\Models\{Deposit,PaymentMethod,User,Wallet};
use App\Services\Payments\DepositService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->buyer=User::factory()->create();
    $this->buyer->assignRole('customer');
    Wallet::create(['user_id'=>$this->buyer->id,'balance'=>0]);
    $this->admin=User::factory()->create();
    $this->admin->assignRole('admin');
    $this->method=PaymentMethod::create(['code'=>'manual','label'=>'Manual','is_active'=>true]);
});
function safetyDeposit($test, string $currency='USD'): Deposit {
    return Deposit::create(['user_id'=>$test->buyer->id,'payment_method_id'=>$test->method->id,'amount'=>'20.0000','currency'=>$currency,'status'=>'pending']);
}
test('deposit approval credits exactly once', function () {
    $deposit=safetyDeposit($this);
    app(DepositService::class)->approve($deposit,$this->admin);
    expect(fn()=>app(DepositService::class)->approve($deposit,$this->admin))->toThrow(\RuntimeException::class);
    expect((string)$this->buyer->wallet->refresh()->balance)->toBe('20.0000');
    expect($this->buyer->wallet->transactions()->count())->toBe(1);
});
test('wrong deposit currency must not be credited', function () {
    $deposit=safetyDeposit($this,'MMK');
    expect(fn()=>app(DepositService::class)->approve($deposit,$this->admin))->toThrow(\RuntimeException::class);
    expect($deposit->refresh()->status)->toBe('pending');
});
test('staff cannot review deposits', function () {
    $staff=User::factory()->create();$staff->assignRole('staff');
    $deposit=safetyDeposit($this);
    expect($staff->can('payments.approve'))->toBeFalse();
    $this->actingAs($staff)->get('/admin/deposits')->assertForbidden();
    $this->actingAs($staff)->post("/admin/deposits/{$deposit->id}/approve")->assertForbidden();
});
