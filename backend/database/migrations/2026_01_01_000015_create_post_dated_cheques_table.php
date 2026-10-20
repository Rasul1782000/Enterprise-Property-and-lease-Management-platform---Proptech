<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_dated_cheques', function (Blueprint $table) {
            $table->id();
            $table->string('cheque_no');
            $table->string('bank');
            $table->string('branch');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('payer');
            $table->string('payer_company');
            $table->string('payee');
            $table->string('lease_code');
            $table->string('unit_code');
            $table->string('property_name');
            $table->date('issued_on');
            $table->date('due_on');
            $table->date('deposited_on')->nullable();
            $table->enum('status', ['pending', 'deposited', 'cleared', 'returned', 'replaced'])->default('pending');
            $table->string('instalment_label');
            $table->string('bounce_case_code')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['due_on', 'status']);
            $table->index('cheque_no');
            $table->index('lease_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_dated_cheques');
    }
};