<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_charges', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('property_name');
            $table->unsignedInteger('period_year');
            $table->string('category');
            $table->decimal('budgeted_amount', 14, 2);
            $table->decimal('actual_amount', 14, 2);
            $table->decimal('variance', 14, 2);
            $table->decimal('total_area_sqft', 10, 2);
            $table->decimal('rate_per_sqft', 10, 4);
            $table->unsignedInteger('units_charged');
            $table->decimal('amount_collected', 14, 2)->default(0);
            $table->decimal('outstanding', 14, 2)->default(0);
            $table->date('issued_on');
            $table->date('due_date');
            $table->enum('status', ['draft', 'issued', 'partially_paid', 'paid', 'closed'])->default('draft');
            $table->string('approved_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'period_year']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_charges');
    }
};