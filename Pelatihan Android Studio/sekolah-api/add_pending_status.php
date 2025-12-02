<?php

/**
 * Script to add 'pending' status to teacher_attendances ENUM
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Adding 'pending' Status to teacher_attendances ENUM ===\n\n";

try {
    // First, check current column definition
    echo "1. Checking current column definition...\n";
    $columns = DB::select("SHOW COLUMNS FROM teacher_attendances WHERE Field = 'status'");
    if (count($columns) > 0) {
        echo "   Current: " . $columns[0]->Type . "\n";
    }

    // Alter the ENUM to include 'pending'
    echo "\n2. Modifying ENUM to include 'pending'...\n";
    DB::statement("ALTER TABLE teacher_attendances MODIFY COLUMN status ENUM('pending', 'hadir', 'telat', 'tidak_hadir', 'diganti') DEFAULT 'tidak_hadir'");
    echo "   Done!\n";

    // Verify the change
    echo "\n3. Verifying the change...\n";
    $columns = DB::select("SHOW COLUMNS FROM teacher_attendances WHERE Field = 'status'");
    if (count($columns) > 0) {
        echo "   New: " . $columns[0]->Type . "\n";
    }

    // Update any empty status to 'pending'
    echo "\n4. Fixing any records with empty status...\n";
    $updated = DB::table('teacher_attendances')
        ->where('status', '')
        ->orWhereNull('status')
        ->update(['status' => 'pending']);
    echo "   Updated $updated records to 'pending'\n";

    // Show status summary
    echo "\n5. Current status distribution:\n";
    $summary = DB::table('teacher_attendances')
        ->select('status', DB::raw('COUNT(*) as count'))
        ->groupBy('status')
        ->get();

    foreach ($summary as $row) {
        $status = $row->status ?: '(empty)';
        echo "   - $status: {$row->count}\n";
    }

    echo "\n=== SUCCESS! 'pending' status has been added ===\n";
} catch (\Exception $e) {
    echo "\n=== ERROR ===\n";
    echo $e->getMessage() . "\n";
}
