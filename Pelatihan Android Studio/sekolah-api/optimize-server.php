<?php

/**
 * Script untuk mengoptimasi server Laravel
 * Jalankan dengan: php optimize-server.php
 */

echo "=== LARAVEL SERVER OPTIMIZATION ===\n\n";

// Change to sekolah-api directory
chdir(__DIR__);

$commands = [
    'Clear all caches' => 'php artisan optimize:clear',
    'Cache routes' => 'php artisan route:cache',
    'Cache config' => 'php artisan config:cache',
    'Cache views' => 'php artisan view:cache',
    'Cache events' => 'php artisan event:cache',
    'Optimize autoloader' => 'composer dump-autoload -o',
];

foreach ($commands as $description => $command) {
    echo "📦 $description...\n";
    echo "   Command: $command\n";

    $output = [];
    $returnVar = 0;
    exec($command . ' 2>&1', $output, $returnVar);

    if ($returnVar === 0) {
        echo "   ✅ Success\n";
    } else {
        echo "   ⚠️  Warning (returned $returnVar)\n";
        echo "   Output: " . implode("\n   ", $output) . "\n";
    }
    echo "\n";
}

echo "=== CHECKING INDEXES ===\n\n";

// Check if performance indexes exist
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    // Check schedules table indexes
    $indexes = DB::select("SHOW INDEX FROM schedules WHERE Key_name LIKE '%class%' OR Key_name LIKE '%day%'");

    if (empty($indexes)) {
        echo "⚠️  Performance indexes not found on schedules table\n";
        echo "   Run: php artisan migrate to add indexes\n\n";
    } else {
        echo "✅ Performance indexes found:\n";
        foreach ($indexes as $index) {
            echo "   - {$index->Key_name} on column {$index->Column_name}\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "⚠️  Could not check indexes: " . $e->getMessage() . "\n\n";
}

echo "=== MEMORY & PERFORMANCE SETTINGS ===\n\n";

// Check PHP memory limit
$memoryLimit = ini_get('memory_limit');
echo "PHP Memory Limit: $memoryLimit\n";
if (preg_match('/(\d+)/', $memoryLimit, $matches)) {
    $limit = (int)$matches[1];
    if ($limit < 256) {
        echo "⚠️  Consider increasing to 256M or higher for better performance\n";
    } else {
        echo "✅ Memory limit is adequate\n";
    }
}

// Check max execution time
$maxTime = ini_get('max_execution_time');
echo "\nPHP Max Execution Time: {$maxTime}s\n";
if ($maxTime > 0 && $maxTime < 60) {
    echo "⚠️  Consider increasing to 60s or higher\n";
} else {
    echo "✅ Execution time is adequate\n";
}

echo "\n=== RECOMMENDATIONS ===\n\n";

echo "✓ Use Redis for caching (edit .env: CACHE_DRIVER=redis)\n";
echo "✓ Use Queue for async tasks (edit .env: QUEUE_CONNECTION=database)\n";
echo "✓ Enable OPcache in php.ini for better PHP performance\n";
echo "✓ Use nginx + php-fpm instead of 'php artisan serve' in production\n";
echo "✓ Consider using Laravel Octane for extreme performance\n";
echo "✓ Monitor logs: tail -f storage/logs/laravel.log\n";

echo "\n=== OPTIMIZATION COMPLETE ===\n\n";

echo "🚀 Server is now optimized!\n";
echo "   Restart your server: php artisan serve --host=0.0.0.0 --port=8000\n\n";
