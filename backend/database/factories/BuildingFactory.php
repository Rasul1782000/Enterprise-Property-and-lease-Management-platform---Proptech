<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class BuildingFactory extends Factory
{
    protected $model = Building::class;

    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'name' => fake()->words(2, true),
            'code' => 'BLD-'.fake()->unique()->numberBetween(100, 999),
            'floors' => fake()->numberBetween(1, 20),
            'year_built' => fake()->year(),
            'construction_type' => fake()->randomElement(['concrete', 'steel', 'wood', 'masonry']),
            'description' => fake()->sentence(),
        ];
    }
}
