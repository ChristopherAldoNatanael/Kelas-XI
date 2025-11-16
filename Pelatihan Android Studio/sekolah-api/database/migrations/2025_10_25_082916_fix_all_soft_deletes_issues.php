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
        // Fix soft deletes for all tables using change() method
        $tables = [
            'users' => ['deleted_at'],
            'subjects' => ['deleted_at'],
            'classes' => ['deleted_at'],
            'teachers' => ['deleted_at'],
            'classrooms' => ['deleted_at'],
            'schedules' => ['deleted_at'],
            'gurus' => ['deleted_at']
        ];

        foreach ($tables as $tableName => $columns) {
            if (Schema::hasTable($tableName)) {
                foreach ($columns as $column) {
                    if (!Schema::hasColumn($tableName, $column)) {
                        Schema::table($tableName, function (Blueprint $table) use ($column) {
                            if ($column === 'deleted_at') {
                                $table->timestamp($column)->nullable();
                            }
                        });
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['users', 'subjects', 'classes', 'teachers', 'classrooms', 'schedules', 'gurus'];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('deleted_at');
                });
            }
        }
    }
};
