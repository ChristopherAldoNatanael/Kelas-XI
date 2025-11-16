<?php

define('LARAVEL_START', microtime(true));

// Register the Composer autoloader...
require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel and return the Application instance...
$app = require_once __DIR__ . '/bootstrap/app.php';

echo "🔍 Testing Cache Fix...\n";
echo "=======================\n\n";

echo "Current Cache Store: " . config('cache.default') . "\n";

// Test the Cache tag functionality directly
try {
    echo "Testing Cache::tags() with current driver...\n";
    \Illuminate\Support\Facades\Cache::store('array')->tags(['test'])->put('test_key', 'test_value', 10);
    $value = \Illuminate\Support\Facades\Cache::store('array')->tags(['test'])->get('test_key');
    echo "✅ Cache::tags() WORKS with 'array' driver (value: $value)\n";
} catch (Exception $e) {
    echo "❌ Cache::tags() FAILED: " . $e->getMessage() . "\n";
}

// Test with the condition from ScheduleOptimizationService
$cacheDriver = config('cache.default');
echo "\nCache Driver Check:\n";
if (in_array($cacheDriver, ['redis', 'memcached', 'array'])) {
    echo "✅ Driver '$cacheDriver' supports tags\n";
    try {
        \Illuminate\Support\Facades\Cache::tags(['schedules', 'academic', 'timetable'])->flush();
        echo "✅ Cache tags flush works!\n";
    } catch (Exception $e) {
        echo "❌ Cache tags flush failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Driver '$cacheDriver' does NOT support tags\n";
}

// Test the ScheduleOptimizationService
echo "\nTesting ScheduleOptimizationService...\n";
try {
    $service = app(\App\Services\ScheduleOptimizationService::class);
    $service->clearAllScheduleCache();
    echo "✅ ScheduleOptimizationService clearAllScheduleCache() works!\n";
} catch (Exception $e) {
    echo "❌ ScheduleOptimizationService failed: " . $e->getMessage() . "\n";
}

echo "\n🚀 Cache fix test completed!\n";
