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
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'nama')) {
                $table->renameColumn('nama', 'name');
            }
            if ($this->indexExists('users', 'idx_users_role_status')) {
                $table->dropIndex('idx_users_role_status');
            }
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
            if (!Schema::hasColumn('users', 'mata_pelajaran')) {
                $table->string('mata_pelajaran')->nullable();
            }
            if (!Schema::hasColumn('users', 'is_banned')) {
                $table->boolean('is_banned')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::table('users', function (Blueprint $table) {
        //     if (Schema::hasColumn('users', 'name')) {
        //         $table->renameColumn('name', 'nama');
        //     }
        //     $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
        //     $table->string('avatar')->nullable();
        //     $table->string('phone')->nullable();
        //     $table->text('address')->nullable();
        //     $table->timestamp('last_login_at')->nullable();
        //     $table->softDeletes();
        //     if (Schema::hasColumn('users', 'mata_pelajaran')) {
        //         $table->dropColumn('mata_pelajaran');
        //     }
        //     if (Schema::hasColumn('users', 'is_banned')) {
        //         $table->dropColumn('is_banned');
        //     }
        // });
    }

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
