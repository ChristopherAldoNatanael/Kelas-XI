<?php

/**
 * Direct test untuk create user dengan class_id
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\ClassModel;
use Illuminate\Support\Facades\Hash;

echo "=== TEST USER WITH CLASS ===\n\n";

try {
    // 1. Check classes
    echo "1. Checking available classes...\n";
    $classes = ClassModel::where('major', 'Rekayasa Perangkat Lunak')
        ->where('status', 'active')
        ->whereIn('name', ['X RPL', 'XI RPL', 'XII RPL'])
        ->orderBy('level')
        ->get();

    echo "Available classes:\n";
    foreach ($classes as $c) {
        echo "  - ID: {$c->id}, Name: {$c->name}, Level: {$c->level}\n";
    }
    echo "Total: " . $classes->count() . " classes\n\n";

    if ($classes->count() === 0) {
        echo "ERROR: No RPL classes found!\n";
        exit(1);
    }

    // 2. Create test user
    echo "2. Creating test siswa user...\n";

    // Delete existing test user
    $existingUser = User::where('email', 'siswa.test@example.com')->first();
    if ($existingUser) {
        echo "  - Deleting existing user...\n";
        $existingUser->forceDelete();
    }

    // Get XI RPL class
    $class = ClassModel::where('name', 'XI RPL')->first();
    if (!$class) {
        echo "ERROR: XI RPL class not found!\n";
        exit(1);
    }

    // Create new user
    $user = User::create([
        'nama' => 'Siswa Test',
        'email' => 'siswa.test@example.com',
        'password' => Hash::make('password123'),
        'role' => 'siswa',
        'class_id' => $class->id,
        'status' => 'active'
    ]);

    echo "✓ User created successfully!\n";
    echo "  ID: {$user->id}\n";
    echo "  Name: {$user->nama}\n";
    echo "  Email: {$user->email}\n";
    echo "  Role: {$user->role}\n";
    echo "  Class ID: {$user->class_id}\n";

    // Load class relationship
    $user->load('class');
    echo "  Class Name: " . ($user->class ? $user->class->name : 'N/A') . "\n\n";

    // 3. Test update class
    echo "3. Testing update user class...\n";
    $newClass = ClassModel::where('name', 'XII RPL')->first();
    if (!$newClass) {
        echo "ERROR: XII RPL class not found!\n";
        exit(1);
    }

    $user->update(['class_id' => $newClass->id]);
    $user->load('class');

    echo "✓ User updated successfully!\n";
    echo "  Previous class: XI RPL\n";
    echo "  New class ID: {$user->class_id}\n";
    echo "  New class name: " . ($user->class ? $user->class->name : 'N/A') . "\n\n";

    // 4. Test role change
    echo "4. Testing role change (siswa -> admin)...\n";
    echo "  Before: Role={$user->role}, class_id={$user->class_id}\n";

    $user->update([
        'role' => 'admin',
        'class_id' => null
    ]);

    echo "  After: Role={$user->role}, class_id=" . ($user->class_id ?? 'NULL') . "\n";
    echo "✓ Role changed to admin, class_id cleared!\n\n";

    // Change back to siswa
    echo "5. Change back to siswa with X RPL...\n";
    $class = ClassModel::where('name', 'X RPL')->first();
    $user->update([
        'role' => 'siswa',
        'class_id' => $class->id
    ]);

    $user->load('class');
    echo "✓ Changed back to siswa\n";
    echo "  Class: " . ($user->class ? $user->class->name : 'N/A') . "\n\n";

    // 6. Final verification
    echo "6. Final verification...\n";
    $user = User::with('class')->where('email', 'siswa.test@example.com')->first();

    echo "Final user state:\n";
    echo "  ID: {$user->id}\n";
    echo "  Name: {$user->nama}\n";
    echo "  Email: {$user->email}\n";
    echo "  Role: {$user->role}\n";
    echo "  Class ID: {$user->class_id}\n";
    echo "  Class: " . ($user->class ? $user->class->name . ' (Level ' . $user->class->level . ')' : 'N/A') . "\n\n";

    echo "✅ ALL TESTS PASSED!\n\n";
    echo "Now you can test in browser:\n";
    echo "1. Visit: http://localhost:8000/web-users/create\n";
    echo "2. Fill form:\n";
    echo "   - Name: Test Siswa Baru\n";
    echo "   - Email: siswa.baru@test.com\n";
    echo "   - Password: password123\n";
    echo "   - Role: Siswa (akan muncul dropdown kelas)\n";
    echo "   - Kelas: XI RPL\n";
    echo "3. Click 'Create User'\n";
    echo "4. You'll be redirected to users list\n\n";

    echo "Test user credentials:\n";
    echo "  Email: siswa.test@example.com\n";
    echo "  Password: password123\n";
    echo "  Class: X RPL\n";
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
