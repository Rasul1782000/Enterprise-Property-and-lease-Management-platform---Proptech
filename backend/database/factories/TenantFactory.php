<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'company_name' => fake()->optional()->company(),
            'id_number' => fake()->unique()->numerify('##########'),
            'date_of_birth' => fake()->date(),
            'address' => fake()->address(),
            'status' => 'active',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
