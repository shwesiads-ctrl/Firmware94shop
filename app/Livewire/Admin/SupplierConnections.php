<?php

namespace App\Livewire\Admin;

use App\Integrations\Suppliers\SupplierManager;
use App\Models\Supplier;
use Livewire\Component;

class SupplierConnections extends Component
{
    public array $results = [];

    public function test(int $supplierId): void
    {
        $supplier = Supplier::findOrFail($supplierId);
        $adapter = app(SupplierManager::class)->for($supplier->slug);

        $start = microtime(true);
        $result = $adapter->testConnection();
        $durationMs = (int) ((microtime(true) - $start) * 1000);

        $supplier->connectionLogs()->create([
            'action' => 'testConnection',
            'outcome' => $result->status,
            'message' => $result->message,
            'duration_ms' => $durationMs,
        ]);

        $this->results[$supplierId] = $result->toArray();
    }

    public function render()
    {
        return view('livewire.admin.supplier-connections', ['suppliers' => Supplier::all()]);
    }
}
