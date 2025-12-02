<?php

/**
 * Test script untuk memastikan default status 'pending' bekerja dengan benar
 *
 * Jalankan: php test_default_pending.php
 */

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\TeacherAttendance;
use App\Models\Schedule;
use Carbon\Carbon;

try {
    echo "=== Testing Default Status 'pending' ===\n\n";

    // 1. Test dari Model (Laravel level)
    echo "1. Testing Model Default Attribute:\n";
    $testModel = new TeacherAttendance();
    echo "   New TeacherAttendance status = '" . $testModel->status . "'\n";
    if ($testModel->status === 'pending') {
        echo "   ✓ SUCCESS - Model default is 'pending'\n\n";
    } else {
        echo "   ✗ FAILED - Expected 'pending', got '" . $testModel->status . "'\n\n";
    }

    // 2. Test menggunakan constants
    echo "2. Testing Status Constants:\n";
    echo "   STATUS_PENDING = '" . TeacherAttendance::STATUS_PENDING . "'\n";
    echo "   STATUS_HADIR = '" . TeacherAttendance::STATUS_HADIR . "'\n";
    echo "   STATUS_TELAT = '" . TeacherAttendance::STATUS_TELAT . "'\n";
    echo "   STATUS_TIDAK_HADIR = '" . TeacherAttendance::STATUS_TIDAK_HADIR . "'\n";
    echo "   STATUS_DIGANTI = '" . TeacherAttendance::STATUS_DIGANTI . "'\n";
    echo "   STATUS_IZIN = '" . TeacherAttendance::STATUS_IZIN . "'\n\n";

    // 3. Test create tanpa status (should default to pending)
    echo "3. Testing Create Without Explicit Status:\n";

    // Get first schedule for testing
    $schedule = Schedule::first();
    if (!$schedule) {
        echo "   ⚠ No schedule found for testing\n\n";
    } else {
        // Create attendance without specifying status
        $attendance = TeacherAttendance::create([
            'schedule_id' => $schedule->id,
            'guru_id' => $schedule->guru_id,
            'tanggal' => Carbon::now()->format('Y-m-d'),
            'keterangan' => 'Test default pending - will be deleted'
        ]);

        echo "   Created attendance ID: " . $attendance->id . "\n";
        echo "   Status: '" . $attendance->status . "'\n";

        if ($attendance->status === 'pending') {
            echo "   ✓ SUCCESS - Created with default 'pending'\n";
        } else {
            echo "   ✗ FAILED - Expected 'pending', got '" . $attendance->status . "'\n";
        }

        // Delete test record
        $attendance->delete();
        echo "   Test record deleted.\n\n";
    }

    // 4. Show current status distribution
    echo "4. Current Status Distribution in Database:\n";
    $stats = TeacherAttendance::selectRaw('status, COUNT(*) as count')
        ->groupBy('status')
        ->orderBy('count', 'desc')
        ->get();

    foreach ($stats as $stat) {
        echo "   - " . ($stat->status ?: 'NULL') . ": " . $stat->count . " records\n";
    }

    echo "\n=== ALL TESTS COMPLETED ===\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
