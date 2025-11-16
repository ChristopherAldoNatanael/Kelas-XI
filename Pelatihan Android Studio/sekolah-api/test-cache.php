<?php

define('LARAVEL_START', microtime(true));

// Register the Composer autoloader...
require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel and return the Application instance...
$app = require_once __DIR__ . '/bootstrap/app.php';

echo "🔍 Testing Cache Drivers...\n";
echo "==========================\n\n";

echo "📋 Current Cache Store: " . config('cache.default') . "\n";
echo "📋 Current Cache Driver: " . config('cache.stores.' . config('cache.default') . '.driver') . "\n\n";

// Test Database Cache
echo "Testing Database Cache:\n";
try {
    \Illuminate\Support\Facades\Cache::store('database')->put('test', 'value', 10);
    $value = \Illuminate\Support\Facades\Cache::store('database')->get('test');
    echo "✅ Database cache: WORKING (value: $value)\n";
} catch(Exception $e) {
    echo "❌ Database cache: FAILED - " . $e->getMessage() . "\n";
}

// Test Database Cache with Tags (should fail)
echo "Testing Database Cache with Tags:\n";
try {
    \Illuminate\Support\Facades\Cache::store('database')->tags(['test'])->put('test', 'value', 10);
    echo "✅ Database cache with tags: WORKING (unexpected!)\n";
} catch(Exception $e) {
    echo "❌ Database cache with tags: FAILED - " . $e->getMessage() . "\n";
}

// Test Redis Cache
echo "\nTesting Redis Cache:\n";
try {
    \Illuminate\Support\Facades\Cache::store('redis')->put('test', 'value', 10);
    $value = \Illuminate\Support\Facades\Cache::store('redis')->get('test');
    echo "✅ Redis cache: WORKING (value: $value)\n";
} catch(Exception $e) {
    echo "❌ Redis cache: FAILED - " . $e->getMessage() . "\n";
}

// Test Redis Cache with Tags
echo "Testing Redis Cache with Tags:\n";
try {
    \Illuminate\Support\Facades\Cache::store('redis')->tags(['test'])->put('test', 'value', 10);
    $value = \Illuminate\Support\Facades\Cache::store('redis')->tags(['test'])->get('test');
    echo "✅ Redis cache with tags: WORKING (value: $value)\n";
} catch(Exception $e) {
    echo "❌ Redis cache with tags: FAILED - " . $e->getMessage() . "\n";
}

echo "\n🚀 Cache driver test completed!\n";
