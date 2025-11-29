<?php

/**
 * Script untuk menambahkan kolom 'major' ke tabel classes
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== MENAMBAHKAN KOLOM 'major' KE TABEL CLASSES ===\n\n";

try {
    // Check if column already exists
    $hasColumn = Schema::hasColumn('classes', 'major');

    if ($hasColumn) {
        echo "✓ Kolom 'major' sudah ada di tabel classes\n";
    } else {
        echo "🔧 Menambahkan kolom 'major' ke tabel classes...\n";

        // Add the missing columns
        DB::statement("ALTER TABLE `classes` ADD COLUMN `level` INT NULL AFTER `kode_kelas`");
        DB::statement("ALTER TABLE `classes` ADD COLUMN `major` VARCHAR(255) NULL AFTER `level`");
        DB::statement("ALTER TABLE `classes` ADD COLUMN `academic_year` VARCHAR(255) NULL AFTER `major`");
        DB::statement("ALTER TABLE `classes` ADD COLUMN `homeroom_teacher_id` BIGINT UNSIGNED NULL AFTER `academic_year`");
        DB::statement("ALTER TABLE `classes` ADD COLUMN `capacity` INT NOT NULL DEFAULT 36 AFTER `homeroom_teacher_id`");
        DB::statement("ALTER TABLE `classes` ADD COLUMN `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER `capacity`");
        DB::statement("ALTER TABLE `classes` ADD COLUMN `deleted_at` TIMESTAMP NULL AFTER `status`");

        // Add foreign key constraint
        DB::statement("ALTER TABLE `classes` ADD CONSTRAINT `classes_homeroom_teacher_id_foreign` FOREIGN KEY (`homeroom_teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL");

        // Add indexes
        DB::statement("ALTER TABLE `classes` ADD INDEX `classes_homeroom_teacher_id_index` (`homeroom_teacher_id`)");
        DB::statement("ALTER TABLE `classes` ADD INDEX `classes_level_index` (`level`)");
        DB::statement("ALTER TABLE `classes` ADD INDEX `classes_academic_year_index` (`academic_year`)");
        DB::statement("ALTER TABLE `classes` ADD INDEX `classes_status_index` (`status`)");

        echo "✓ Berhasil menambahkan kolom 'major' dan kolom lainnya ke tabel classes\n";
    }

    // Verify the columns
    echo "\n🔍 Verifikasi kolom tabel classes:\n";
    $columns = Schema::getColumnListing('classes');
    foreach ($columns as $column) {
        echo "  - {$column}\n";
    }

    echo "\n✅ BERHASIL!\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
