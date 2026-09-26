<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Center',
            'code' => 'PROP-'.$this->faker->unique()->bothify('###'),
            'type' => $this->faker->randomElement(['commercial','multi_family','mixed_use']),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'zip' => $this->faker->postcode(),
            'total_area_sqft' => $this->faker->numberBetween(10000,100000),
            'year_built' => $this->faker->numberBetween(1990,2024),
            'status' => 'active',
        ];
    }
}
