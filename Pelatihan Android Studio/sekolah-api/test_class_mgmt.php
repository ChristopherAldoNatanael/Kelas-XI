<?php

/**
 * Test class management API - debug JSON error
 */
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$today = Carbon::now()->format('Y-m-d');
$dayMap = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];
$hari = $dayMap[Carbon::now()->format('l')] ?? 'Senin';

echo "Testing classManagement for $hari ($today)\n\n";

// Get all schedules for today
$schedules = Schedule::with([
    'class',
    'subject:id,nama',
    'teacher:id,nama,nip'
])
    ->where('hari', $hari)
    ->orderBy('kelas')
    ->orderBy('jam_mulai')
    ->get();

echo "Found " . count($schedules) . " schedules\n\n";

// Check each schedule for null values
foreach ($schedules as $s) {
    echo "Schedule #{$s->id}:\n";
    echo "  - class: " . ($s->class ? $s->class->nama_kelas : 'NULL (using kelas: ' . $s->kelas . ')') . "\n";
    echo "  - subject: " . ($s->subject ? $s->subject->nama : 'NULL (using mata_pelajaran: ' . $s->mata_pelajaran . ')') . "\n";
    echo "  - teacher: " . ($s->teacher ? $s->teacher->nama : 'NULL (guru_id: ' . $s->guru_id . ')') . "\n";
    echo "  - teacher_nip: " . ($s->teacher ? $s->teacher->nip : 'NULL') . "\n";

    // Check for issues
    $issues = [];
    if (!$s->class) $issues[] = "No class model";
    if (!$s->subject) $issues[] = "No subject model";
    if (!$s->teacher) $issues[] = "No teacher model";

    if (!empty($issues)) {
        echo "  ⚠ ISSUES: " . implode(', ', $issues) . "\n";
    }
    echo "\n";
}

// Test JSON encoding
echo "\n=== Testing JSON encoding ===\n";

try {
    $result = $schedules->map(function ($schedule) {
        return [
            'schedule_id' => $schedule->id,
            'class_name' => $schedule->class->nama_kelas ?? $schedule->kelas ?? 'Unknown',
            'teacher_name' => $schedule->teacher->nama ?? 'Unknown',
            'teacher_nip' => $schedule->teacher->nip ?? '',
        ];
    });

    $json = json_encode($result, JSON_THROW_ON_ERROR);
    echo "JSON encoding SUCCESS - Length: " . strlen($json) . " bytes\n";
} catch (\Exception $e) {
    echo "JSON encoding FAILED: " . $e->getMessage() . "\n";
}

echo "\n=== DONE ===\n";
