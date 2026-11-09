<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            // Hanya tambah kolom yang belum ada
            if (!Schema::hasColumn('clinics', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('description');
            }
            if (!Schema::hasColumn('clinics', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (!Schema::hasColumn('clinics', 'rating')) {
                $table->decimal('rating', 3, 2)->nullable()->after('longitude');
            }
            if (!Schema::hasColumn('clinics', 'reviews_count')) {
                $table->unsignedInteger('reviews_count')->default(0)->after('rating');
            }
            if (!Schema::hasColumn('clinics', 'opening_time')) {
                $table->time('opening_time')->nullable()->after('reviews_count');
            }
            if (!Schema::hasColumn('clinics', 'closing_time')) {
                $table->time('closing_time')->nullable()->after('opening_time');
            }
            if (!Schema::hasColumn('clinics', 'operating_days')) {
                $table->json('operating_days')->nullable()->after('closing_time')
                    ->comment('Array of days: ["monday","tuesday",...] or ["all"]');
            }
            if (!Schema::hasColumn('clinics', 'website')) {
                $table->string('website')->nullable()->after('operating_days');
            }
            if (!Schema::hasColumn('clinics', 'featured_image_path')) {
                $table->string('featured_image_path', 512)->nullable()->after('website');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumnIfExists([
                'latitude', 'longitude', 'rating', 'reviews_count',
                'opening_time', 'closing_time', 'operating_days',
                'website', 'featured_image_path'
            ]);
        });
    }
};
