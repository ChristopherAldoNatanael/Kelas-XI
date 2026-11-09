<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (!Schema::hasColumn('services', 'service_category_id')) {
                $table->foreignId('service_category_id')
                    ->nullable()
                    ->after('category')
                    ->constrained('service_categories')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'service_category_id')) {
                $table->dropForeign(['service_category_id']);
                $table->dropColumn('service_category_id');
            }
        });
    }
};
