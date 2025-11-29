<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== FIXING CLASSES TIMESTAMPS ===\n\n";

try {
    // Fix null timestamps in classes table
    $classesUpdated = DB::table('classes')
        ->whereNull('created_at')
        ->orWhereNull('updated_at')
        ->update([
            'created_at' => now(),
            'updated_at' => now()
        ]);

    echo "✓ Updated timestamps for $classesUpdated classes\n";

    // Show current classes data
    $classes = DB::table('classes')->get();
    echo "\nCurrent classes:\n";
    foreach ($classes as $class) {
        echo "  - {$class->nama_kelas} ({$class->kode_kelas}) - Created: " .
            ($class->created_at ? $class->created_at : 'NULL') . "\n";
    }

    echo "\n✅ Classes timestamps fixed successfully!\n";
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
