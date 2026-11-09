<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_categories')) {
            Schema::create('service_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('icon')->nullable()->comment('Material icon name');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Insert default categories
            \Illuminate\Support\Facades\DB::table('service_categories')->insert([
                ['name' => 'Vaksinasi', 'slug' => 'vaksinasi', 'icon' => 'vaccines', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Pemeriksaan Umum', 'slug' => 'pemeriksaan-umum', 'icon' => 'medical_services', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Operasi & Bedah', 'slug' => 'operasi-bedah', 'icon' => 'surgical', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Grooming', 'slug' => 'grooming', 'icon' => 'content_cut', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Rawat Inap', 'slug' => 'rawat-inap', 'icon' => 'hotel', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Pemeriksaan Gigi', 'slug' => 'pemeriksaan-gigi', 'icon' => 'dentistry', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Laboratorium', 'slug' => 'laboratorium', 'icon' => 'biotech', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Radiologi', 'slug' => 'radiologi', 'icon' => 'radiology', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Lainnya', 'slug' => 'lainnya', 'icon' => 'more_horiz', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_categories');
    }
};
