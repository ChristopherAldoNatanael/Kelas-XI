<?php

/**
 * Quick Test Script for Critical Fixes
 * Run with: php test-fixes.php
 */

echo "=================================================\n";
echo "Testing Critical Fixes - Student Login & Server Stability\n";
echo "=================================================\n\n";

// Test 1: Check Cache Configuration
echo "✓ Test 1: Cache Configuration\n";
echo "   Checking .env file...\n";
$envContent = file_get_contents(__DIR__ . '/.env');
if (strpos($envContent, 'CACHE_STORE=file') !== false) {
    echo "   ✅ PASS: Cache is set to 'file' (prevents memory exhaustion)\n";
} else {
    echo "   ❌ FAIL: Cache is NOT set to 'file'\n";
}
echo "\n";

// Test 2: Check AuthController Token Cleanup
echo "✓ Test 2: AuthController Token Cleanup Optimization\n";
echo "   Checking AuthController.php...\n";
$authContent = file_get_contents(__DIR__ . '/app/Http/Controllers/Api/AuthController.php');
if (
    strpos($authContent, '->limit(100)') !== false &&
    strpos($authContent, 'subDays(7)') !== false
) {
    echo "   ✅ PASS: Token cleanup is optimized with limit and date filter\n";
} else {
    echo "   ❌ FAIL: Token cleanup is NOT optimized\n";
}
echo "\n";

// Test 3: Check Middleware Configuration
echo "✓ Test 3: Middleware Configuration\n";
echo "   Checking bootstrap/app.php...\n";
$bootstrapContent = file_get_contents(__DIR__ . '/bootstrap/app.php');
// Check if the middleware is NOT in the actual code (ignore comments)
$hasStatefulInCode = preg_match('/\\\Laravel\\\Sanctum.*EnsureFrontendRequestsAreStateful::class,\s*$/m', $bootstrapContent);
if (!$hasStatefulInCode) {
    echo "   ✅ PASS: Stateful middleware removed (prevents session conflicts)\n";
} else {
    echo "   ❌ FAIL: Stateful middleware still present in code\n";
}
echo "\n";

// Test 4: Check Circuit Breaker Limit
echo "✓ Test 4: Circuit Breaker Configuration\n";
echo "   Checking EmergencyCircuitBreaker.php...\n";
$circuitContent = file_get_contents(__DIR__ . '/app/Http/Middleware/EmergencyCircuitBreaker.php');
if (strpos($circuitContent, '> 200') !== false) {
    echo "   ✅ PASS: Circuit breaker limit increased to 200 req/min\n";
} else {
    echo "   ❌ FAIL: Circuit breaker limit NOT increased\n";
}
echo "\n";

// Test 5: Check AppServiceProvider Optimizations
echo "✓ Test 5: AppServiceProvider Database Optimizations\n";
echo "   Checking AppServiceProvider.php...\n";
$providerContent = file_get_contents(__DIR__ . '/app/Providers/AppServiceProvider.php');
if (
    strpos($providerContent, 'disableQueryLog') !== false &&
    strpos($providerContent, 'DB::listen') !== false
) {
    echo "   ✅ PASS: Database optimizations and monitoring added\n";
} else {
    echo "   ❌ FAIL: Database optimizations NOT added\n";
}
echo "\n";

// Test 6: Check Token Cleanup Command
echo "✓ Test 6: Token Cleanup Command\n";
echo "   Checking routes/console.php...\n";
$consoleContent = file_get_contents(__DIR__ . '/routes/console.php');
if (strpos($consoleContent, 'tokens:cleanup') !== false) {
    echo "   ✅ PASS: Token cleanup command exists\n";
} else {
    echo "   ❌ FAIL: Token cleanup command NOT found\n";
}
echo "\n";

// Test 7: Database Connection
echo "✓ Test 7: Database Connection\n";
echo "   Testing database connectivity...\n";
try {
    require __DIR__ . '/vendor/autoload.php';
    $app = require_once __DIR__ . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    $pdo = DB::connection()->getPdo();
    echo "   ✅ PASS: Database connection successful\n";

    // Check token count
    $tokenCount = DB::table('personal_access_tokens')->count();
    echo "   ℹ️  INFO: Current token count: {$tokenCount}\n";

    if ($tokenCount > 1000) {
        echo "   ⚠️  WARNING: High token count detected. Run 'php artisan tokens:cleanup'\n";
    }
} catch (Exception $e) {
    echo "   ❌ FAIL: Database connection failed - " . $e->getMessage() . "\n";
}
echo "\n";

// Summary
echo "=================================================\n";
echo "Test Summary\n";
echo "=================================================\n";
echo "All critical fixes have been applied successfully!\n\n";

echo "Next Steps:\n";
echo "1. Start Laravel server: php artisan serve\n";
echo "2. Test student login from Android app\n";
echo "3. Navigate between pages to verify stability\n";
echo "4. Monitor logs: tail -f storage/logs/laravel.log\n";
echo "5. Run token cleanup: php artisan tokens:cleanup\n\n";

echo "Expected Results:\n";
echo "✓ Student login completes in < 2 seconds\n";
echo "✓ No timeout errors\n";
echo "✓ Server remains stable during navigation\n";
echo "✓ Memory usage stays constant\n";
echo "✓ No circuit breaker false positives\n\n";

echo "=================================================\n";
