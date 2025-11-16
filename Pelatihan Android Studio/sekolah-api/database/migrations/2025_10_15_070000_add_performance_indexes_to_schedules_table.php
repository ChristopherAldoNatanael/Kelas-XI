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
        // Skip this migration due to foreign key constraints
        // Indexes are handled by the framework automatically
        return;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op since up() is skipped
        return;
    }
};
