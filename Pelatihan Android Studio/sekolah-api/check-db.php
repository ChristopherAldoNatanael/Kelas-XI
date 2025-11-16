#!/usr/bin/env php
<?php

define('LARAVEL_START', microtime(true));

// Register the Composer autoloader...
require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel and return the Application instance...
$app = require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

try {
    echo "🔍 Testing Database Connection...\n";
    echo "================================\n\n";

    // Test basic connection
    $connection = DB::connection();

    echo "✅ Database connection: SUCCESS\n";
    echo "📊 Database Driver: " . $connection->getDriverName() . "\n";

    // Get database info
    $dbName = $connection->getDatabaseName();
    echo "🗄️  Database Name: " . $dbName . "\n";

    // Test query
    $result = DB::select('SELECT VERSION() as version, @@port as port, @@datadir as datadir');
    $info = $result[0];

    echo "🖥️  Server Version: " . $info->version . "\n";
    echo "📡 Server Port: " . $info->port . "\n";
    echo "📁 Data Directory: " . $info->datadir . "\n";

    // Check if this is Laragon (typical Laragon paths)
    if (stripos($info->datadir, 'laragon') !== false) {
        echo "🎯 CONFIRMED: Using Laragon MySQL!\n";
    } elseif (stripos($info->datadir, 'xampp') !== false) {
        echo "⚠️  WARNING: Still using XAMPP MySQL!\n";
    } else {
        echo "ℹ️  MySQL Source: " . $info->datadir . "\n";
    }

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

    // Test users table if exists
    $userCount = 0;
    try {
        $userCount = DB::table('users')->count();
        echo "\n👥 Users in database: " . $userCount . "\n";
    } catch (Exception $e) {
        echo "\n📋 Users table not found (need to migrate)\n";
    }

    echo "\n⚙️  Configuration:\n";
    echo "   Host: " . config('database.connections.mysql.host') . "\n";
    echo "   Port: " . config('database.connections.mysql.port') . "\n";
    echo "   Username: " . config('database.connections.mysql.username') . "\n";
    echo "   Database: " . config('database.connections.mysql.database') . "\n";

    echo "\n🎉 Database connection is working correctly!\n";
} catch (Exception $e) {
    echo "❌ Database connection FAILED: " . $e->getMessage() . "\n";
    echo "\n💡 Check your Laragon MySQL service and .env configuration\n";
}
