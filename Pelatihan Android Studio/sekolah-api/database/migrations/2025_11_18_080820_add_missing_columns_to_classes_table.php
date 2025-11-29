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
        Schema::table('classes', function (Blueprint $table) {
            if (!Schema::hasColumn('classes', 'level')) {
                $table->integer('level')->nullable()->after('kode_kelas');
            }
            if (!Schema::hasColumn('classes', 'major')) {
                $table->string('major')->nullable()->after('level');
            }
            if (!Schema::hasColumn('classes', 'academic_year')) {
                $table->string('academic_year')->nullable()->after('major');
            }
            if (!Schema::hasColumn('classes', 'homeroom_teacher_id')) {
                $table->foreignId('homeroom_teacher_id')->nullable()->constrained('users')->onDelete('set null')->after('academic_year');
            }
            if (!Schema::hasColumn('classes', 'capacity')) {
                $table->integer('capacity')->default(36)->after('homeroom_teacher_id');
            }
            if (!Schema::hasColumn('classes', 'status')) {
                $table->enum('status', ['active', 'inactive'])->default('active')->after('capacity');
            }
            if (!Schema::hasColumn('classes', 'deleted_at')) {
                $table->softDeletes()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropSoftDeletes();
            if (Schema::hasColumn('classes', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('classes', 'capacity')) {
                $table->dropColumn('capacity');
            }
            if (Schema::hasColumn('classes', 'homeroom_teacher_id')) {
                $table->dropForeign(['homeroom_teacher_id']);
                $table->dropColumn('homeroom_teacher_id');
            }
            if (Schema::hasColumn('classes', 'academic_year')) {
                $table->dropColumn('academic_year');
            }
            if (Schema::hasColumn('classes', 'major')) {
                $table->dropColumn('major');
            }
            if (Schema::hasColumn('classes', 'level')) {
                $table->dropColumn('level');
            }
        });
    }
};
