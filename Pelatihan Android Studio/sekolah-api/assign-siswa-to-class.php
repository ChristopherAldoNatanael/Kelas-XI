<?php

/**
 * Script untuk assign siswa ke kelas RPL
 * Jalankan dengan: php assign-siswa-to-class.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\ClassModel;
use Illuminate\Support\Facades\DB;

echo "=== ASSIGN SISWA TO CLASS ===\n\n";

try {
    // 1. Cek apakah kolom class_id sudah ada
    $hasClassId = Schema::hasColumn('users', 'class_id');

    if (!$hasClassId) {
        echo "❌ Kolom class_id belum ada di tabel users.\n";
        echo "   Jalankan migration dulu: php artisan migrate\n";
        exit(1);
    }

    echo "✓ Kolom class_id sudah ada di tabel users\n\n";

    // 2. Ambil semua kelas RPL
    $rplClasses = ClassModel::where('major', 'Rekayasa Perangkat Lunak')
        ->where('status', 'active')
        ->orderBy('level')
        ->get();

    if ($rplClasses->isEmpty()) {
        echo "❌ Tidak ada kelas RPL aktif di database.\n";
        echo "   Jalankan: php check-rpl-classes.php untuk membuat kelas.\n";
        exit(1);
    }

    echo "✓ Ditemukan {$rplClasses->count()} kelas RPL:\n";
    foreach ($rplClasses as $idx => $class) {
        echo "   " . ($idx + 1) . ". {$class->name} (ID: {$class->id}, Level: {$class->level})\n";
    }
    echo "\n";

    // 3. Ambil semua siswa
    $students = User::where('role', 'siswa')
        ->whereNull('deleted_at')
        ->get();

    echo "✓ Ditemukan {$students->count()} siswa\n\n";

    if ($students->isEmpty()) {
        echo "❌ Tidak ada siswa di database.\n";
        echo "   Buat user siswa terlebih dahulu.\n";
        exit(1);
    }

    // 4. Tampilkan siswa yang belum punya kelas
    $unassignedStudents = $students->whereNull('class_id');

    if ($unassignedStudents->isNotEmpty()) {
        echo "⚠️  Siswa yang belum assigned ke kelas ({$unassignedStudents->count()}):\n";
        foreach ($unassignedStudents as $student) {
            echo "   - {$student->nama} ({$student->email})\n";
        }
        echo "\n";
    }

    // 5. Menu untuk assign siswa
    echo "=== PILIHAN ASSIGN ===\n";
    echo "1. Auto assign (distribusi merata ke semua kelas RPL)\n";
    echo "2. Assign manual per siswa\n";
    echo "3. Assign semua siswa ke kelas tertentu\n";
    echo "4. Tampilkan status saat ini\n";
    echo "5. Keluar\n\n";

    echo "Pilih opsi (1-5): ";
    $option = trim(fgets(STDIN));

    switch ($option) {
        case '1':
            // Auto assign merata
            echo "\n🔄 Melakukan auto assign merata...\n";
            $classIndex = 0;
            $classCount = $rplClasses->count();
            $updated = 0;

            foreach ($unassignedStudents as $student) {
                $targetClass = $rplClasses[$classIndex % $classCount];
                $student->class_id = $targetClass->id;
                $student->save();

                echo "   ✓ {$student->nama} → {$targetClass->name}\n";
                $classIndex++;
                $updated++;
            }

            echo "\n✅ Berhasil assign {$updated} siswa!\n";
            break;

        case '2':
            // Manual assign
            echo "\n";
            foreach ($unassignedStudents as $student) {
                echo "Siswa: {$student->nama} ({$student->email})\n";
                echo "Pilih kelas:\n";
                foreach ($rplClasses as $idx => $class) {
                    echo "   " . ($idx + 1) . ". {$class->name}\n";
                }
                echo "Pilih (1-{$rplClasses->count()}): ";
                $classChoice = (int)trim(fgets(STDIN));

                if ($classChoice > 0 && $classChoice <= $rplClasses->count()) {
                    $targetClass = $rplClasses[$classChoice - 1];
                    $student->class_id = $targetClass->id;
                    $student->save();
                    echo "   ✓ Assigned ke {$targetClass->name}\n\n";
                } else {
                    echo "   ✗ Pilihan tidak valid, skip\n\n";
                }
            }
            break;

        case '3':
            // Assign ke satu kelas
            echo "\nPilih kelas tujuan:\n";
            foreach ($rplClasses as $idx => $class) {
                echo "   " . ($idx + 1) . ". {$class->name}\n";
            }
            echo "Pilih (1-{$rplClasses->count()}): ";
            $classChoice = (int)trim(fgets(STDIN));

            if ($classChoice > 0 && $classChoice <= $rplClasses->count()) {
                $targetClass = $rplClasses[$classChoice - 1];
                $updated = 0;

                foreach ($unassignedStudents as $student) {
                    $student->class_id = $targetClass->id;
                    $student->save();
                    echo "   ✓ {$student->nama} → {$targetClass->name}\n";
                    $updated++;
                }

                echo "\n✅ Berhasil assign {$updated} siswa ke {$targetClass->name}!\n";
            } else {
                echo "❌ Pilihan tidak valid.\n";
            }
            break;

        case '4':
            // Status
            echo "\n=== STATUS SISWA ===\n";
            foreach ($rplClasses as $class) {
                $studentCount = User::where('role', 'siswa')
                    ->where('class_id', $class->id)
                    ->count();
                echo "{$class->name}: {$studentCount} siswa\n";

                if ($studentCount > 0) {
                    $classStudents = User::where('role', 'siswa')
                        ->where('class_id', $class->id)
                        ->get();
                    foreach ($classStudents as $s) {
                        echo "   - {$s->nama} ({$s->email})\n";
                    }
                }
            }

            $noClass = User::where('role', 'siswa')
                ->whereNull('class_id')
                ->count();
            echo "\nBelum assigned: {$noClass} siswa\n";
            break;

        case '5':
            echo "Keluar...\n";
            break;

        default:
            echo "❌ Pilihan tidak valid.\n";
    }
} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
