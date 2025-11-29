<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add composite indexes for schedules table (only if they don't exist)
        Schema::table('schedules', function (Blueprint $table) {
            if (!$this->indexExists('schedules', 'idx_schedules_class_day_period')) {
                $table->index(['class_id', 'day_of_week', 'period_number'], 'idx_schedules_class_day_period');
            }
            if (!$this->indexExists('schedules', 'idx_schedules_teacher_day')) {
                $table->index(['teacher_id', 'day_of_week'], 'idx_schedules_teacher_day');
            }
            if (!$this->indexExists('schedules', 'idx_schedules_status_class')) {
                $table->index(['status', 'class_id'], 'idx_schedules_status_class');
            }
        });

        // Add composite indexes for kehadiran table
        Schema::table('kehadiran', function (Blueprint $table) {
            if (!$this->indexExists('kehadiran', 'idx_kehadiran_submitted_date')) {
                $table->index(['submitted_by', 'tanggal'], 'idx_kehadiran_submitted_date');
            }
            if (!$this->indexExists('kehadiran', 'idx_kehadiran_schedule_date')) {
                $table->index(['schedule_id', 'tanggal'], 'idx_kehadiran_schedule_date');
            }
        });

        // Add composite indexes for users table
        Schema::table('users', function (Blueprint $table) {
            if (!$this->indexExists('users', 'idx_users_role_status')) {
                $table->index(['role', 'status'], 'idx_users_role_status');
            }
            if (!$this->indexExists('users', 'idx_users_class_role')) {
                $table->index(['class_id', 'role'], 'idx_users_class_role');
            }
        });

        // Add index for attendance table if it exists
        if (Schema::hasTable('attendance')) {
            Schema::table('attendance', function (Blueprint $table) {
                if (!$this->indexExists('attendance', 'idx_attendance_user_date')) {
                    $table->index(['user_id', 'date'], 'idx_attendance_user_date');
                }
                if (!$this->indexExists('attendance', 'idx_attendance_class_date')) {
                    $table->index(['class_id', 'date'], 'idx_attendance_class_date');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes from schedules table
        Schema::table('schedules', function (Blueprint $table) {
            if ($this->indexExists('schedules', 'idx_schedules_class_day_period')) {
                $table->dropIndex('idx_schedules_class_day_period');
            }
            if ($this->indexExists('schedules', 'idx_schedules_teacher_day')) {
                $table->dropIndex('idx_schedules_teacher_day');
            }
            if ($this->indexExists('schedules', 'idx_schedules_status_class')) {
                $table->dropIndex('idx_schedules_status_class');
            }
        });

        // Drop indexes from kehadiran table
        Schema::table('kehadiran', function (Blueprint $table) {
            if ($this->indexExists('kehadiran', 'idx_kehadiran_submitted_date')) {
                $table->dropIndex('idx_kehadiran_submitted_date');
            }
            if ($this->indexExists('kehadiran', 'idx_kehadiran_schedule_date')) {
                $table->dropIndex('idx_kehadiran_schedule_date');
            }
        });

        // Drop indexes from users table
        Schema::table('users', function (Blueprint $table) {
            if ($this->indexExists('users', 'idx_users_role_status')) {
                $table->dropIndex('idx_users_role_status');
            }
            if ($this->indexExists('users', 'idx_users_class_role')) {
                $table->dropIndex('idx_users_class_role');
            }
        });

        // Drop indexes from attendance table if it exists
        if (Schema::hasTable('attendance')) {
            Schema::table('attendance', function (Blueprint $table) {
                if ($this->indexExists('attendance', 'idx_attendance_user_date')) {
                    $table->dropIndex('idx_attendance_user_date');
                }
                if ($this->indexExists('attendance', 'idx_attendance_class_date')) {
                    $table->dropIndex('idx_attendance_class_date');
                }
            });
        }
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = \DB::connection();
        if ($connection->getDriverName() === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list({$table})");
            foreach ($indexes as $idx) {
                if ($idx->name === $indexName) {
                    return true;
                }
            }
            return false;
        } else {
            $indexes = $connection->select("SHOW INDEX FROM {$table}");
            foreach ($indexes as $idx) {
                if ($idx->Key_name === $indexName) {
                    return true;
                }
            }
            return false;
        }
    }
};
