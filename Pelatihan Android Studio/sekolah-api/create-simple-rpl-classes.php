<?php

/**
 * Script sederhana untuk membuat 3 kelas RPL saja
 * X RPL (Level 10), XI RPL (Level 11), XII RPL (Level 12)
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ClassModel;
use App\Models\User;

echo "=== BUAT 3 KELAS RPL SEDERHANA ===\n\n";

try {
    // Data 3 kelas yang benar
    $classesData = [
        ['name' => 'X RPL', 'level' => 10],
        ['name' => 'XI RPL', 'level' => 11],
        ['name' => 'XII RPL', 'level' => 12],
    ];

    echo "Kelas yang akan dibuat:\n";
    foreach ($classesData as $idx => $data) {
        echo "  " . ($idx + 1) . ". {$data['name']} (Level {$data['level']})\n";
    }
    echo "\n";

    // Check apakah sudah ada
    foreach ($classesData as $data) {
        $existing = ClassModel::where('name', $data['name'])
            ->where('level', $data['level'])
            ->where('major', 'Rekayasa Perangkat Lunak')
            ->first();

        if ($existing) {
            echo "✓ {$data['name']} sudah ada (ID: {$existing->id})\n";
        } else {
            // Create new
            $class = ClassModel::create([
                'name' => $data['name'],
                'level' => $data['level'],
                'major' => 'Rekayasa Perangkat Lunak',
                'academic_year' => '2024/2025',
                'semester' => 'ganjil',
                'status' => 'active',
                'capacity' => 36,
            ]);
            echo "✓ {$data['name']} berhasil dibuat (ID: {$class->id})\n";
        }
    }

    echo "\n=== KELAS RPL YANG TERSEDIA ===\n";
    $rplClasses = ClassModel::where('major', 'Rekayasa Perangkat Lunak')
        ->where('status', 'active')
        ->orderBy('level')
        ->get();

    foreach ($rplClasses as $class) {
        $studentCount = User::where('class_id', $class->id)->where('role', 'siswa')->count();
        echo "\n{$class->name}:\n";
        echo "  ID: {$class->id}\n";
        echo "  Level: {$class->level}\n";
        echo "  Siswa: {$studentCount}\n";
    }

    echo "\n✅ Total kelas RPL aktif: {$rplClasses->count()}\n";

    if ($rplClasses->count() > 3) {
        echo "\n⚠️  PERINGATAN: Ada lebih dari 3 kelas RPL!\n";
        echo "Jalankan 'php fix-rpl-classes.php' untuk membersihkan.\n";
    }
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
