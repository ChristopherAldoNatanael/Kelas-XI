<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('empty_classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained('classrooms')->onDelete('cascade');
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
            $table->integer('period_number');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('reason', ['no_schedule', 'teacher_absent', 'class_cancelled', 'maintenance']);
            $table->text('notes')->nullable();
            $table->string('academic_year');
            $table->enum('semester', ['ganjil', 'genap']);
            $table->timestamps();

            // Unique constraint untuk mencegah duplikasi
            $table->unique(['classroom_id', 'day_of_week', 'period_number', 'academic_year', 'semester'], 'unique_empty_classroom');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empty_classrooms');
    }
};
