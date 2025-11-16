<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add missing indexes for foreign keys
        Schema::table('classes', function (Blueprint $table) {
            $table->index(['major', 'status'], 'classes_major_status_index');
            $table->index(['level', 'major', 'status'], 'classes_level_major_status_index');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->index(['class_id', 'day_of_week', 'status'], 'schedules_class_day_status_index');
            $table->index(['teacher_id', 'day_of_week', 'status'], 'schedules_teacher_day_status_index');
            $table->index(['subject_id', 'status'], 'schedules_subject_status_index');
        });

        Schema::table('attendance_reports', function (Blueprint $table) {
            $table->index(['student_id', 'schedule_id'], 'attendance_student_schedule_index');
            $table->index(['reported_at'], 'attendance_reported_at_index');
            $table->index(['status', 'reported_at'], 'attendance_status_date_index');
        });

        // Composite indexes for common query patterns
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'is_read', 'created_at'], 'notifications_user_read_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropIndex('classes_major_status_index');
            $table->dropIndex('classes_level_major_status_index');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('schedules_class_day_status_index');
            $table->dropIndex('schedules_teacher_day_status_index');
            $table->dropIndex('schedules_subject_status_index');
        });

        Schema::table('attendance_reports', function (Blueprint $table) {
            $table->dropIndex('attendance_student_schedule_index');
            $table->dropIndex('attendance_reported_at_index');
            $table->dropIndex('attendance_status_date_index');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_read_created_index');
        });
    }
};
