<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Mengubah default status menjadi 'pending' dan memastikan semua status tersedia
     */
    public function up(): void
    {
        // Update ENUM dengan semua status yang dibutuhkan dan set default ke 'pending'
        DB::statement("ALTER TABLE teacher_attendances MODIFY COLUMN status ENUM('pending', 'hadir', 'telat', 'tidak_hadir', 'diganti', 'izin') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert ke default 'tidak_hadir'
        DB::statement("ALTER TABLE teacher_attendances MODIFY COLUMN status ENUM('pending', 'hadir', 'telat', 'tidak_hadir', 'diganti', 'izin') DEFAULT 'tidak_hadir'");
    }
};
