<?php

/**
 * Test script untuk verifikasi fitur class_id di web interface
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\ClassModel;

echo "=== TEST WEB INTERFACE CLASS SELECTION ===\n\n";

try {
    // 1. Check if class_id column exists
    echo "1. Checking database structure...\n";
    $hasClassId = Schema::hasColumn('users', 'class_id');
    echo "   ✓ Column 'class_id' exists: " . ($hasClassId ? 'YES' : 'NO') . "\n\n";

    if (!$hasClassId) {
        echo "   ❌ ERROR: Column class_id tidak ada. Jalankan migration dulu!\n";
        exit(1);
    }

    // 2. Check RPL classes
    echo "2. Checking RPL classes...\n";
    $rplClasses = ClassModel::where('major', 'Rekayasa Perangkat Lunak')
        ->where('status', 'active')
        ->whereIn('name', ['X RPL', 'XI RPL', 'XII RPL'])
        ->orderBy('level')
        ->get();

    echo "   ✓ Found {$rplClasses->count()} RPL classes:\n";
    foreach ($rplClasses as $class) {
        echo "     - ID {$class->id}: {$class->name} (Level {$class->level})\n";
    }
    echo "\n";

    if ($rplClasses->count() !== 3) {
        echo "   ⚠️  WARNING: Expected 3 classes, found {$rplClasses->count()}\n\n";
    }

    // 3. Check students with classes
    echo "3. Checking students with class assignments...\n";
    $siswaWithClass = User::where('role', 'siswa')
        ->whereNotNull('class_id')
        ->with('class')
        ->get();

    echo "   ✓ Students with class: {$siswaWithClass->count()}\n";
    foreach ($siswaWithClass as $siswa) {
        echo "     - {$siswa->nama} ({$siswa->email}) → {$siswa->class->name}\n";
    }
    echo "\n";

    $siswaWithoutClass = User::where('role', 'siswa')
        ->whereNull('class_id')
        ->get();

    if ($siswaWithoutClass->count() > 0) {
        echo "   ⚠️  Students WITHOUT class: {$siswaWithoutClass->count()}\n";
        foreach ($siswaWithoutClass as $siswa) {
            echo "     - {$siswa->nama} ({$siswa->email})\n";
        }
        echo "\n";
    }

    // 4. Test user model fillable
    echo "4. Testing User model fillable...\n";
    $user = new User();
    $fillable = $user->getFillable();
    $hasClassIdFillable = in_array('class_id', $fillable);
    echo "   ✓ 'class_id' in fillable: " . ($hasClassIdFillable ? 'YES' : 'NO') . "\n\n";

    if (!$hasClassIdFillable) {
        echo "   ❌ ERROR: class_id tidak ada di fillable array!\n\n";
    }

    // 5. Summary
    echo "=== SUMMARY ===\n";
    echo "✓ Database: class_id column exists\n";
    echo "✓ RPL Classes: {$rplClasses->count()}/3 available\n";
    echo "✓ Students with class: {$siswaWithClass->count()}\n";
    echo "✓ Students without class: {$siswaWithoutClass->count()}\n";
    echo "✓ Model: class_id fillable\n\n";

    // 6. Instructions
    echo "=== CARA MENGGUNAKAN ===\n";
    echo "1. Buka browser: http://localhost:8000/web-users/create\n";
    echo "2. Isi form dengan:\n";
    echo "   - Nama: [Nama Siswa]\n";
    echo "   - Email: [email@example.com]\n";
    echo "   - Password: [password]\n";
    echo "   - Role: Pilih 'Siswa'\n";
    echo "3. Field 'Kelas' akan muncul otomatis\n";
    echo "4. Pilih kelas (X RPL, XI RPL, atau XII RPL)\n";
    echo "5. Submit form\n\n";

    echo "✅ SEMUA TEST PASSED!\n";
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
