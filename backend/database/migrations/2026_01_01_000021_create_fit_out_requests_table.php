<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fit_out_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('property_name');
            $table->string('unit_code');
            $table->string('tenant_name');
            $table->string('tenant_company');
            $table->string('contractor_name');
            $table->string('contractor_trn');
            $table->text('scope');
            $table->enum('permit_status', ['not_submitted', 'under_review', 'approved', 'rejected', 'expired'])->default('not_submitted');
            $table->enum('noc_status', ['pending', 'inspection_scheduled', 'noc_issued', 'noc_rejected'])->default('pending');
            $table->decimal('deposit_amount', 14, 2);
            $table->string('deposit_status');
            $table->date('requested_on');
            $table->date('expected_completion');
            $table->date('inspection_date')->nullable();
            $table->unsignedInteger('progress_percent')->default(0);
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $table->decimal('estimated_cost', 14, 2);
            $table->string('owner_name');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['property_name', 'unit_code']);
            $table->index(['permit_status', 'noc_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fit_out_requests');
    }
};