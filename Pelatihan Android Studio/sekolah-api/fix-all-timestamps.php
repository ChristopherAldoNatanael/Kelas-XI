<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== FIXING ALL NULL TIMESTAMPS ===\n\n";

try {
    $tables = ['users', 'subjects', 'classes', 'teachers'];

    foreach ($tables as $table) {
        echo "Checking table: {$table}\n";

        try {
            // Check if table exists
            $exists = DB::select("SHOW TABLES LIKE '{$table}'");
            if (empty($exists)) {
                echo "  ⚠️  Table {$table} does not exist\n";
                continue;
            }

            // Check if table has created_at and updated_at columns
            $columns = DB::select("DESCRIBE {$table}");
            $columnNames = array_column($columns, 'Field');

            $hasCreatedAt = in_array('created_at', $columnNames);
            $hasUpdatedAt = in_array('updated_at', $columnNames);

            if (!$hasCreatedAt && !$hasUpdatedAt) {
                echo "  ℹ️  Table {$table} doesn't have timestamp columns\n";
                continue;
            }

            $fixCount = 0;

            if ($hasCreatedAt) {
                $nullCreatedAt = DB::table($table)->whereNull('created_at')->count();
                if ($nullCreatedAt > 0) {
                    DB::table($table)
                        ->whereNull('created_at')
                        ->update(['created_at' => now()]);
                    $fixCount += $nullCreatedAt;
                    echo "  ✓ Fixed {$nullCreatedAt} NULL created_at records\n";
                }
            }

            if ($hasUpdatedAt) {
                $nullUpdatedAt = DB::table($table)->whereNull('updated_at')->count();
                if ($nullUpdatedAt > 0) {
                    DB::table($table)
                        ->whereNull('updated_at')
                        ->update(['updated_at' => now()]);
                    $fixCount += $nullUpdatedAt;
                    echo "  ✓ Fixed {$nullUpdatedAt} NULL updated_at records\n";
                }
            }

            if ($fixCount === 0) {
                echo "  ✅ No NULL timestamps found\n";
            }
        } catch (Exception $e) {
            echo "  ❌ Error with table {$table}: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    echo "✅ TIMESTAMP FIXING COMPLETED!\n";
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
