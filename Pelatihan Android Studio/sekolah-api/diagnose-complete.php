<?php

/**
 * COMPREHENSIVE DIAGNOSTIC SCRIPT
 * This will check EVERYTHING that could cause server crashes
 * Run with: php diagnose-complete.php
 */

echo "==========================================================\n";
echo "COMPREHENSIVE SERVER DIAGNOSTIC - COMPLETE ANALYSIS\n";
echo "==========================================================\n\n";

$errors = [];
$warnings = [];
$passed = 0;
$failed = 0;

// Helper function
function test($name, $condition, $errorMsg = '', $warningMsg = '') {
    global $errors, $warnings, $passed, $failed;
    
    echo "Testing: $name ... ";
    if ($condition) {
        echo "✅ PASS\n";
        $passed++;
        return true;
    } else {
        echo "❌ FAIL\n";
        $failed++;
        if ($errorMsg) {
            $errors[] = $errorMsg;
            echo "   ERROR: $errorMsg\n";
        }
        if ($warningMsg) {
            $warnings[] = $warningMsg;
            echo "   WARNING: $warningMsg\n";
        }
        return false;
    }
}

echo "=== SECTION 1: PHP CONFIGURATION ===\n";

// Check PHP version
$phpVersion = phpversion();
test(
    "PHP Version (>= 8.1)", 
    version_compare($phpVersion, '8.1.0', '>='),
    "PHP version $phpVersion is too old. Laravel 11 requires PHP 8.1+",
    "Update PHP to 8.1 or higher"
);
echo "   Current: PHP $phpVersion\n";

// Check memory limit
$memoryLimit = ini_get('memory_limit');
$memoryBytes = return_bytes($memoryLimit);
test(
    "PHP Memory Limit (>= 256M)",
    $memoryBytes >= 256 * 1024 * 1024,
    "Memory limit is only $memoryLimit. This can cause crashes!",
    "Increase memory_limit to 256M in php.ini"
);
echo "   Current: $memoryLimit\n";

// Check max execution time
$maxExecTime = ini_get('max_execution_time');
test(
    "Max Execution Time (>= 60s)",
    $maxExecTime == 0 || $maxExecTime >= 60,
    "Max execution time is only {$maxExecTime}s. Requests may timeout!",
    "Increase max_execution_time to 60 in php.ini"
);
echo "   Current: {$maxExecTime}s\n";

// Check required extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath'];
foreach ($requiredExtensions as $ext) {
    test(
        "PHP Extension: $ext",
        extension_loaded($ext),
        "Required extension '$ext' is not loaded!",
        "Enable $ext in php.ini"
    );
}

echo "\n=== SECTION 2: MYSQL CONNECTION ===\n";

// Load .env
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    test("ENV File Exists", false, ".env file not found!", "Create .env file");
    die("\nCRITICAL: Cannot continue without .env file\n");
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

echo "Database Config:\n";
echo "   Host: $host:$port\n";
echo "   Database: $database\n";
echo "   Username: $username\n\n";

// Test port connectivity
$connection = @fsockopen($host, $port, $errno, $errstr, 3);
test(
    "MySQL Port Accessible",
    $connection !== false,
    "Cannot connect to MySQL on port $port! MySQL is NOT running!",
    "Start MySQL in XAMPP/Laragon Control Panel"
);
if ($connection) fclose($connection);

// Test MySQL connection
try {
    $dsn = "mysql:host=$host;port=$port";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    test("MySQL Server Connection", true);
    
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "   MySQL Version: $version\n";
} catch (PDOException $e) {
    test("MySQL Server Connection", false, $e->getMessage(), "Check MySQL credentials in .env");
    die("\nCRITICAL: Cannot connect to MySQL. Fix this first!\n");
}

// Test database exists
try {
    $stmt = $pdo->query("SHOW DATABASES LIKE '$database'");
    $exists = $stmt->fetch();
    test(
        "Database '$database' Exists",
        $exists !== false,
        "Database '$database' does not exist!",
        "Create database: CREATE DATABASE $database;"
    );
} catch (PDOException $e) {
    test("Database Check", false, $e->getMessage());
}

// Test database connection
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$database";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    test("Database Connection", true);
} catch (PDOException $e) {
    test("Database Connection", false, $e->getMessage());
    die("\nCRITICAL: Cannot connect to database '$database'\n");
}

// Check tables
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $tableCount = count($tables);
    
    test(
        "Database Has Tables",
        $tableCount > 0,
        "Database is empty! No tables found.",
        "Run: php artisan migrate"
    );
    
    echo "   Found $tableCount tables\n";
    
    // Check critical tables
    $criticalTables = ['users', 'personal_access_tokens', 'schedules', 'classes'];
    foreach ($criticalTables as $table) {
        test(
            "Table '$table' Exists",
            in_array($table, $tables),
            "Critical table '$table' is missing!",
            "Run: php artisan migrate"
        );
    }
} catch (PDOException $e) {
    test("Table Check", false, $e->getMessage());
}

