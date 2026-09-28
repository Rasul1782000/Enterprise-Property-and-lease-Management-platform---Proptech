<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $amount = fake()->numberBetween(500, 5000);

        return [
            'invoice_number' => 'INV-'.fake()->unique()->numberBetween(1000, 9999),
            'lease_id' => Lease::factory(),
            'tenant_id' => Tenant::factory(),
            'unit_id' => Unit::factory(),
            'period_start' => fake()->date(),
            'period_end' => fake()->date(),
            'due_date' => fake()->date(),
            'amount' => $amount,
            'late_fee' => 0,
            'total_amount' => $amount,
            'status' => fake()->randomElement(['pending', 'paid', 'overdue', 'cancelled', 'partial']),
            'notes' => fake()->optional()->sentence(),
            'sent_at' => fake()->optional()->dateTime(),
        ];
    }
}
