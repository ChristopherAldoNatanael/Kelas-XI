<?php

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
