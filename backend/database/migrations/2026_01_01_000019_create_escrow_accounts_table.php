<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escrow_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('property_name');
            $table->string('bank');
            $table->string('iban');
            $table->string('account_number');
            $table->decimal('opening_balance', 14, 2);
            $table->decimal('deposits', 14, 2)->default(0);
            $table->decimal('withdrawals', 14, 2)->default(0);
            $table->decimal('closing_balance', 14, 2);
            $table->string('currency', 3)->default('USD');
            $table->decimal('authority_limit', 14, 2);
            $table->enum('status', ['active', 'frozen', 'closed'])->default('active');
            $table->date('opened_on');
            $table->date('last_reconciled_on')->nullable();
            $table->string('manager_name');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'status']);
            $table->index('iban');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escrow_accounts');
    }
};