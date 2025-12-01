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
        Schema::table('teacher_attendances', function (Blueprint $table) {
            // Index for tanggal (most common filter)
            if (!$this->indexExists('teacher_attendances', 'idx_teacher_attendances_tanggal')) {
                $table->index('tanggal', 'idx_teacher_attendances_tanggal');
            }

            // Index for guru_id (teacher filtering)
            if (!$this->indexExists('teacher_attendances', 'idx_teacher_attendances_guru_id')) {
                $table->index('guru_id', 'idx_teacher_attendances_guru_id');
            }

            // Index for status (status filtering)
            if (!$this->indexExists('teacher_attendances', 'idx_teacher_attendances_status')) {
                $table->index('status', 'idx_teacher_attendances_status');
            }

            // Composite index for tanggal + guru_id (common query pattern)
            if (!$this->indexExists('teacher_attendances', 'idx_teacher_attendances_date_guru')) {
                $table->index(['tanggal', 'guru_id'], 'idx_teacher_attendances_date_guru');
            }

            // Composite index for schedule_id + tanggal (for schedule-based queries)
            if (!$this->indexExists('teacher_attendances', 'idx_teacher_attendances_schedule_date')) {
                $table->index(['schedule_id', 'tanggal'], 'idx_teacher_attendances_schedule_date');
            }

            // Index for created_at (for ordering)
            if (!$this->indexExists('teacher_attendances', 'idx_teacher_attendances_created_at')) {
                $table->index('created_at', 'idx_teacher_attendances_created_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->dropIndex('idx_teacher_attendances_tanggal');
            $table->dropIndex('idx_teacher_attendances_guru_id');
            $table->dropIndex('idx_teacher_attendances_status');
            $table->dropIndex('idx_teacher_attendances_date_guru');
            $table->dropIndex('idx_teacher_attendances_schedule_date');
            $table->dropIndex('idx_teacher_attendances_created_at');
        });
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = \DB::connection();
        if ($connection->getDriverName() === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list({$table})");
            foreach ($indexes as $idx) {
                if ($idx->name === $index) {
                    return true;
                }
            }
            return false;
        } else {
            $indexes = $connection->select("SHOW INDEX FROM {$table}");
            foreach ($indexes as $idx) {
                if ($idx->Key_name === $index) {
                    return true;
                }
            }
            return false;
        }
    }
};
