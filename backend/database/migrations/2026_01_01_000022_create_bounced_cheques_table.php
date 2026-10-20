<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bounced_cheques', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('cheque_no');
            $table->string('bank');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('tenant_name');
            $table->string('tenant_company');
            $table->string('lease_code');
            $table->string('unit_code');
            $table->string('property_name');
            $table->date('bounced_on');
            $table->string('bounce_reason');
            $table->enum('stage', [
                'new', 'notified', 'promise_to_pay', 'partially_recovered',
                'recovered', 'escalated', 'legal_action', 'written_off'
            ])->default('new');
            $table->decimal('recovered_amount', 14, 2)->default(0);
            $table->decimal('outstanding', 14, 2);
            $table->decimal('recovery_percent', 5, 2)->default(0);
            $table->unsignedInteger('actions_taken')->default(0);
            $table->date('next_action_due');
            $table->unsignedInteger('aging_days');
            $table->string('case_manager');
            $table->enum('priority', ['low', 'normal', 'high', 'critical'])->default('normal');
            $table->boolean('legal_notice_sent')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['stage', 'priority']);
            $table->index('cheque_no');
            $table->index(['tenant_name', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bounced_cheques');
    }
};