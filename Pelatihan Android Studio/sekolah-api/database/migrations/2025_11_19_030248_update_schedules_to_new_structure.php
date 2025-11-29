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
        Schema::table('schedules', function (Blueprint $table) {
            // Drop old columns
            $table->dropForeign(['class_id']);
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['teacher_id']);
            $table->dropForeign(['classroom_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['class_id', 'subject_id', 'teacher_id', 'classroom_id', 'day_of_week', 'period_number', 'start_time', 'end_time', 'academic_year', 'semester', 'status', 'notes', 'created_by', 'updated_by']);

            // Add new columns
            $table->enum('hari', ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']);
            $table->string('kelas', 10);
            $table->string('mata_pelajaran');
            $table->unsignedBigInteger('guru_id');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('ruang')->nullable();
            $table->foreign('guru_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            //
        });
    }
};
