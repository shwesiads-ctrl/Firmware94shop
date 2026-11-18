<?php
use App\Integrations\Suppliers\Contracts\SupplierResult;
use App\Jobs\ReconcileUnknownSupplierOrders;
use App\Models\{Order,Service,ServiceCategory,Supplier,SupplierServiceMapping,User,Wallet};
use App\Services\Orders\OrderEngine;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->buyer=User::factory()->create();
    $this->other=User::factory()->create();
    Wallet::create(['user_id'=>$this->buyer->id,'balance'=>100]);
    Wallet::create(['user_id'=>$this->other->id,'balance'=>100]);
    $category=ServiceCategory::factory()->create();
    $this->service=Service::factory()->create(['service_category_id'=>$category->id,'base_price'=>10]);
    $supplier=Supplier::create(['name'=>'Mock','slug'=>'dhru_classic','driver'=>'dhru_classic','mode'=>'mock']);
    SupplierServiceMapping::create(['service_id'=>$this->service->id,'supplier_id'=>$supplier->id,'supplier_service_ref'=>'MOCK-1','supplier_cost'=>5,'is_active'=>true]);
    $this->engine=app(OrderEngine::class);
});

test('replay must belong to same owner and payload', function () {
    $key='test-one-key';
    $first=$this->engine->placeOrder($this->buyer,$this->service,['imei'=>'1111111111111110'],$key);
    $second=$this->engine->placeOrder($this->buyer,$this->service,['imei'=>'1111111111111110'],$key);
    expect($first->id)->toBe($second->id);
    expect($first->supplierOrders()->count())->toBe(1);
    expect(fn()=>$this->engine->placeOrder($this->other,$this->service,['imei'=>'1111111111111110'],$key))->toThrow(ValidationException::class);
    expect(fn()=>$this->engine->placeOrder($this->buyer,$this->service,['imei'=>'1111111111111111'],$key))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(1);
    expect((string)$this->other->wallet->refresh()->balance)->toBe('100.0000');
});
test('confirmed failure refund is idempotent', function () {
    $order=$this->engine->placeOrder($this->buyer,$this->service,['imei'=>'1111111111111114'],'test-unknown');
    expect($order->status)->toBe(Order::STATUS_UNKNOWN);
    $this->engine->resolveConfirmedSupplierResult($order,SupplierResult::failed('rejected'));
    $this->engine->resolveConfirmedSupplierResult($order,SupplierResult::failed('rejected'));
    expect($order->refresh()->status)->toBe(Order::STATUS_REFUNDED);
    expect((string)$this->buyer->wallet->refresh()->balance)->toBe('100.0000');
    expect($this->buyer->wallet->transactions()->where('type','refund')->count())->toBe(1);
});
test('reconciled confirmed supplier failure refunds exactly once', function () {
    $order=$this->engine->placeOrder($this->buyer,$this->service,['imei'=>'1111111111111114'],'test-recon');
    $order->supplierOrders()->firstOrFail()->update(['supplier_reference'=>'REF-1']);
    app(ReconcileUnknownSupplierOrders::class)->handle(app(\App\Integrations\Suppliers\SupplierManager::class),$this->engine);
    expect($order->refresh()->status)->toBe(Order::STATUS_REFUNDED);
});
test('wallet currency mismatch blocks charge instead of guessing FX', function () {
    $this->buyer->wallet->update(['currency'=>'MMK']);
    expect(fn()=>$this->engine->placeOrder($this->buyer,$this->service,['imei'=>'1111111111111110'],'test-fx'))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(0);
});
test('free service leaves wallet ledger unchanged', function () {
    $this->service->update(['base_price'=>0]);
    $order=$this->engine->placeOrder($this->buyer,$this->service,['imei'=>'1111111111111110'],'test-free');
    expect($order->status)->toBe(Order::STATUS_SUCCESS);
    expect($this->buyer->wallet->transactions()->count())->toBe(0);
});
