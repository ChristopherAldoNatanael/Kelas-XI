<?php
// Quick test to find malformed data in pending attendances

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Carbon\Carbon;
use App\Models\Schedule;

$targetDate = Carbon::now()->format('Y-m-d');
$hari = match (Carbon::now()->dayOfWeekIso) {
    1 => 'Senin',
    2 => 'Selasa',
    3 => 'Rabu',
    4 => 'Kamis',
    5 => 'Jumat',
    6 => 'Sabtu',
    7 => 'Minggu'
};

echo "Checking schedules for $hari ($targetDate)\n\n";

$schedules = Schedule::with(['guru:id,nama,nip'])->where('hari', $hari)->get();

$issues = [];
foreach ($schedules as $idx => $schedule) {
    $problems = [];

    // Check guru_id
    if (empty($schedule->guru_id)) {
        $problems[] = "Missing guru_id";
    }

    // Check guru relationship
    if ($schedule->guru_id && !$schedule->guru) {
        $problems[] = "Guru ID {$schedule->guru_id} not found in teachers table";
    }

    // Check kelas
    if (empty($schedule->kelas)) {
        $problems[] = "Missing kelas";
    }

    // Check mata_pelajaran
    if (empty($schedule->mata_pelajaran)) {
        $problems[] = "Missing mata_pelajaran";
    }

    if (!empty($problems)) {
        $issues[] = [
            'index' => $idx,
            'schedule_id' => $schedule->id,
            'problems' => $problems
        ];
    }
}

if (empty($issues)) {
    echo "✓ All schedules look OK!\n";
} else {
    echo "Found " . count($issues) . " schedules with issues:\n\n";
    foreach ($issues as $issue) {
        echo "Schedule #{$issue['schedule_id']} (index {$issue['index']}):\n";
        foreach ($issue['problems'] as $p) {
            echo "  - $p\n";
        }
        echo "\n";
    }
}

// Also check total count
echo "\nTotal schedules for $hari: " . $schedules->count() . "\n";
