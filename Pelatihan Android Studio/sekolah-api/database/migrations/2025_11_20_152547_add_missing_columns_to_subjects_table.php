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
        Schema::table('subjects', function (Blueprint $table) {
            // Check if columns already exist before adding them
            if (!Schema::hasColumn('subjects', 'category')) {
                $table->enum('category', ['wajib', 'peminatan', 'mulok'])->default('wajib');
            }
            if (!Schema::hasColumn('subjects', 'description')) {
                $table->text('description')->nullable();
            }
            if (!Schema::hasColumn('subjects', 'credit_hours')) {
                $table->integer('credit_hours')->default(2);
            }
            if (!Schema::hasColumn('subjects', 'semester')) {
                $table->integer('semester')->default(1);
            }
            if (!Schema::hasColumn('subjects', 'status')) {
                $table->enum('status', ['active', 'inactive'])->default('active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            // Drop the added columns if they exist
            if (Schema::hasColumn('subjects', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('subjects', 'semester')) {
                $table->dropColumn('semester');
            }
            if (Schema::hasColumn('subjects', 'credit_hours')) {
                $table->dropColumn('credit_hours');
            }
            if (Schema::hasColumn('subjects', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('subjects', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
