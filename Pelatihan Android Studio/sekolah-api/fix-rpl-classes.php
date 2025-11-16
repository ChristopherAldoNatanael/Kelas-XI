<?php

/**
 * Script untuk membersihkan dan membuat ulang kelas RPL yang benar
 * Hanya 3 kelas: X RPL, XI RPL, XII RPL
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ClassModel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== PERBAIKAN KELAS RPL ===\n\n";

try {
    DB::beginTransaction();

    // 1. Tampilkan kelas RPL yang ada sekarang
    $existingRplClasses = ClassModel::where('major', 'Rekayasa Perangkat Lunak')->get();

    echo "Kelas RPL yang ada sekarang ({$existingRplClasses->count()}):\n";
    foreach ($existingRplClasses as $class) {
        $studentCount = User::where('class_id', $class->id)->where('role', 'siswa')->count();
        echo "  - ID {$class->id}: {$class->name} (Level {$class->level}) - {$studentCount} siswa\n";
    }
    echo "\n";

    // 2. Konfirmasi
    echo "⚠️  PERINGATAN: Script ini akan:\n";
    echo "  1. Membersihkan semua kelas RPL yang ada\n";
    echo "  2. Membuat 3 kelas baru: X RPL, XI RPL, XII RPL\n";
    echo "  3. Memindahkan siswa ke kelas yang sesuai berdasarkan level\n";
    echo "\n";
    echo "Lanjutkan? (y/n): ";
    $confirm = trim(fgets(STDIN));

    if (strtolower($confirm) !== 'y') {
        echo "\n❌ Dibatalkan.\n";
        exit(0);
    }

    echo "\n🔄 Memproses...\n\n";    // 3. Ambil mapping siswa dari kelas lama
    $studentMapping = [];
    foreach ($existingRplClasses as $oldClass) {
        $students = User::where('class_id', $oldClass->id)->where('role', 'siswa')->get();
        foreach ($students as $student) {
            // Map berdasarkan level kelas
            $studentMapping[$student->id] = $oldClass->level;
        }
    }

    echo "✓ Menemukan " . count($studentMapping) . " siswa yang perlu dipindahkan\n";

    // 4. Hapus kelas RPL lama (soft delete atau hard delete)
    echo "✓ Menghapus kelas RPL lama...\n";
    ClassModel::where('major', 'Rekayasa Perangkat Lunak')->delete();

    // 5. Buat 3 kelas RPL yang benar
    $newClasses = [];
    $classesData = [
        ['name' => 'X RPL', 'level' => 10],
        ['name' => 'XI RPL', 'level' => 11],
        ['name' => 'XII RPL', 'level' => 12],
    ];

    echo "✓ Membuat kelas RPL baru:\n";
    foreach ($classesData as $data) {
        $class = ClassModel::create([
            'name' => $data['name'],
            'level' => $data['level'],
            'major' => 'Rekayasa Perangkat Lunak',
            'academic_year' => '2024/2025',
            'semester' => 'ganjil',
            'status' => 'active',
            'capacity' => 36, // Sesuaikan dengan kapasitas yang Anda inginkan
            'homeroom_teacher_id' => null,
        ]);

        $newClasses[$data['level']] = $class;
        echo "  - {$class->name} (ID: {$class->id})\n";
    }

    // 6. Pindahkan siswa ke kelas baru sesuai level
    echo "\n✓ Memindahkan siswa ke kelas baru:\n";
    $movedCount = 0;
    foreach ($studentMapping as $studentId => $level) {
        if (isset($newClasses[$level])) {
            $student = User::find($studentId);
            if ($student) {
                $student->class_id = $newClasses[$level]->id;
                $student->save();
                echo "  - {$student->nama} → {$newClasses[$level]->name}\n";
                $movedCount++;
            }
        }
    }

    DB::commit();

    echo "\n✅ SELESAI!\n\n";
    echo "=== HASIL AKHIR ===\n";

    $finalClasses = ClassModel::where('major', 'Rekayasa Perangkat Lunak')
        ->where('status', 'active')
        ->orderBy('level')
        ->get();

    foreach ($finalClasses as $class) {
        $studentCount = User::where('class_id', $class->id)->where('role', 'siswa')->count();
        echo "{$class->name}:\n";
        echo "  ID: {$class->id}\n";
        echo "  Level: {$class->level}\n";
        echo "  Siswa: {$studentCount}\n";

        if ($studentCount > 0) {
            $students = User::where('class_id', $class->id)->where('role', 'siswa')->get();
            foreach ($students as $s) {
                echo "    - {$s->nama} ({$s->email})\n";
            }
        }
        echo "\n";
    }

    echo "📊 Total kelas RPL: {$finalClasses->count()}\n";
    echo "📊 Total siswa dipindahkan: {$movedCount}\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
