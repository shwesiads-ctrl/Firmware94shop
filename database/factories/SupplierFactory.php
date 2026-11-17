<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    protected $model = \App\Models\Supplier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'slug' => fake()->unique()->slug(2),
            'driver' => 'mock',
            'mode' => 'mock',
            'is_active' => true,
            'priority' => fake()->numberBetween(0, 10),
        ];
    }
}
