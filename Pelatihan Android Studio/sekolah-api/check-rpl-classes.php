<?php

/**
 * Script untuk memeriksa data kelas RPL di database
 * Jalankan dengan: php check-rpl-classes.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ClassModel;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;

echo "=== CHECKING RPL CLASSES IN DATABASE ===\n\n";

try {
    // 1. Check total classes
    $totalClasses = ClassModel::count();
    echo "✓ Total classes in database: {$totalClasses}\n";

    // 2. Check RPL classes specifically
    $rplClasses = ClassModel::where('major', 'Rekayasa Perangkat Lunak')
        ->where('status', 'active')
        ->get();

    echo "✓ Total RPL classes (active): {$rplClasses->count()}\n\n";

    if ($rplClasses->isEmpty()) {
        echo "❌ WARNING: No RPL classes found in database!\n";
        echo "   Creating sample RPL classes...\n\n";

        // Create sample RPL classes
        $rplLevels = [
            ['level' => 10, 'name' => 'X RPL'],
            ['level' => 11, 'name' => 'XI RPL'],
            ['level' => 12, 'name' => 'XII RPL'],
        ];

        foreach ($rplLevels as $levelData) {
            $class = ClassModel::create([
                'name' => $levelData['name'],
                'level' => $levelData['level'],
                'major' => 'Rekayasa Perangkat Lunak',
                'academic_year' => '2024/2025',
                'semester' => 'ganjil',
                'status' => 'active',
                'capacity' => 30
            ]);
            echo "   ✓ Created class: {$class->name} (ID: {$class->id})\n";
        }

        // Reload RPL classes
        $rplClasses = ClassModel::where('major', 'Rekayasa Perangkat Lunak')
            ->where('status', 'active')
            ->get();
    }

    // 3. Display all RPL classes
    echo "\n=== RPL CLASSES DETAILS ===\n";
    foreach ($rplClasses as $class) {
        echo "\nClass ID: {$class->id}\n";
        echo "  Name: {$class->name}\n";
        echo "  Level: {$class->level}\n";
        echo "  Major: {$class->major}\n";
        echo "  Status: {$class->status}\n";
        echo "  Academic Year: {$class->academic_year}\n";
        echo "  Semester: {$class->semester}\n";

        // Count schedules for this class
        $scheduleCount = Schedule::where('class_id', $class->id)
            ->where('status', 'active')
            ->count();
        echo "  Schedules: {$scheduleCount}\n";

        if ($scheduleCount > 0) {
            echo "  Days with schedules:\n";
            $schedulesByDay = Schedule::where('class_id', $class->id)
                ->where('status', 'active')
                ->select('day_of_week', DB::raw('count(*) as total'))
                ->groupBy('day_of_week')
                ->get();

            foreach ($schedulesByDay as $daySchedule) {
                echo "    - {$daySchedule->day_of_week}: {$daySchedule->total} periods\n";
            }
        }
    }

    // 4. Test API endpoint simulation
    echo "\n\n=== SIMULATING API ENDPOINT ===\n";
    echo "Testing: GET /api/dropdown/classes?major=Rekayasa Perangkat Lunak\n\n";

    $apiResult = ClassModel::select('level', 'major')
        ->selectRaw('MIN(id) as id')
        ->where('major', 'Rekayasa Perangkat Lunak')
        ->where('status', 'active')
        ->groupBy('level', 'major')
        ->orderBy('level')
        ->get()
        ->map(function ($class) {
            $levelNames = [10 => 'X', 11 => 'XI', 12 => 'XII'];
            $levelName = $levelNames[$class->level] ?? $class->level;
            return [
                'id' => $class->id,
                'name' => $levelName . ' RPL',
                'level' => $class->level,
                'major' => $class->major
            ];
        });

    echo "API Response:\n";
    echo json_encode([
        'success' => true,
        'message' => 'Data kelas berhasil diambil',
        'data' => $apiResult
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    echo "\n\n✅ All checks completed!\n";
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
