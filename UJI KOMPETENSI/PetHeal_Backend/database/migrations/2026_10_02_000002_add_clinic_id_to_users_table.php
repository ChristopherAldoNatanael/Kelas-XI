<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('clinic_id')
                ->nullable()
                ->after('role')
                ->constrained('clinics')
                ->nullOnDelete();

            $table->index(['clinic_id', 'role']);
        });

        // Promote existing admin users to super_admin (super_admin has clinic_id = NULL)
        DB::table('users')
            ->where('role', 'admin')
            ->update(['role' => 'super_admin', 'clinic_id' => null]);
    }

    public function down(): void
    {
        // Revert super_admin back to admin
        DB::table('users')
            ->where('role', 'super_admin')
            ->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->dropIndex(['clinic_id', 'role']);
            $table->dropColumn('clinic_id');
        });
    }
};