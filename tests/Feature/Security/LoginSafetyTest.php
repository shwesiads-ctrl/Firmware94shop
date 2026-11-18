<?php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('five failed logins block the sixth attempt even with correct credentials',function(){
    $user=User::factory()->create(['password'=>Hash::make('valid-password')]);
    for($i=0;$i<5;$i++){
        $this->post('/login',['email'=>$user->email,'password'=>'wrong'])->assertSessionHasErrors('email');
    }
    $this->post('/login',['email'=>$user->email,'password'=>'valid-password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});
