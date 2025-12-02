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
        // Alter the ENUM to include 'pending' status
        // The new order: pending, hadir, telat, tidak_hadir, diganti
        DB::statement("ALTER TABLE teacher_attendances MODIFY COLUMN status ENUM('pending', 'hadir', 'telat', 'tidak_hadir', 'diganti') DEFAULT 'tidak_hadir'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First update any 'pending' status to 'tidak_hadir' before removing the enum value
        DB::statement("UPDATE teacher_attendances SET status = 'tidak_hadir' WHERE status = 'pending'");

        // Revert the ENUM back to original without 'pending'
        DB::statement("ALTER TABLE teacher_attendances MODIFY COLUMN status ENUM('hadir', 'telat', 'tidak_hadir', 'diganti') DEFAULT 'tidak_hadir'");
    }
};
