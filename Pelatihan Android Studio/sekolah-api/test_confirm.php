<?php
// Test confirm attendance API

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Carbon\Carbon;
use App\Models\Schedule;
use App\Models\TeacherAttendance;
use App\Models\Teacher;

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

echo "=== Test Confirm Attendance ===\n";
echo "Date: $targetDate | Day: $hari\n\n";

// Get first schedule without attendance
$schedule = Schedule::where('hari', $hari)->first();

if (!$schedule) {
    echo "No schedule found for $hari\n";
    exit;
}

echo "Test Schedule:\n";
echo "  - ID: {$schedule->id}\n";
echo "  - Class: {$schedule->kelas}\n";
echo "  - Subject: {$schedule->mata_pelajaran}\n";
echo "  - Teacher ID: {$schedule->guru_id}\n";

// Check if attendance exists
$existingAttendance = TeacherAttendance::where('schedule_id', $schedule->id)
    ->where('tanggal', $targetDate)
    ->first();

echo "\nExisting attendance: " . ($existingAttendance ? "YES (ID: {$existingAttendance->id}, Status: {$existingAttendance->status})" : "NO") . "\n";

// Simulate confirm
echo "\n=== Simulating Confirm ===\n";
$status = 'hadir';
$keterangan = 'Test dari debug script';

if ($existingAttendance && !in_array($existingAttendance->status, ['pending', 'belum_lapor'])) {
    echo "Cannot confirm - already has status: {$existingAttendance->status}\n";
} else {
    try {
        if ($existingAttendance) {
            // Update existing
            echo "Updating existing attendance ID: {$existingAttendance->id}\n";
            $existingAttendance->update([
                'status' => $status,
                'keterangan' => $keterangan,
                'jam_masuk' => $existingAttendance->jam_masuk ?? Carbon::now()->format('H:i:s')
            ]);
            $attendance = $existingAttendance->fresh();
        } else {
            // Create new
            echo "Creating new attendance\n";
            $attendance = TeacherAttendance::create([
                'schedule_id' => $schedule->id,
                'guru_id' => $schedule->guru_id,
                'tanggal' => $targetDate,
                'jam_masuk' => Carbon::now()->format('H:i:s'),
                'status' => $status,
                'keterangan' => $keterangan,
            ]);
        }

        echo "\n✅ Success!\n";
        echo "  - Attendance ID: {$attendance->id}\n";
        echo "  - Status: {$attendance->status}\n";
        echo "  - Jam Masuk: {$attendance->jam_masuk}\n";
        echo "  - Keterangan: {$attendance->keterangan}\n";
    } catch (\Exception $e) {
        echo "\n❌ Error: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . "\n";
        echo "Line: " . $e->getLine() . "\n";
    }
}

// Verify change
echo "\n=== Verifying ===\n";
$verifyAttendance = TeacherAttendance::where('schedule_id', $schedule->id)
    ->where('tanggal', $targetDate)
    ->first();

if ($verifyAttendance) {
    echo "Attendance record found:\n";
    echo "  - ID: {$verifyAttendance->id}\n";
    echo "  - Status: {$verifyAttendance->status}\n";
    echo "  - Guru ID: {$verifyAttendance->guru_id}\n";
    echo "  - Jam Masuk: {$verifyAttendance->jam_masuk}\n";
} else {
    echo "No attendance record found!\n";
}

// Check TeacherAttendance model fillable
echo "\n=== TeacherAttendance Model Info ===\n";
$model = new TeacherAttendance();
echo "Fillable: " . implode(', ', $model->getFillable()) . "\n";
echo "Table: " . $model->getTable() . "\n";
