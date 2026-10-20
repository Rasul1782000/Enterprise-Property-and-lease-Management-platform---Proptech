<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_renewals', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('lease_code');
            $table->string('property_name');
            $table->string('unit_code');
            $table->string('tenant_name');
            $table->string('tenant_company');
            $table->decimal('current_rent', 14, 2);
            $table->decimal('proposed_rent', 14, 2);
            $table->decimal('uplift_percent', 5, 2);
            $table->date('expiry_date');
            $table->unsignedInteger('days_to_expiry');
            $table->date('offer_sent_on')->nullable();
            $table->date('response_due_on');
            $table->date('responded_on')->nullable();
            $table->enum('status', ['draft', 'sent', 'under_negotiation', 'accepted', 'declined', 'expired'])->default('draft');
            $table->unsignedInteger('term_months');
            $table->date('new_start_date');
            $table->date('new_end_date');
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->string('owner_note')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['lease_code', 'status']);
            $table->index(['expiry_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_renewals');
    }
};