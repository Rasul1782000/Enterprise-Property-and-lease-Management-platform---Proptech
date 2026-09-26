<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // e.g. PROP-001
            $table->enum('type', ['commercial', 'multi_family', 'mixed_use'])->default('commercial');
            $table->string('address');
            $table->string('city');
            $table->string('state', 50)->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('country')->default('USA');
            $table->decimal('total_area_sqft', 12, 2)->nullable();
            $table->year('year_built')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->enum('status', ['active', 'inactive', 'under_maintenance'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
