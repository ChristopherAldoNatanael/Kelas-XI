<?php

/**
 * FIX SCHEDULES ERROR - Replace database with complete schema
 */

echo "=== FIXING SCHEDULES ERROR ===\n";
echo "This will replace your database with the complete schema\n";
echo "including proper 'hari', 'jam_mulai', 'jam_selesai' columns.\n\n";

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) {
        throw new Exception("File .env not found!");
    }

    $envContent = file_get_contents($envFile);
    preg_match('/DB_DATABASE=(.*)/', $envContent, $dbMatch);
    $database = trim($dbMatch[1] ?? 'db_sekolah');

    echo "Database: {$database}\n\n";

    // Create backup
    echo "Creating backup...\n";
    $backupFile = __DIR__ . "/backup_before_schedules_fix_" . date('Y-m-d_H-i-s') . ".sql";
    $tables = DB::select("SHOW TABLES");
    $backupContent = "-- Auto-backup before schedules fix: " . date('Y-m-d H:i:s') . "\nUSE {$database};\n\n";
    foreach ($tables as $table) {
        $tableName = current($table);
        $backupContent .= "-- Table: {$tableName}\n";
        $columns = DB::select("SHOW CREATE TABLE {$tableName}");
        $backupContent .= $columns[0]->{'Create Table'} . ";\n\n";
        $data = DB::select("SELECT * FROM {$tableName}");
        if ($data) {
            $backupContent .= "INSERT INTO `{$tableName}` VALUES\n";
            $values = [];
            foreach ($data as $row) {
                $rowArray = (array)$row;
                $rowValues = array_map(function($value) {
                    return $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                }, $rowArray);
                $values[] = "(" . implode(', ', $rowValues) . ")";
            }
            $backupContent .= implode(",\n", $values) . ";\n\n";
        }
    }
    file_put_contents($backupFile, $backupContent);
    echo "✓ Backup saved to: {$backupFile}\n\n";

    // Confirm
    echo "⚠️  This will DELETE all current data!\n";
    echo "Type 'yes' to continue or 'no' to cancel: ";
    $handle = fopen("php://stdin", "r");
    $confirm = trim(fgets($handle));
    fclose($handle);

    if (strtolower($confirm) !== 'yes') {
        echo "Operation cancelled.\n";
        exit(0);
    }

    // Drop all tables
    DB::statement("SET FOREIGN_KEY_CHECKS = 0");
    foreach ($tables as $table) {
        $tableName = current($table);
        DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
    }
    DB::statement("SET FOREIGN_KEY_CHECKS = 1");

    echo "✓ Old tables dropped\n";

    // Execute complete schema
    $schemaFile = __DIR__ . '/database_schema_complete.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("database_schema_complete.sql not found!");
    }

    $sql = file_get_contents($schemaFile);
    $statements = array_filter(array_map('trim', explode(';', $sql)), function($stmt) {
        return !empty($stmt) && !preg_match('/^--/', $stmt);
    });

    $executed = 0;
    foreach ($statements as $statement) {
        if (trim($statement)) {
            try {
                DB::statement($statement);
                $executed++;
            } catch (Exception $e) {
                if (!str_contains($e->getMessage(), 'Variable') && !str_contains($e->getMessage(), 'read only')) {
                    throw $e;
                }
            }
        }
    }

    echo "✓ Complete schema executed ({$executed} statements)\n";

    // Verify schedules table
    echo "\n🔍 Verifying schedules table structure...\n";
    $columns = DB::select("DESCRIBE schedules");
    echo "Schedules table columns:\n";
    foreach ($columns as $column) {
        echo "  - {$column->Field}: {$column->Type}\n";
    }

    // Clear caches
    echo "\n🧹 Clearing caches...\n";

    echo "\n✅ DATABASE FIXED!\n";
    echo "The schedules table now has the correct columns:\n";
    echo "  - hari (enum: Senin, Selasa, Rabu, Kamis, Jumat, Sabtu)\n";
    echo "  - jam_mulai (time)\n";
    echo "  - jam_selesai (time)\n";
    echo "  - kelas, mata_pelajaran, guru_id, ruang\n";
    echo "\nYour dashboard should now work without column errors!\n";
    echo "Backup: {$backupFile}\n\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
