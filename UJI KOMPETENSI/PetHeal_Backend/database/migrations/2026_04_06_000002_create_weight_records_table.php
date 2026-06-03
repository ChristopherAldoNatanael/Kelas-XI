<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Track pet weight over time for health monitoring.
     */
    public function up(): void
    {
        Schema::create('weight_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pet_id')->constrained()->onDelete('cascade');
            $table->decimal('weight', 5, 2)->comment('Weight in kg');
            $table->date('recorded_at')->comment('Date when weight was recorded');
            $table->text('notes')->nullable()->comment('Optional notes (diet change, health condition, etc.)');
            $table->timestamps();
            
            $table->index(['pet_id', 'recorded_at'], 'idx_pet_weight_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weight_records');
    }
};
