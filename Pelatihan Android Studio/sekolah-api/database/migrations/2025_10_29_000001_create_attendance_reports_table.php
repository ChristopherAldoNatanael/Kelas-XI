<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('schedule_id');
            $table->unsignedBigInteger('teacher_id');
            $table->enum('status', ['hadir', 'tidak_hadir']);
            $table->string('notes', 200)->nullable();
            $table->timestamp('reported_at');
            $table->timestamps();

            $table->index(['student_id', 'reported_at']);
            $table->index(['schedule_id']);
            $table->index(['teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_reports');
    }
};
