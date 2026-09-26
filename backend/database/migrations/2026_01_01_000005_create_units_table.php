<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('unit_number'); // 101, A-12
            $table->integer('floor')->default(1);
            $table->decimal('sqft', 10, 2);
            $table->integer('bedrooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->decimal('rent_amount', 10, 2);
            $table->enum('unit_type', ['office', 'retail', 'apartment', 'studio', 'warehouse'])->default('office');
            $table->enum('status', ['vacant', 'occupied', 'maintenance', 'reserved'])->default('vacant');
            $table->text('amenities')->nullable(); // json string
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['building_id', 'unit_number']);
            $table->index('status');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
