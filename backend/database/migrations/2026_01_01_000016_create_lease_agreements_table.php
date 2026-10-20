<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('lease_code');
            $table->string('property_name');
            $table->string('unit_code');
            $table->string('tenant_name');
            $table->string('tenant_company');
            $table->string('type');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('rent_amount', 14, 2);
            $table->decimal('security_deposit', 14, 2);
            $table->string('payment_frequency');
            $table->string('template');
            $table->unsignedInteger('version')->default(1);
            $table->date('generated_on');
            $table->enum('status', ['draft', 'sent', 'partially_signed', 'executed', 'expired'])->default('draft');
            $table->date('landlord_signed_on')->nullable();
            $table->date('tenant_signed_on')->nullable();
            $table->string('witness_name')->nullable();
            $table->string('ejari_number')->nullable();
            $table->unsignedInteger('document_size_kb')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['lease_code', 'status']);
            $table->index('ejari_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_agreements');
    }
};