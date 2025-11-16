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
        // Update schedules table to match new database structure
        Schema::table('schedules', function (Blueprint $table) {
            // Drop old columns if they exist
            $oldColumns = [
                'class_id',
                'subject_id',
                'teacher_id',
                'classroom_id',
                'day_of_week',
                'period_number',
                'start_time',
                'end_time',
                'academic_year',
                'semester',
                'status',
                'notes',
                'created_by',
                'updated_by',
                'deleted_at'
            ];

            foreach ($oldColumns as $column) {
                if (Schema::hasColumn('schedules', $column)) {
                    $table->dropColumn($column);
                }
            }

            // Add new columns that should exist
            if (!Schema::hasColumn('schedules', 'hari')) {
                $table->enum('hari', ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']);
            }
            if (!Schema::hasColumn('schedules', 'kelas')) {
                $table->string('kelas', 10);
            }
            if (!Schema::hasColumn('schedules', 'mata_pelajaran')) {
                $table->string('mata_pelajaran');
            }
            if (!Schema::hasColumn('schedules', 'guru_id')) {
                 $table->unsignedBigInteger('guru_id');
                 $table->foreign('guru_id')->references('id')->on('teachers')->onDelete('cascade');
             }
            if (!Schema::hasColumn('schedules', 'jam_mulai')) {
                $table->time('jam_mulai');
            }
            if (!Schema::hasColumn('schedules', 'jam_selesai')) {
                $table->time('jam_selesai');
            }
            if (!Schema::hasColumn('schedules', 'ruang')) {
                $table->string('ruang')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            // Remove new columns
            $newColumns = ['hari', 'kelas', 'mata_pelajaran', 'guru_id', 'jam_mulai', 'jam_selesai', 'ruang'];

            foreach ($newColumns as $column) {
                if (Schema::hasColumn('schedules', $column)) {
                    $table->dropColumn($column);
                }
            }

            // Add back old columns
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedBigInteger('classroom_id')->nullable();
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']);
            $table->integer('period_number');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('academic_year');
            $table->integer('semester');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
        });
    }
};
