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
        Schema::table('medical_records', function (Blueprint $table) {
            $table->decimal('cost', 12, 2)->default(0)->after('notes');
            $table->decimal('treatment_cost', 12, 2)->default(0)->after('cost');
            $table->decimal('medicine_cost', 12, 2)->default(0)->after('treatment_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropColumn(['cost', 'treatment_cost', 'medicine_cost']);
        });
    }
};
