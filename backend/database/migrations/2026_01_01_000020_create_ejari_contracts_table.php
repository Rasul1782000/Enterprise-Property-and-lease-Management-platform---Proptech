<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ejari_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('ejari_number')->nullable()->unique();
            $table->string('lease_code');
            $table->string('property_name');
            $table->string('unit_code');
            $table->string('landlord_name');
            $table->string('tenant_name');
            $table->string('tenant_company');
            $table->string('ejari_type');
            $table->date('registration_date')->nullable();
            $table->date('certificate_issued_on')->nullable();
            $table->date('expiry_date');
            $table->enum('status', ['draft', 'submitted', 'registered', 'rejected', 'cancelled'])->default('draft');
            $table->decimal('registration_fee', 14, 2)->default(0);
            $table->decimal('penalty_amount', 14, 2)->default(0);
            $table->string('deed_reference')->nullable();
            $table->boolean('free_zone_property')->default(false);
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['lease_code', 'status']);
            $table->index(['ejari_number', 'status']);
            $table->index(['expiry_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ejari_contracts');
    }
};