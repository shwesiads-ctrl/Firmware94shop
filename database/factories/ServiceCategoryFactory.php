<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ServiceCategoryFactory extends Factory
{
    protected $model = \App\Models\ServiceCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'group' => fake()->randomElement(['imei_carrier_icloud_frp', 'server_tools_cards', 'rent_shared_boxes', 'file_firmware']),
            'is_active' => true,
        ];
    }
}
