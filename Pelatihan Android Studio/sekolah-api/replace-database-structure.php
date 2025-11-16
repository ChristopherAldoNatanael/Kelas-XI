<?php

/**
 * Script untuk replace database structure dengan struktur baru
 * Jalankan dengan: php replace-database-structure.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=================================================\n";
echo "DATABASE STRUCTURE REPLACEMENT TOOL\n";
echo "=================================================\n\n";

try {
    // Read database configuration from .env
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) {
        throw new Exception("File .env tidak ditemukan!");
    }

    $envContent = file_get_contents($envFile);

    preg_match('/DB_HOST=(.*)/', $envContent, $hostMatch);
    preg_match('/DB_PORT=(.*)/', $envContent, $portMatch);
    preg_match('/DB_DATABASE=(.*)/', $envContent, $dbMatch);
    preg_match('/DB_USERNAME=(.*)/', $envContent, $userMatch);
    preg_match('/DB_PASSWORD=(.*)/', $envContent, $passMatch);

    $host = trim($hostMatch[1] ?? '127.0.0.1');
    $port = trim($portMatch[1] ?? '3306');
    $database = trim($dbMatch[1] ?? 'db_sekolah');
    $username = trim($userMatch[1] ?? 'root');
    $password = trim($passMatch[1] ?? '');

    echo "📋 Database Configuration:\n";
    echo "   Host: $host\n";
    echo "   Port: $port\n";
    echo "   Database: $database\n";
    echo "   Username: $username\n";
    echo "   Password: " . ($password ? "***" : "(kosong)") . "\n\n";

    // Test connection
    echo "✓ Testing database connection...\n";
    $dsn = "mysql:host=$host;port=$port;dbname=$database";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "   ✅ Connection successful!\n\n";

    // Create backup
    echo "📦 Creating database backup...\n";
    $backupFile = __DIR__ . "/backup_db_sekolah_" . date('Y-m-d_H-i-s') . ".sql";

    // Get all current tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    $backupContent = "-- Database backup created: " . date('Y-m-d H:i:s') . "\n";
    $backupContent .= "-- Database: $database\n\n";

    foreach ($tables as $table) {
        $backupContent .= "-- Table structure for table `$table`\n";
        $createStmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
        $backupContent .= $createStmt['Create Table'] . ";\n\n";

        $backupContent .= "-- Dumping data for table `$table`\n";
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $columns = array_keys($rows[0]);
            $backupContent .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";
            $values = [];
            foreach ($rows as $row) {
                $rowValues = [];
                foreach ($row as $value) {
                    $rowValues[] = $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                }
                $values[] = "(" . implode(', ', $rowValues) . ")";
            }
            $backupContent .= implode(",\n", $values) . ";\n\n";
        } else {
            $backupContent .= "-- No data in table `$table`\n\n";
        }
    }

    file_put_contents($backupFile, $backupContent);
    echo "   ✅ Backup created: $backupFile\n\n";

    // Confirm replacement
    echo "⚠️  WARNING: This will replace your entire database structure!\n";
    echo "   The backup above can be used to restore if needed.\n\n";
    echo "Continue with database replacement? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $confirm = trim(strtolower($line));
    fclose($handle);

    if ($confirm !== 'yes' && $confirm !== 'y') {
        echo "Operation cancelled.\n";
        echo "Backup saved at: $backupFile\n";
        exit(0);
    }

    // Begin transaction
    echo "\n🔄 Starting database replacement...\n";
    $pdo->beginTransaction();

    try {
        // Drop all existing tables (in reverse order to handle foreign keys)
        echo "   🗑️  Dropping existing tables...\n";
        $tablesToDrop = array_reverse($tables);

        foreach ($tablesToDrop as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
            echo "     ✓ Dropped: $table\n";
        }

        // Execute new schema
        echo "\n   🏗️  Creating new database structure...\n";
        $sqlFile = __DIR__ . '/database_schema_new.sql';

        if (!file_exists($sqlFile)) {
            throw new Exception("File database_schema_new.sql tidak ditemukan!");
        }

        $sqlContent = file_get_contents($sqlFile);

        // Remove comments and split by semicolon
        $sqlStatements = array_filter(array_map('trim', explode(';', $sqlContent)), function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt) && !preg_match('/^\/\*!/', $stmt);
        });

        $executedStatements = 0;
        foreach ($sqlStatements as $statement) {
            if (trim($statement)) {
                try {
                    $pdo->exec($statement);
                    $executedStatements++;
                } catch (PDOException $e) {
                    // Skip if it's just informational (like SET statements)
                    if (strpos($e->getMessage(), 'Variable') === false &&
                        strpos($e->getMessage(), 'read only') === false) {
                        throw $e;
                    }
                }
            }
        }

        echo "     ✓ Executed $executedStatements SQL statements\n";

        // Verify tables were created
        echo "\n   🔍 Verifying new structure...\n";
        $newTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        $expectedTables = [
            'cache', 'cache_locks', 'classes', 'failed_jobs', 'jobs',
            'job_batches', 'migrations', 'password_reset_tokens',
            'personal_access_tokens', 'schedules', 'sessions', 'subjects',
            'teachers', 'teacher_attendances', 'users'
        ];

        $missingTables = array_diff($expectedTables, $newTables);
        if (!empty($missingTables)) {
            throw new Exception("Missing tables after creation: " . implode(', ', $missingTables));
        }

        echo "     ✓ All expected tables created:\n";
        foreach ($expectedTables as $table) {
            echo "       - $table\n";
        }

        // Clear Laravel caches
        echo "\n   🧹 Clearing Laravel caches...\n";
        try {
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            \Illuminate\Support\Facades\Artisan::call('route:clear');
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            echo "     ✓ All Laravel caches cleared\n";
        } catch (Exception $e) {
            echo "     ⚠️  Cache clearing warning: " . $e->getMessage() . "\n";
        }

        $pdo->commit();
        echo "\n✅ DATABASE REPLACEMENT COMPLETED SUCCESSFULLY!\n\n";

        echo "=================================================\n";
        echo "SUMMARY\n";
        echo "=================================================\n\n";

        echo "📦 Backup created: $backupFile\n";
        echo "🔄 Old tables dropped: " . count($tables) . "\n";
        echo "🆕 New tables created: " . count($expectedTables) . "\n";
        echo "📊 SQL statements executed: $executedStatements\n\n";

        echo "🎯 NEXT STEPS:\n";
        echo "1. Update your Laravel models to match new structure\n";
        echo "2. Test your application functionality\n";
        echo "3. If needed, restore from backup: mysql -u root -p $database < $backupFile\n\n";

        echo "📋 IMPORTANT NOTES:\n";
        echo "- Users table now has default roles: admin, siswa, kurikulum, kepala_sekolah\n";
        echo "- Schedules table uses 'hari' (Indonesian days) instead of 'day_of_week'\n";
        echo "- All timestamps are in UTC timezone\n";
        echo "- Database is now ready for the new structure!\n\n";

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";

    if (isset($backupFile) && file_exists($backupFile)) {
        echo "\n📦 Backup available at: $backupFile\n";
        echo "You can restore the original database with:\n";
        echo "mysql -u root -p $database < $backupFile\n";
    }

    exit(1);
}

echo "=================================================\n";
