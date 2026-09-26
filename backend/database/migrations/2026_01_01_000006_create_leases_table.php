<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->string('lease_number')->unique(); // LEASE-2026-0001
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('rent_amount', 10, 2);
            $table->decimal('deposit_amount', 10, 2)->default(0);
            $table->decimal('late_fee_percent', 5, 2)->default(2.5);
            $table->integer('due_day')->default(5); // rent due day of month
            $table->enum('status', ['draft', 'active', 'expired', 'terminated', 'renewed'])->default('draft');
            $table->enum('payment_frequency', ['monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->json('terms')->nullable(); // custom clauses
            $table->text('notes')->nullable();
            $table->string('document_path')->nullable(); // signed pdf
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'end_date']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
