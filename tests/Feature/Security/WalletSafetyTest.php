<?php
use App\Models\{User,Wallet};
use App\Services\Wallet\WalletService;

test('wallet idempotency key cannot target other wallet or amount',function(){
    $a=User::factory()->create();$b=User::factory()->create();
    $w1=Wallet::create(['user_id'=>$a->id,'balance'=>100]);
    $w2=Wallet::create(['user_id'=>$b->id,'balance'=>100]);
    $svc=app(WalletService::class);
    $svc->debit($w1,'10.0000','key-once');
    expect(fn()=>$svc->debit($w2,'10.0000','key-once'))->toThrow(\LogicException::class);
    expect(fn()=>$svc->debit($w1,'12.0000','key-once'))->toThrow(\LogicException::class);
    expect((string)$w2->refresh()->balance)->toBe('100.0000');
});
test('wallet rejects zero and negative mutations',function(){
    $u=User::factory()->create();
    $wallet=Wallet::create(['user_id'=>$u->id,'balance'=>100]);
    $svc=app(WalletService::class);
    expect(fn()=>$svc->credit($wallet,'0.0000','zero'))->toThrow(\InvalidArgumentException::class);
    expect(fn()=>$svc->debit($wallet,'-1.0000','negative'))->toThrow(\InvalidArgumentException::class);
});
