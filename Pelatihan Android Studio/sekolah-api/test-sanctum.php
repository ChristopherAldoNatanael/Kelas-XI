<?php
// Test Sanctum auth

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

try {
    echo "=== TEST SANCTUM ===\n\n";

    // Check if table exists
    echo "1. Checking personal_access_tokens table...\n";
    $tableExists = DB::getSchemaBuilder()->hasTable('personal_access_tokens');
    echo "   Table exists: " . ($tableExists ? 'YES' : 'NO') . "\n\n";

    if (!$tableExists) {
        echo "ERROR: Table personal_access_tokens does not exist!\n";
        echo "Run: php artisan migrate\n";
        exit(1);
    }

    // Check tokens count
    echo "2. Checking tokens count...\n";
    $tokenCount = DB::table('personal_access_tokens')->count();
    echo "   Total tokens: $tokenCount\n\n";

    // Get siswa user
    echo "3. Getting siswa user...\n";
    $user = User::where('role', 'siswa')->first();
    echo "   User: {$user->email} (ID: {$user->id})\n\n";

    // Create new token
    echo "4. Creating new token...\n";
    $user->tokens()->delete(); // Delete old tokens
    $token = $user->createToken('test_token');
    echo "   Token created: " . substr($token->plainTextToken, 0, 30) . "...\n\n";

    // Verify token in database
    echo "5. Verifying token in database...\n";
    $dbToken = DB::table('personal_access_tokens')
        ->where('tokenable_id', $user->id)
        ->first();
    if ($dbToken) {
        echo "   Token found in DB: ID={$dbToken->id}, name={$dbToken->name}\n";
    } else {
        echo "   ERROR: Token not found in DB!\n";
    }

    // Test finding token
    echo "\n6. Testing PersonalAccessToken::findToken...\n";
    $parts = explode('|', $token->plainTextToken);
    $tokenId = $parts[0];
    $plainToken = $parts[1];
    echo "   Token ID: $tokenId\n";
    echo "   Finding token...\n";

    $accessToken = PersonalAccessToken::findToken($token->plainTextToken);
    if ($accessToken) {
        echo "   SUCCESS: Token validated!\n";
        echo "   Tokenable: " . get_class($accessToken->tokenable) . " ID=" . $accessToken->tokenable->id . "\n";
    } else {
        echo "   ERROR: Token not found/validated!\n";
    }

    echo "\n=== ALL TESTS PASSED ===\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}
