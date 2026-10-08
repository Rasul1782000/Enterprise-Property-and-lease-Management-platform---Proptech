<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->string('status')->default('active');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->string('name')->nullable();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('code')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->string('type')->default('fixed');
            $table->text('escalation_clause')->nullable();
            $table->integer('renewal_options')->default(0);
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->index(['unit_id', 'status']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('type')->default('rent');
            $table->date('issue_date')->nullable();
            $table->string('currency', 3)->default('USD');
        });

        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->timestamps();
        });

        foreach (DB::table('tenants')->whereNull('code')->get() as $tenant) {
            DB::table('tenants')->where('id', $tenant->id)->update([
                'code' => 'TEN-'.str_pad($tenant->id, 5, '0', STR_PAD_LEFT),
            ]);
        }

        DB::table('invoices')->whereNull('issue_date')->update([
            'issue_date' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_line_items');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['type', 'issue_date', 'currency']);
        });

        Schema::table('leases', function (Blueprint $table) {
            $table->dropIndex(['unit_id', 'status']);
            $table->dropColumn(['type', 'escalation_clause', 'renewal_options', 'signed_at', 'terminated_at']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['code', 'tax_id', 'emergency_contact_name', 'emergency_contact_phone']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn(['address', 'city', 'state', 'zip', 'status']);
        });
    }
};