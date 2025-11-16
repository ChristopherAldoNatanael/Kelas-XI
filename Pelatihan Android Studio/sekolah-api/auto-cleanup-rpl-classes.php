<?php

/**
 * Script untuk membersihkan kelas RPL duplikat - AUTO (tanpa konfirmasi)
 * Hanya simpan 3 kelas: X RPL, XI RPL, XII RPL
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ClassModel;
use App\Models\User;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;

echo "=== AUTO CLEANUP KELAS RPL ===\n\n";

try {
    DB::beginTransaction();

    // 1. Cari kelas yang sudah benar (X RPL, XI RPL, XII RPL tanpa angka)
    $correctClasses = ClassModel::where('major', 'Rekayasa Perangkat Lunak')
        ->whereIn('name', ['X RPL', 'XI RPL', 'XII RPL'])
        ->orderBy('level')
        ->get();

    if ($correctClasses->count() === 3) {
        echo "✅ 3 kelas yang benar sudah ada:\n";
        foreach ($correctClasses as $class) {
            echo "  - ID {$class->id}: {$class->name} (Level {$class->level})\n";
        }

        $correctIds = $correctClasses->pluck('id')->toArray();

        // 2. Hapus semua kelas RPL lain (yang duplikat/salah)
        $duplicates = ClassModel::where('major', 'Rekayasa Perangkat Lunak')
            ->whereNotIn('id', $correctIds)
            ->get();

        if ($duplicates->count() > 0) {
            echo "\n🗑️  Menghapus {$duplicates->count()} kelas duplikat...\n";

            foreach ($duplicates as $dup) {
                // Pindahkan jadwal dari kelas duplikat ke kelas yang benar
                $targetClass = $correctClasses->where('level', $dup->level)->first();
                if ($targetClass) {
                    $schedules = Schedule::where('class_id', $dup->id)->get();
                    $movedSchedules = 0;
                    foreach ($schedules as $schedule) {
                        // Check if schedule already exists in target class
                        $exists = Schedule::where('class_id', $targetClass->id)
                            ->where('day_of_week', $schedule->day_of_week)
                            ->where('period_number', $schedule->period_number)
                            ->where('academic_year', $schedule->academic_year)
                            ->where('semester', $schedule->semester)
                            ->exists();

                        if (!$exists) {
                            $schedule->class_id = $targetClass->id;
                            $schedule->save();
                            $movedSchedules++;
                        } else {
                            // Skip duplicate, delete instead
                            $schedule->delete();
                        }
                    }
                    if ($movedSchedules > 0) {
                        echo "  ✓ Memindahkan {$movedSchedules} jadwal dari {$dup->name} ke {$targetClass->name}\n";
                    }
                }

                // Pindahkan siswa dari kelas duplikat ke kelas yang benar
                $students = User::where('class_id', $dup->id)->where('role', 'siswa')->get();
                foreach ($students as $student) {
                    if ($targetClass) {
                        $student->class_id = $targetClass->id;
                        $student->save();
                    }
                }
                if ($students->count() > 0) {
                    echo "  ✓ Memindahkan {$students->count()} siswa dari {$dup->name} ke {$targetClass->name}\n";
                }

                // Hapus kelas duplikat
                $dup->delete();
                echo "  ✓ Menghapus kelas duplikat: {$dup->name} (ID {$dup->id})\n";
            }
        } else {
            echo "\n✅ Tidak ada kelas duplikat.\n";
        }
    } else {
        echo "⚠️  Kelas yang benar belum lengkap. Membuat ulang...\n\n";

        // Hapus semua kelas RPL
        $allRplClasses = ClassModel::where('major', 'Rekayasa Perangkat Lunak')->get();
        $studentMapping = [];
        $scheduleMapping = [];

        // Simpan mapping siswa dan jadwal berdasarkan level
        foreach ($allRplClasses as $class) {
            $students = User::where('class_id', $class->id)->where('role', 'siswa')->get();
            foreach ($students as $student) {
                $studentMapping[$student->id] = $class->level;
            }

            $schedules = Schedule::where('class_id', $class->id)->get();
            foreach ($schedules as $schedule) {
                $scheduleMapping[] = [
                    'schedule_id' => $schedule->id,
                    'level' => $class->level
                ];
            }
        }

        // Hapus semua kelas RPL
        ClassModel::where('major', 'Rekayasa Perangkat Lunak')->delete();
        echo "✓ Menghapus semua kelas RPL lama\n";

        // Buat 3 kelas baru
        $classesData = [
            ['name' => 'X RPL', 'level' => 10],
            ['name' => 'XI RPL', 'level' => 11],
            ['name' => 'XII RPL', 'level' => 12],
        ];

        $newClasses = [];
        echo "\n✓ Membuat 3 kelas RPL baru:\n";
        foreach ($classesData as $data) {
            $class = ClassModel::create([
                'name' => $data['name'],
                'level' => $data['level'],
                'major' => 'Rekayasa Perangkat Lunak',
                'academic_year' => '2024/2025',
                'semester' => 'ganjil',
                'status' => 'active',
                'capacity' => 36,
            ]);

            $newClasses[$data['level']] = $class;
            echo "  - {$class->name} (ID: {$class->id})\n";
        }

        // Pindahkan siswa
        if (count($studentMapping) > 0) {
            echo "\n✓ Memindahkan siswa:\n";
            foreach ($studentMapping as $studentId => $level) {
                if (isset($newClasses[$level])) {
                    $student = User::find($studentId);
                    if ($student) {
                        $student->class_id = $newClasses[$level]->id;
                        $student->save();
                        echo "  - {$student->nama} → {$newClasses[$level]->name}\n";
                    }
                }
            }
        }

        // Pindahkan jadwal
        if (count($scheduleMapping) > 0) {
            echo "\n✓ Memindahkan jadwal:\n";
            foreach ($scheduleMapping as $map) {
                $schedule = Schedule::find($map['schedule_id']);
                if ($schedule && isset($newClasses[$map['level']])) {
                    $schedule->class_id = $newClasses[$map['level']]->id;
                    $schedule->save();
                }
            }
            echo "  - Memindahkan " . count($scheduleMapping) . " jadwal\n";
        }

        $correctClasses = ClassModel::where('major', 'Rekayasa Perangkat Lunak')
            ->whereIn('name', ['X RPL', 'XI RPL', 'XII RPL'])
            ->orderBy('level')
            ->get();
    }

    DB::commit();

    echo "\n✅ CLEANUP SELESAI!\n\n";
    echo "=== HASIL AKHIR ===\n";

    foreach ($correctClasses as $class) {
        $studentCount = User::where('class_id', $class->id)->where('role', 'siswa')->count();
        $scheduleCount = Schedule::where('class_id', $class->id)->count();

        echo "\n{$class->name}:\n";
        echo "  ID: {$class->id}\n";
        echo "  Level: {$class->level}\n";
        echo "  Siswa: {$studentCount}\n";
        echo "  Jadwal: {$scheduleCount}\n";

        if ($studentCount > 0) {
            $students = User::where('class_id', $class->id)->where('role', 'siswa')->get();
            echo "  Daftar siswa:\n";
            foreach ($students as $s) {
                echo "    - {$s->nama} ({$s->email})\n";
            }
        }
    }

    // Clear cache
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    echo "\n✅ Cache cleared!\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
