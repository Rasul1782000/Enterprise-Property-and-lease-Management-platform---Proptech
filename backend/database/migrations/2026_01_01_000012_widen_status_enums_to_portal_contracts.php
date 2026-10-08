<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('status', 32)->default('vacant')->change();
            $table->string('unit_type', 32)->default('office')->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('status', 32)->default('draft')->change();
        });

        DB::table('units')->where('status', 'maintenance')->update(['status' => 'under_maintenance']);
        DB::table('units')->whereIn('unit_type', ['apartment', 'studio'])->update(['unit_type' => 'residential']);
        DB::table('invoices')->where('status', 'pending')->update(['status' => 'sent']);
    }

    public function down(): void
    {
        DB::table('units')->where('status', 'under_maintenance')->update(['status' => 'maintenance']);
        DB::table('invoices')->where('status', 'sent')->update(['status' => 'pending']);

        Schema::table('units', function (Blueprint $table) {
            $table->enum('status', ['vacant', 'occupied', 'maintenance', 'reserved'])->default('vacant')->change();
            $table->enum('unit_type', ['office', 'retail', 'apartment', 'studio', 'warehouse'])->default('office')->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled', 'partial'])->default('pending')->change();
        });
    }
};