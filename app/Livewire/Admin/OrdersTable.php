<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class OrdersTable extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public function render()
    {
        $orders = Order::query()
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(15);

        return view('livewire.admin.orders-table', ['orders' => $orders]);
    }
}
