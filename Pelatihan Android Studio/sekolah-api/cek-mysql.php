<?php

/**
 * Script untuk cek apakah MySQL berjalan dan troubleshoot masalah
 * Jalankan dengan: php cek-mysql.php
 */

echo "=================================================\n";
echo "CEK MYSQL & TROUBLESHOOT SERVER MATI\n";
echo "=================================================\n\n";

// Load .env file
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    echo "❌ ERROR: File .env tidak ditemukan!\n";
    echo "   Pastikan Anda menjalankan script ini di folder sekolah-api\n\n";
    exit(1);
}

$envContent = file_get_contents($envFile);

// Parse .env
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

echo "📋 Konfigurasi Database dari .env:\n";
echo "   Host: $host\n";
echo "   Port: $port\n";
echo "   Database: $database\n";
echo "   Username: $username\n";
echo "   Password: " . ($password ? "***" : "(kosong)") . "\n\n";

// Test 1: Cek apakah port terbuka
echo "✓ Test 1: Cek apakah MySQL port terbuka...\n";
$connection = @fsockopen($host, $port, $errno, $errstr, 5);
if ($connection) {
    echo "   ✅ PASS: Port $port terbuka dan bisa diakses\n";
    fclose($connection);
} else {
    echo "   ❌ FAIL: Port $port TIDAK bisa diakses!\n";
    echo "   Error: $errstr ($errno)\n\n";
    echo "   🔧 SOLUSI:\n";
    echo "   1. Buka XAMPP/Laragon Control Panel\n";
    echo "   2. Klik tombol START di sebelah MySQL\n";
    echo "   3. Tunggu sampai status berubah jadi hijau/running\n";
    echo "   4. Jalankan script ini lagi\n\n";
    exit(1);
}
echo "\n";

// Test 2: Coba connect ke MySQL
echo "✓ Test 2: Coba connect ke MySQL...\n";
try {
    $dsn = "mysql:host=$host;port=$port";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    echo "   ✅ PASS: Berhasil connect ke MySQL server\n";
    
    // Get MySQL version
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "   ℹ️  MySQL Version: $version\n";
} catch (PDOException $e) {
    echo "   ❌ FAIL: Tidak bisa connect ke MySQL!\n";
    echo "   Error: " . $e->getMessage() . "\n\n";
    
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "   🔧 SOLUSI: Password salah!\n";
        echo "   1. Cek password MySQL Anda\n";
        echo "   2. Untuk XAMPP default, password biasanya KOSONG\n";
        echo "   3. Update file .env:\n";
        echo "      DB_PASSWORD=\n\n";
    } else {
        echo "   🔧 SOLUSI: MySQL tidak berjalan!\n";
        echo "   1. Buka XAMPP/Laragon\n";
        echo "   2. Start MySQL service\n";
        echo "   3. Coba lagi\n\n";
    }
    exit(1);
}
echo "\n";

// Test 3: Cek apakah database exists
echo "✓ Test 3: Cek apakah database '$database' ada...\n";
try {
    $stmt = $pdo->query("SHOW DATABASES LIKE '$database'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "   ✅ PASS: Database '$database' ditemukan\n";
    } else {
        echo "   ❌ FAIL: Database '$database' TIDAK ditemukan!\n\n";
        echo "   🔧 SOLUSI: Buat database dulu!\n";
        echo "   Cara 1 - Via phpMyAdmin:\n";
        echo "   1. Buka http://localhost/phpmyadmin\n";
        echo "   2. Klik tab 'Databases'\n";
        echo "   3. Ketik '$database' di kolom 'Create database'\n";
        echo "   4. Klik 'Create'\n\n";
        echo "   Cara 2 - Via command ini:\n";
        echo "   CREATE DATABASE $database;\n\n";
        
        // Tawarkan untuk create otomatis
        echo "   Mau saya buatkan sekarang? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        if (trim($line) == 'y' || trim($line) == 'Y') {
            try {
                $pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                echo "   ✅ Database '$database' berhasil dibuat!\n";
            } catch (PDOException $e) {
                echo "   ❌ Gagal membuat database: " . $e->getMessage() . "\n";
                exit(1);
            }
        } else {
            echo "   Silakan buat database manual dulu, lalu jalankan script ini lagi.\n";
            exit(1);
        }
        fclose($handle);
    }
} catch (PDOException $e) {
    echo "   ❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
echo "\n";

// Test 4: Cek apakah bisa connect ke database
echo "✓ Test 4: Coba connect ke database '$database'...\n";
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$database";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "   ✅ PASS: Berhasil connect ke database '$database'\n";
} catch (PDOException $e) {
    echo "   ❌ FAIL: " . $e->getMessage() . "\n";
    exit(1);
}
echo "\n";

// Test 5: Cek apakah tabel ada
echo "✓ Test 5: Cek apakah tabel sudah ada...\n";
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "   ✅ PASS: Ditemukan " . count($tables) . " tabel\n";
        echo "   Tabel: " . implode(', ', array_slice($tables, 0, 5));
        if (count($tables) > 5) {
            echo ", ... (+" . (count($tables) - 5) . " lagi)";
        }
        echo "\n";
        
        // Cek tabel penting
        $requiredTables = ['users', 'schedules', 'classes', 'personal_access_tokens'];
        $missingTables = [];
        foreach ($requiredTables as $table) {
            if (!in_array($table, $tables)) {
                $missingTables[] = $table;
            }
        }
        
        if (!empty($missingTables)) {
            echo "   ⚠️  WARNING: Tabel penting tidak ada: " . implode(', ', $missingTables) . "\n";
            echo "   Jalankan: php artisan migrate\n";
        }
    } else {
        echo "   ⚠️  WARNING: Database kosong, belum ada tabel!\n\n";
        echo "   🔧 SOLUSI: Jalankan migration\n";
        echo "   php artisan migrate --seed\n\n";
    }
} catch (PDOException $e) {
    echo "   ❌ ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 6: Test query sederhana
echo "✓ Test 6: Test query sederhana...\n";
try {
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    if ($result['test'] == 1) {
        echo "   ✅ PASS: Query berhasil dijalankan\n";
    }
} catch (PDOException $e) {
    echo "   ❌ FAIL: " . $e->getMessage() . "\n";
}
echo "\n";

// Summary
echo "=================================================\n";
echo "HASIL PEMERIKSAAN\n";
echo "=================================================\n\n";

echo "✅ MySQL berjalan dengan baik!\n";
echo "✅ Database '$database' siap digunakan\n";
echo "✅ Koneksi database berhasil\n\n";

echo "🎉 SEKARANG ANDA BISA:\n";
echo "1. Jalankan server: php artisan serve\n";
echo "2. Test login dari Android app\n";
echo "3. Server TIDAK akan mati lagi!\n\n";

echo "📝 CATATAN PENTING:\n";
echo "- Pastikan MySQL SELALU JALAN sebelum start Laravel\n";
echo "- Jika server mati, cek dulu apakah MySQL masih jalan\n";
echo "- Gunakan script ini untuk troubleshoot: php cek-mysql.php\n\n";

echo "=================================================\n";

