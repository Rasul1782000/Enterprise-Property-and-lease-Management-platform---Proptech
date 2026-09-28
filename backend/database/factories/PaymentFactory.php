<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'payment_number' => 'PAY-'.fake()->unique()->numberBetween(1000, 9999),
            'invoice_id' => Invoice::factory(),
            'tenant_id' => Tenant::factory(),
            'amount' => fake()->numberBetween(100, 5000),
            'method' => fake()->randomElement(['cash', 'bank_transfer', 'check', 'card', 'online']),
            'reference' => fake()->optional()->word(),
            'paid_at' => fake()->dateTime(),
            'recorded_by' => User::factory(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
