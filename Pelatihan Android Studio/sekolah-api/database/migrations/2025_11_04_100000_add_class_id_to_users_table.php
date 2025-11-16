<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambahkan class_id untuk user dengan role 'siswa'
     * Sehingga setiap siswa otomatis terhubung dengan kelasnya
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Tambah kolom class_id setelah kolom role
            $table->foreignId('class_id')
                ->nullable()
                ->after('role')
                ->constrained('classes')
                ->onDelete('set null')
                ->comment('Kelas untuk user dengan role siswa');

            // Tambah index untuk performa query
            $table->index(['role', 'class_id'], 'users_role_class_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_class_index');
            $table->dropForeign(['class_id']);
            $table->dropColumn('class_id');
        });
    }
};
