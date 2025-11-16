<?php

use Illuminate\Support\Facades\DB;

// Test koneksi database
try {
    echo "🔍 Testing Database Connection...\n";
    echo "================================\n\n";

    // Test basic connection
    $connection = DB::connection();
    $pdo = $connection->getPdo();

    echo "✅ Database connection: SUCCESS\n";
    echo "📊 Database Driver: " . $connection->getDriverName() . "\n";

    // Get database info
    $dbName = $connection->getDatabaseName();
    echo "🗄️  Database Name: " . $dbName . "\n";

    // Test query
    $serverVersion = DB::select('SELECT VERSION() as version')[0]->version;
    echo "🖥️  Server Version: " . $serverVersion . "\n";

    // Check current tables
    $tables = DB::select("SHOW TABLES");
    echo "📋 Total Tables: " . count($tables) . "\n";

    if (count($tables) > 0) {
        echo "\n📝 Existing Tables:\n";
        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            echo "   - " . $tableName . "\n";
        }
    }

    // Test specific configuration
    echo "\n⚙️  Configuration:\n";
    echo "   Host: " . config('database.connections.mysql.host') . "\n";
    echo "   Port: " . config('database.connections.mysql.port') . "\n";
    echo "   Username: " . config('database.connections.mysql.username') . "\n";
    echo "   Charset: " . config('database.connections.mysql.charset') . "\n";

    echo "\n🎉 All tests PASSED! Database is working correctly with Laragon.\n";
} catch (Exception $e) {
    echo "❌ Database connection FAILED: " . $e->getMessage() . "\n";
    echo "\n💡 Suggestions:\n";
    echo "   1. Make sure Laragon MySQL service is running\n";
    echo "   2. Check .env database configuration\n";
    echo "   3. Verify database 'db_sekolah' exists\n";
}
