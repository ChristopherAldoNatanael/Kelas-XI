<?php

/**
 * Test script untuk create user melalui controller
 * Mensimulasikan form submit
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\ClassModel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

echo "=== TEST CREATE USER VIA WEB FORM ===\n\n";

try {
    // Get XI RPL class
    $xiRpl = ClassModel::where('name', 'XI RPL')->first();

    if (!$xiRpl) {
        echo "❌ Kelas XI RPL tidak ditemukan!\n";
        exit(1);
    }

    echo "✓ Kelas XI RPL found (ID: {$xiRpl->id})\n\n";

    // Test 1: Create siswa with class
    echo "TEST 1: Create Siswa with Class\n";
    echo "================================\n";

    $testEmail = 'test.siswa.' . time() . '@test.com';

    $userData = [
        'nama' => 'Test Siswa ' . time(),
        'email' => $testEmail,
        'password' => Hash::make('password123'),
        'role' => 'siswa',
        'class_id' => $xiRpl->id,
        'status' => 'active',
    ];

    echo "Data to insert:\n";
    echo "  Nama: {$userData['nama']}\n";
    echo "  Email: {$userData['email']}\n";
    echo "  Role: {$userData['role']}\n";
    echo "  Class ID: {$userData['class_id']}\n";
    echo "  Status: {$userData['status']}\n\n";

    // Check if class_id is in fillable
    $user = new User();
    $fillable = $user->getFillable();
    echo "Fillable fields: " . implode(', ', $fillable) . "\n";

    if (!in_array('class_id', $fillable)) {
        echo "❌ class_id is NOT in fillable!\n";
        exit(1);
    }
    echo "✓ class_id is in fillable\n\n";

    // Create user
    echo "Creating user...\n";
    $newUser = User::create($userData);

    echo "✓ User created!\n";
    echo "  ID: {$newUser->id}\n";
    echo "  Nama: {$newUser->nama}\n";
    echo "  Email: {$newUser->email}\n";
    echo "  Role: {$newUser->role}\n";
    echo "  Class ID: {$newUser->class_id}\n";
    echo "  Status: {$newUser->status}\n\n";

    // Verify in database
    echo "Verifying in database...\n";
    $dbUser = User::find($newUser->id);

    if (!$dbUser) {
        echo "❌ User not found in database!\n";
        exit(1);
    }

    echo "✓ User found in database\n";
    echo "  Class ID from DB: {$dbUser->class_id}\n";

    // Load class relationship
    $dbUser->load('class');
    if ($dbUser->class) {
        echo "  Class Name: {$dbUser->class->name}\n";
    } else {
        echo "  ⚠️  Class relationship not loaded\n";
    }

    echo "\n";

    // Test 2: Create admin (no class)
    echo "TEST 2: Create Admin (no class)\n";
    echo "================================\n";

    $adminEmail = 'test.admin.' . time() . '@test.com';

    $adminData = [
        'nama' => 'Test Admin ' . time(),
        'email' => $adminEmail,
        'password' => Hash::make('password123'),
        'role' => 'admin',
        'status' => 'active',
    ];

    echo "Creating admin...\n";
    $admin = User::create($adminData);

    echo "✓ Admin created!\n";
    echo "  ID: {$admin->id}\n";
    echo "  Nama: {$admin->nama}\n";
    echo "  Role: {$admin->role}\n";
    echo "  Class ID: " . ($admin->class_id ?? 'NULL') . "\n\n";

    // Test 3: Check total users
    echo "TEST 3: Total Users Check\n";
    echo "=========================\n";

    $totalUsers = User::count();
    $totalSiswa = User::where('role', 'siswa')->count();
    $siswaWithClass = User::where('role', 'siswa')->whereNotNull('class_id')->count();
    $siswaWithoutClass = User::where('role', 'siswa')->whereNull('class_id')->count();

    echo "Total Users: {$totalUsers}\n";
    echo "Total Siswa: {$totalSiswa}\n";
    echo "Siswa with Class: {$siswaWithClass}\n";
    echo "Siswa without Class: {$siswaWithoutClass}\n\n";

    // List siswa with their classes
    echo "Siswa List:\n";
    echo "-----------\n";
    $students = User::where('role', 'siswa')
        ->with('class')
        ->orderBy('id', 'desc')
        ->limit(5)
        ->get();

    foreach ($students as $s) {
        $className = $s->class ? $s->class->name : 'No Class';
        echo "  - {$s->nama} ({$s->email}) → {$className}\n";
    }

    echo "\n✅ ALL TESTS PASSED!\n";
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
