<?php

define('LARAVEL_START', microtime(true));

// Register the Composer autoloader...
require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel and return the Application instance...
$app = require_once __DIR__ . '/bootstrap/app.php';

// Bootstrap the kernel to initialize the application
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

echo "Generating API Token for admin@sekolah.com...\n";
echo "==============================================\n\n";

try {
    $user = User::where('email', 'admin@sekolah.com')->first();

    if ($user) {
        $token = $user->createToken('test-token')->plainTextToken;
        echo "✅ Token generated successfully!\n";
        echo "Bearer Token: " . $token . "\n\n";
        echo "Use this token in your Authorization header:\n";
        echo "Authorization: Bearer " . $token . "\n";
    } else {
        echo "❌ User not found\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
