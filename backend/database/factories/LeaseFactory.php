<?php

namespace Database\Factories;

use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaseFactory extends Factory
{
    protected $model = Lease::class;

    public function definition(): array
    {
        return [
            'lease_number' => 'LSE-'.fake()->unique()->numberBetween(1000, 9999),
            'unit_id' => Unit::factory(),
            'tenant_id' => Tenant::factory(),
            'start_date' => fake()->date(),
            'end_date' => fake()->date(),
            'rent_amount' => fake()->numberBetween(500, 5000),
            'deposit_amount' => fake()->numberBetween(500, 5000),
            'late_fee_percent' => 2.5,
            'due_day' => 5,
            'status' => 'active',
            'payment_frequency' => 'monthly',
            'created_by' => User::factory(),
        ];
    }
}
