<?php

/**
 * SIMPLE REPLACE DATABASE STRUCTURE SCRIPT
 * Mengubah struktur database menggunakan database_schema_complete.sql
 */

echo "=== SIMPLE DATABASE REPLACEMENT ===\n";
echo "Using: database_schema_complete.sql\n\n";

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) {
        throw new Exception("File .env tidak ditemukan!");
    }

    $envContent = file_get_contents($envFile);
    preg_match('/DB_DATABASE=(.*)/', $envContent, $dbMatch);
    $database = trim($dbMatch[1] ?? 'db_sekolah');

    echo "Target Database: {$database}\n";

    // Backup existing database
    $backupFile = __DIR__ . "/backup_before_complete_" . date('Y-m-d_H-i-s') . ".sql";
    echo "Creating backup: {$backupFile}\n";

    $tables = DB::select("SHOW TABLES");
    $backupContent = "-- Backup created: " . date('Y-m-d H:i:s') . "\nUSE {$database};\n\n";

    foreach ($tables as $table) {
        $tableName = current($table);
        $backupContent .= "-- Table: {$tableName}\n";
        $columns = DB::select("SHOW CREATE TABLE {$tableName}");
        $backupContent .= $columns[0]->{'Create Table'} . ";\n\n";

        $data = DB::select("SELECT * FROM {$tableName}");
        if ($data) {
            $columnNames = array_keys((array)$data[0]);
            $backupContent .= "INSERT INTO `{$tableName}` (`" . implode('`, `', $columnNames) . "`) VALUES\n";
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
    echo "✓ Backup created successfully\n\n";

    // Confirm replacement
    echo "⚠️  This will REPLACE ALL DATA with complete schema!\n";
    echo "Confirm? (yes/no): ";
    $handle = fopen("php://stdin", "r");
    $confirmation = trim(fgets($handle));
    fclose($handle);

    if ($confirmation !== 'yes') {
        echo "Cancelled.\n";
        exit(0);
    }

    // Drop all tables
    echo "Dropping existing tables...\n";
    DB::statement("SET FOREIGN_KEY_CHECKS = 0");
    foreach ($tables as $table) {
        $tableName = current($table);
        DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
        echo "  ✓ Dropped: {$tableName}\n";
    }
    DB::statement("SET FOREIGN_KEY_CHECKS = 1");

    // Execute complete schema
    echo "\nExecuting complete schema...\n";
    $schemaFile = __DIR__ . '/database_schema_complete.sql';

    if (!file_exists($schemaFile)) {
        throw new Exception("File database_schema_complete.sql tidak ditemukan!");
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
                if (!str_contains($e->getMessage(), 'Variable')) {
                    throw $e;
                }
            }
        }
    }

    echo "✓ Executed {$executed} SQL statements\n";

    // Clear caches
    try {
        \Artisan::call('cache:clear');
        \Artisan::call('config:clear');
        \Artisan::call('route:clear');
        \Artisan::call('view:clear');
    } catch (Exception $e) {
        echo "Warning: Cache clearing failed: " . $e->getMessage() . "\n";
    }

    echo "\n✅ DATABASE COMPLETELY REPLACED!\n";
    echo "Backup saved to: {$backupFile}\n\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Database configuration
$host = '127.0.0.1';
$port = 3306;
$database = 'db_sekolah';
$username = 'root';
$password = '';

echo "=== SIMPLE REPLACE DATABASE STRUCTURE ===\n";
echo "Database: {$database}\n";
echo "Host: {$host}:{$port}\n";
echo "Username: {$username}\n";
echo "==========================================\n\n";

echo "⚠️  PERINGATAN: Script ini akan MENGHAPUS SEMUA DATA yang ada!\n";
echo "   Pastikan Anda sudah backup database jika diperlukan.\n\n";

try {
    // Koneksi ke MySQL
    $pdo = new PDO("mysql:host={$host};port={$port}", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

    echo "✓ Berhasil terhubung ke MySQL\n";

    // Use database
    $pdo->exec("USE `{$database}`");
    echo "✓ Menggunakan database '{$database}'\n";

    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Get all existing tables and drop them
    echo "\n🗑️  Menghapus semua tabel yang ada...\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        echo "  ✓ Dropped table: {$table}\n";
    }

    echo "✓ Semua tabel berhasil dihapus\n\n";

    // Now create new structure
    echo "🔨 Membuat struktur tabel baru...\n\n";    // Read and execute the SQL file
    $sqlFile = __DIR__ . '/database_schema_new.sql';

    if (!file_exists($sqlFile)) {
        throw new Exception("File database_schema_new.sql tidak ditemukan!");
    }

    $sql = file_get_contents($sqlFile);

    // Remove MySQL-specific commands that might cause issues
    $sql = preg_replace('/\/\*!\d+.*?\*\//', '', $sql);
    $sql = preg_replace('/START TRANSACTION;/', '', $sql);
    $sql = preg_replace('/COMMIT;/', '', $sql);

    // Split by delimiter and execute each statement
    $statements = explode(';', $sql);

    foreach ($statements as $statement) {
        $statement = trim($statement);

        // Skip empty statements, comments, and MySQL-specific commands
        if (
            empty($statement) ||
            strpos($statement, '--') === 0 ||
            strpos($statement, '/*') === 0 ||
            preg_match('/^(SET |START |COMMIT)/i', $statement)
        ) {
            continue;
        }

        try {
            $result = $pdo->exec($statement);

            // Log table creation
            if (preg_match('/CREATE TABLE `?(\w+)`?/i', $statement, $matches)) {
                $tableName = $matches[1];
                echo "  ✓ Created table: {$tableName}\n";
            }
            // Log data insertion
            else if (preg_match('/INSERT INTO `?(\w+)`?/i', $statement, $matches)) {
                $tableName = $matches[1];
                echo "  ✓ Inserted data into: {$tableName}\n";
            }
            // Log index/constraint operations
            else if (preg_match('/ALTER TABLE `?(\w+)`?/i', $statement, $matches)) {
                $tableName = $matches[1];
                echo "  ✓ Modified table: {$tableName}\n";
            }
            // Log other operations
            else if (preg_match('/DROP TABLE/i', $statement)) {
                echo "  ✓ Executed: DROP TABLE\n";
            }
        } catch (PDOException $e) {
            // Only show errors for important operations
            if (preg_match('/CREATE TABLE|INSERT INTO|ALTER TABLE/i', $statement)) {
                echo "  ❌ Error executing: " . substr($statement, 0, 50) . "...\n";
                echo "     " . $e->getMessage() . "\n";
            }
        }
    }

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "\n✅ BERHASIL!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🗄️ Database: {$database}\n";
    echo "📊 Struktur baru berhasil dibuat\n";
    echo "👥 Data sample users telah ditambahkan\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    // Verify structure
    echo "\n🔍 VERIFIKASI STRUKTUR:\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 Total tabel: " . count($tables) . "\n";
    foreach ($tables as $table) {
        echo "   • {$table}\n";
    }

    // Check users
    echo "\n👥 DATA USERS:\n";
    $users = $pdo->query("SELECT id, name, email, role FROM users")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $user) {
        echo "   • ID: {$user['id']}, Name: {$user['name']}, Email: {$user['email']}, Role: {$user['role']}\n";
    }
} catch (PDOException $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . __FILE__ . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 SELESAI! Database siap digunakan.\n";
