<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ResellerGroupFactory extends Factory
{
    protected $model = \App\Models\ResellerGroup::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true).' Tier';

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'default_discount_percent' => fake()->randomFloat(2, 0, 20),
        ];
    }
}
