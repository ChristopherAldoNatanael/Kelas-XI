<?php

/**
 * Script untuk mengubah default status menjadi 'pending' di teacher_attendances table
 *
 * Jalankan: php set_pending_default.php
 */

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "Setting default status to 'pending'...\n\n";

    // Check current column definition
    $columnInfo = DB::select("SHOW COLUMNS FROM teacher_attendances WHERE Field = 'status'")[0] ?? null;

    if ($columnInfo) {
        echo "Current column definition:\n";
        echo "Type: " . $columnInfo->Type . "\n";
        echo "Default: " . ($columnInfo->Default ?? 'NULL') . "\n\n";
    }

    // Alter the column to have 'pending' as default
    DB::statement("ALTER TABLE teacher_attendances MODIFY COLUMN status ENUM('pending', 'hadir', 'telat', 'tidak_hadir', 'diganti', 'izin') DEFAULT 'pending'");

    echo "SUCCESS! Default status changed to 'pending'\n\n";

    // Verify the change
    $columnInfo = DB::select("SHOW COLUMNS FROM teacher_attendances WHERE Field = 'status'")[0] ?? null;

    if ($columnInfo) {
        echo "New column definition:\n";
        echo "Type: " . $columnInfo->Type . "\n";
        echo "Default: " . ($columnInfo->Default ?? 'NULL') . "\n";
    }

    echo "\n=== DONE ===\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
