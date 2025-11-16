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
        Schema::create('kehadiran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->onDelete('cascade');
            $table->date('tanggal');
            $table->boolean('guru_hadir')->default(false);
            $table->text('catatan')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Prevent duplicate submissions: one student can only submit once per schedule per day
            $table->unique(['schedule_id', 'tanggal', 'submitted_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kehadiran');
    }
};
