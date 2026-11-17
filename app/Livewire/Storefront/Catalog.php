<?php

namespace App\Livewire\Storefront;

use App\Models\ServiceCategory;
use Livewire\Component;

class Catalog extends Component
{
    public ?string $group = null;

    public function render()
    {
        return view('livewire.storefront.catalog', [
            'categories' => ServiceCategory::where('is_active', true)
                ->when($this->group, fn ($q) => $q->where('group', $this->group))
                ->with('services')
                ->get(),
        ]);
    }
}
