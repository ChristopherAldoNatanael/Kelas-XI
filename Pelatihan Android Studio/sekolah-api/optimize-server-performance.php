<?php

/**
 * SCRIPT OPTIMASI SERVER - Jalankan sekali saja untuk fix server crash
 *
 * Cara pakai: php optimize-server-performance.php
 */

echo "🚀 OPTIMASI SERVER PERFORMANCE - START\n\n";

// 1. Clear semua cache Laravel
echo "1️⃣  Clearing Laravel cache...\n";
exec('php artisan cache:clear');
exec('php artisan config:clear');
exec('php artisan route:clear');
exec('php artisan view:clear');
echo "   ✅ Cache cleared\n\n";

// 2. Optimize autoloader
echo "2️⃣  Optimizing autoloader...\n";
exec('composer dump-autoload --optimize');
echo "   ✅ Autoloader optimized\n\n";

// 3. Cache config untuk production
echo "3️⃣  Caching configurations...\n";
exec('php artisan config:cache');
exec('php artisan route:cache');
echo "   ✅ Config cached\n\n";

// 4. Optimize database
echo "4️⃣  Optimizing database tables...\n";
try {
    require __DIR__ . '/vendor/autoload.php';
    $app = require_once __DIR__ . '/bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

    $pdo = DB::connection()->getPdo();

    // Optimize tables
    $tables = DB::select('SHOW TABLES');
    $dbName = DB::getDatabaseName();

    foreach ($tables as $table) {
        $tableName = $table->{"Tables_in_$dbName"};
        DB::statement("OPTIMIZE TABLE `$tableName`");
        echo "   ✅ Optimized table: $tableName\n";
    }

    echo "\n   ✅ All database tables optimized\n\n";
} catch (\Exception $e) {
    echo "   ⚠️  Database optimization skipped: " . $e->getMessage() . "\n\n";
}

// 5. Delete old tokens (prevent memory leak)
echo "5️⃣  Cleaning up old tokens...\n";
try {
    $deleted = DB::table('personal_access_tokens')
        ->where('created_at', '<', now()->subDays(30))
        ->delete();
    echo "   ✅ Deleted $deleted old tokens\n\n";
} catch (\Exception $e) {
    echo "   ⚠️  Token cleanup skipped: " . $e->getMessage() . "\n\n";
}

// 6. Delete old cache entries
echo "6️⃣  Cleaning up cache database...\n";
try {
    $deleted = DB::table('cache')->where('expiration', '<', time())->delete();
    echo "   ✅ Deleted $deleted expired cache entries\n\n";
} catch (\Exception $e) {
    echo "   ⚠️  Cache cleanup skipped: " . $e->getMessage() . "\n\n";
}

// 7. Restart queue workers (if any)
echo "7️⃣  Restarting queue workers...\n";
exec('php artisan queue:restart');
echo "   ✅ Queue workers restarted\n\n";

echo "========================================\n";
echo "✅ OPTIMASI SELESAI!\n";
echo "========================================\n";
echo "Server sekarang lebih ringan dan stabil.\n";
echo "Jalankan script ini setiap minggu untuk performa optimal.\n\n";
