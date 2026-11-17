<?php

namespace App\Livewire\Reseller;

use App\Models\ApiCredential;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ApiKeys extends Component
{
    public ?string $newSecret = null;
    public string $label = '';

    public function generate(): void
    {
        $this->validate(['label' => ['required', 'string', 'max:100']]);

        ['credential' => $credential, 'secret' => $secret] = ApiCredential::generate(Auth::user(), [
            'label' => $this->label,
            'scopes' => ['orders.create', 'orders.read', 'wallet.read'],
        ]);

        // Shown exactly once — only the hash is stored after this request.
        $this->newSecret = "{$credential->public_key}.{$secret}";
        $this->label = '';
    }

    public function render()
    {
        return view('livewire.reseller.api-keys', ['credentials' => Auth::user()->apiCredentials]);
    }
}
