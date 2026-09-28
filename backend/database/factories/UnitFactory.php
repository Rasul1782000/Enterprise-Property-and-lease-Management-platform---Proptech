<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'unit_number' => fake()->unique()->numerify('U-####'),
            'floor' => fake()->numberBetween(1, 10),
            'sqft' => fake()->numberBetween(300, 3000),
            'bedrooms' => fake()->numberBetween(0, 4),
            'bathrooms' => fake()->numberBetween(1, 3),
            'rent_amount' => fake()->numberBetween(500, 5000),
            'unit_type' => fake()->randomElement(['office', 'retail', 'apartment', 'studio', 'warehouse']),
            'status' => fake()->randomElement(['vacant', 'occupied', 'maintenance', 'reserved']),
            'amenities' => fake()->optional()->sentence(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
