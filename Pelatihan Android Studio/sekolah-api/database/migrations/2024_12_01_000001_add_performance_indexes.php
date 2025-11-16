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
        // Add indexes for better performance on schedules table
        Schema::table('schedules', function (Blueprint $table) {
            // Composite index for class_id + day_of_week + status (most common query)
            if (!$this->indexExists('schedules', 'idx_schedules_class_day_status')) {
                $table->index(['class_id', 'day_of_week', 'status'], 'idx_schedules_class_day_status');
            }

            // Index for period_number ordering
            if (!$this->indexExists('schedules', 'idx_schedules_period')) {
                $table->index('period_number', 'idx_schedules_period');
            }

            // Index for status filtering
            if (!$this->indexExists('schedules', 'idx_schedules_status')) {
                $table->index('status', 'idx_schedules_status');
            }
        });

        // Add indexes for kehadiran table
        Schema::table('kehadiran', function (Blueprint $table) {
            // Composite index for submitted_by + tanggal (for riwayat queries)
            if (!$this->indexExists('kehadiran', 'idx_kehadiran_user_date')) {
                $table->index(['submitted_by', 'tanggal'], 'idx_kehadiran_user_date');
            }

            // Index for schedule_id + tanggal (for today status queries)
            if (!$this->indexExists('kehadiran', 'idx_kehadiran_schedule_date')) {
                $table->index(['schedule_id', 'tanggal'], 'idx_kehadiran_schedule_date');
            }
        });

        // Add indexes for users table
        Schema::table('users', function (Blueprint $table) {
            // Index for class_id (siswa queries)
            if (!$this->indexExists('users', 'idx_users_class_id')) {
                $table->index('class_id', 'idx_users_class_id');
            }

            // Index for role filtering
            if (!$this->indexExists('users', 'idx_users_role')) {
                $table->index('role', 'idx_users_role');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('idx_schedules_class_day_status');
            $table->dropIndex('idx_schedules_period');
            $table->dropIndex('idx_schedules_status');
        });

        Schema::table('kehadiran', function (Blueprint $table) {
            $table->dropIndex('idx_kehadiran_user_date');
            $table->dropIndex('idx_kehadiran_schedule_date');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_class_id');
            $table->dropIndex('idx_users_role');
        });
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $indexes = \DB::select("SHOW INDEX FROM {$table}");
        foreach ($indexes as $idx) {
            if ($idx->Key_name === $index) {
                return true;
            }
        }
        return false;
    }
};
