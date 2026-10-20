<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_assets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('asset_type');
            $table->string('title');
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('property_name')->nullable();
            $table->string('unit_code')->nullable();
            $table->string('holder');
            $table->string('safe_id');
            $table->string('safe_location');
            $table->enum('status', ['sealed', 'active', 'released', 'expired'])->default('sealed');
            $table->date('deposited_on');
            $table->date('expires_on')->nullable();
            $table->decimal('value', 14, 2)->default(0);
            $table->unsignedInteger('document_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'status']);
            $table->index('asset_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_assets');
    }
};