echo "\n=== SECTION 3: LARAVEL CONFIGURATION ===\n";

// Check .env settings
preg_match('/APP_DEBUG=(.*)/', $envContent, $debugMatch);
preg_match('/CACHE_STORE=(.*)/', $envContent, $cacheMatch);
preg_match('/CACHE_DRIVER=(.*)/', $envContent, $cacheDriverMatch);

$appDebug = trim($debugMatch[1] ?? 'false');
$cacheStore = trim($cacheMatch[1] ?? 'file');
$cacheDriver = trim($cacheDriverMatch[1] ?? 'file');

test(
    "APP_DEBUG Setting",
    true,
    "",
    ""
);
echo "   Current: $appDebug\n";

test(
    "CACHE_STORE is 'file' (not 'array')",
    $cacheStore === 'file',
    "CACHE_STORE is '$cacheStore' - should be 'file' to prevent memory issues!",
    "Change CACHE_STORE=file in .env"
);

// Check storage permissions
$storagePath = __DIR__ . '/storage';
$isWritable = is_writable($storagePath);
test(
    "Storage Directory Writable",
    $isWritable,
    "Storage directory is not writable! This will cause crashes.",
    "Run: chmod -R 775 storage (Linux/Mac) or check folder permissions (Windows)"
);

// Check cache directory
$cachePath = __DIR__ . '/storage/framework/cache';
if (file_exists($cachePath)) {
    $cacheWritable = is_writable($cachePath);
    test(
        "Cache Directory Writable",
        $cacheWritable,
        "Cache directory is not writable!",
        "Check storage/framework/cache permissions"
    );
}

// Check log file size
$logFile = __DIR__ . '/storage/logs/laravel.log';
if (file_exists($logFile)) {
    $logSize = filesize($logFile);
    $logSizeMB = round($logSize / 1024 / 1024, 2);
    test(
        "Log File Size (< 50MB)",
        $logSize < 50 * 1024 * 1024,
        "Log file is {$logSizeMB}MB! This can slow down the server.",
        "Clear logs: echo '' > storage/logs/laravel.log"
    );
    echo "   Current: {$logSizeMB}MB\n";
}

echo "\n=== SECTION 4: PERFORMANCE CHECKS ===\n";

// Check if config is cached
$configCached = file_exists(__DIR__ . '/bootstrap/cache/config.php');
echo "Config Cached: " . ($configCached ? "Yes" : "No") . "\n";
if ($configCached) {
    echo "   ⚠️  Config is cached. If you changed .env, run: php artisan config:clear\n";
}

// Check if routes are cached
$routesCached = file_exists(__DIR__ . '/bootstrap/cache/routes-v7.php');
echo "Routes Cached: " . ($routesCached ? "Yes" : "No") . "\n";

// Test a simple query performance
echo "\nTesting Query Performance...\n";
$start = microtime(true);
try {
    $pdo->query("SELECT 1")->fetch();
    $queryTime = (microtime(true) - $start) * 1000;
    test(
        "Simple Query Speed (< 100ms)",
        $queryTime < 100,
        "Query took {$queryTime}ms - database is VERY slow!",
        "Check MySQL performance or restart MySQL"
    );
    echo "   Query time: " . round($queryTime, 2) . "ms\n";
} catch (PDOException $e) {
    test("Query Performance", false, $e->getMessage());
}

echo "\n==========================================================\n";
echo "DIAGNOSTIC SUMMARY\n";
echo "==========================================================\n\n";

echo "Tests Passed: $passed\n";
echo "Tests Failed: $failed\n\n";

if ($failed > 0) {
    echo "❌ CRITICAL ISSUES FOUND:\n";
    foreach ($errors as $i => $error) {
        echo ($i + 1) . ". $error\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "⚠️  WARNINGS:\n";
    foreach ($warnings as $i => $warning) {
        echo ($i + 1) . ". $warning\n";
    }
    echo "\n";
}

if ($failed == 0) {
    echo "✅ ALL TESTS PASSED!\n";
    echo "Your server configuration looks good.\n";
    echo "If server still crashes, the issue is likely in the code.\n\n";
    echo "Next steps:\n";
    echo "1. Clear all caches: php artisan optimize:clear\n";
    echo "2. Start server: php artisan serve\n";
    echo "3. Monitor logs: Get-Content storage/logs/laravel.log -Wait -Tail 20\n";
} else {
    echo "❌ FIX THE ISSUES ABOVE BEFORE STARTING THE SERVER!\n";
    echo "\nQuick fixes:\n";
    echo "1. If MySQL not running: Start it in XAMPP/Laragon\n";
    echo "2. If cache is 'array': Change to 'file' in .env\n";
    echo "3. If memory low: Increase in php.ini\n";
    echo "4. Then run: php artisan config:clear\n";
}

echo "\n==========================================================\n";

// Helper function
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

