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
        Schema::table('schedules', function (Blueprint $table) {
            // Drop the existing foreign key that references users
            $table->dropForeign(['guru_id']);

            // Add new foreign key that references teachers
            $table->foreign('guru_id')->references('id')->on('teachers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            // Drop the foreign key that references teachers
            $table->dropForeign(['guru_id']);

            // Add back the foreign key that references users
            $table->foreign('guru_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
