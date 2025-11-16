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
        // Update users table to match new database structure
        Schema::table('users', function (Blueprint $table) {
            // Rename 'nama' to 'name' if it exists
            if (Schema::hasColumn('users', 'nama')) {
                $table->renameColumn('nama', 'name');
            }

            // Drop columns that don't exist in new structure
            if (Schema::hasColumn('users', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('users', 'avatar')) {
                $table->dropColumn('avatar');
            }
            if (Schema::hasColumn('users', 'phone')) {
                $table->dropColumn('phone');
            }
            if (Schema::hasColumn('users', 'address')) {
                $table->dropColumn('address');
            }
            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropColumn('last_login_at');
            }
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }

            // Add new columns that should exist
            if (!Schema::hasColumn('users', 'mata_pelajaran')) {
                $table->string('mata_pelajaran')->nullable();
            }
            if (!Schema::hasColumn('users', 'is_banned')) {
                $table->boolean('is_banned')->default(false);
            }
        });

        // Update role enum values
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','siswa','kurikulum','kepala_sekolah') NOT NULL DEFAULT 'siswa'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Reverse the changes if needed
            if (Schema::hasColumn('users', 'name')) {
                $table->renameColumn('name', 'nama');
            }

            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->string('avatar')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->softDeletes();

            if (Schema::hasColumn('users', 'mata_pelajaran')) {
                $table->dropColumn('mata_pelajaran');
            }
            if (Schema::hasColumn('users', 'is_banned')) {
                $table->dropColumn('is_banned');
            }
        });

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','kurikulum','siswa','kepala-sekolah') NOT NULL DEFAULT 'siswa'");
    }
};
