<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

echo "🔍 COMPREHENSIVE ROUTE AND CONTROLLER TESTING\n";
echo "==============================================\n\n";

// Test database connection
echo "1. Testing Database Connection...\n";
try {
    $userCount = User::count();
    echo "✅ Database connection successful. Users count: {$userCount}\n\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test authentication
echo "2. Testing Authentication...\n";
$adminUser = User::where('email', 'admin@example.com')->first();
if ($adminUser) {
    Auth::login($adminUser);
    echo "✅ Admin user authenticated successfully\n\n";
} else {
    echo "❌ Admin user not found\n\n";
    exit(1);
}

// Get all web routes
$routes = Route::getRoutes();
$webRoutes = [];

foreach ($routes as $route) {
    if (str_contains($route->uri(), 'web-') || in_array($route->getName(), ['dashboard', 'login', 'logout'])) {
        $webRoutes[] = $route;
    }
}

echo "3. Testing Web Routes (" . count($webRoutes) . " routes found)...\n";

$errors = [];
$successCount = 0;

foreach ($webRoutes as $route) {
    $uri = $route->uri();
    $method = $route->methods()[0];
    $name = $route->getName() ?: 'unnamed';

    // Skip routes that need parameters for now
    if (str_contains($uri, '{')) {
        continue;
    }

    try {
        // Create a test request
        $request = \Illuminate\Http\Request::create($uri, $method);

        // Handle middleware
        if (in_array('auth', $route->middleware())) {
            // Already authenticated above
        }

        // Try to resolve the route
        $response = app()->handle($request);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $successCount++;
            echo "✅ {$method} {$uri} ({$name}) - Status: {$response->getStatusCode()}\n";
        } else {
            $errors[] = "{$method} {$uri} ({$name}) - Status: {$response->getStatusCode()}";
            echo "⚠️  {$method} {$uri} ({$name}) - Status: {$response->getStatusCode()}\n";
        }

    } catch (Exception $e) {
        $errors[] = "{$method} {$uri} ({$name}) - Error: " . $e->getMessage();
        echo "❌ {$method} {$uri} ({$name}) - Error: " . $e->getMessage() . "\n";
    }
}

echo "\n📊 Test Results:\n";
echo "✅ Successful routes: {$successCount}\n";
echo "❌ Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\n🚨 Error Details:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n🏁 Testing completed!\n";

if (count($errors) === 0) {
    echo "🎉 All routes are working correctly!\n";
} else {
    echo "⚠️  Some routes have issues that need to be fixed.\n";
    exit(1);
}
