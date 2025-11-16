<?php

/**
 * Script manual untuk clear cache Laravel
 * Jalankan dengan: php manual-optimize.php
 */

echo "=== MANUAL LARAVEL OPTIMIZATION ===\n\n";

$cacheDirectories = [
    'bootstrap/cache' => ['*.php', '!.gitignore'],
    'storage/framework/cache/data' => ['*'],
    'storage/framework/views' => ['*.php'],
    'storage/framework/sessions' => ['*', '!.gitignore'],
    'storage/logs' => ['*.log'],
];

echo "🗑️  Clearing cache directories...\n\n";

foreach ($cacheDirectories as $dir => $patterns) {
    $fullPath = __DIR__ . '/' . $dir;
    echo "Cleaning: $dir\n";

    if (!is_dir($fullPath)) {
        echo "  ⚠️  Directory not found, skipping\n\n";
        continue;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    $deletedCount = 0;
    foreach ($files as $fileinfo) {
        if ($fileinfo->isFile()) {
            $filename = $fileinfo->getFilename();

            // Skip .gitignore files
            if ($filename === '.gitignore') {
                continue;
            }

            // Delete the file
            if (unlink($fileinfo->getRealPath())) {
                $deletedCount++;
            }
        }
    }

    echo "  ✅ Deleted $deletedCount files\n\n";
}

echo "\n=== PERFORMANCE TIPS ===\n\n";

echo "After clearing cache, run these commands:\n\n";
echo "  php artisan config:cache\n";
echo "  php artisan route:cache\n";
echo "  php artisan view:cache\n\n";

echo "To improve server performance:\n\n";
echo "  1. ✓ Use proper indexes on database tables\n";
echo "  2. ✓ Enable OPcache in php.ini\n";
echo "  3. ✓ Use Redis for session/cache (faster than file-based)\n";
echo "  4. ✓ Use Queue for heavy tasks\n";
echo "  5. ✓ Enable Laravel Octane for 10x performance boost\n";
echo "  6. ✓ Monitor with: tail -f storage/logs/laravel.log\n\n";

echo "=== OPTIMIZATION COMPLETE ===\n";
