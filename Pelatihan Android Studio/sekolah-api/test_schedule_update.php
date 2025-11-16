<?php

/**
 * Test Script untuk Memverifikasi Schedule Update
 * Script ini akan menguji proses update schedule dan memverifikasi data tersimpan di database
 */

require 'vendor/autoload.php';
require 'bootstrap/app.php';

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Schedule Update Verification Test ===\n\n";

try {
    // 1. Get first schedule to test update
    echo "1. Fetching a schedule to test update...\n";
    $schedule = Schedule::with(['teacher', 'subject', 'classroom', 'class'])->first();

    if (!$schedule) {
        echo "❌ No schedule found in database\n";
        exit(1);
    }

    echo "✓ Found schedule ID: {$schedule->id}\n";
    $teacherName = isset($schedule->teacher->user->nama) ? $schedule->teacher->user->nama : 'N/A';
    $subjectName = isset($schedule->subject->name) ? $schedule->subject->name : 'N/A';
    echo "  - Teacher: {$teacherName}\n";
    echo "  - Subject: {$subjectName}\n";
    echo "  - Day: {$schedule->day_of_week}\n";
    echo "  - Start Time: {$schedule->start_time}\n";
    echo "  - End Time: {$schedule->end_time}\n";
    echo "  - Period: {$schedule->period_number}\n";
    echo "  - Notes: {$schedule->notes}\n\n";

    // 2. Store original data
    echo "2. Storing original data...\n";
    $originalData = [
        'day_of_week' => $schedule->day_of_week,
        'period_number' => $schedule->period_number,
        'start_time' => $schedule->start_time,
        'end_time' => $schedule->end_time,
        'notes' => $schedule->notes,
    ];
    echo "✓ Original data stored\n\n";

    // 3. Prepare update data
    echo "3. Preparing update data...\n";
    $updateData = [
        'class_id' => $schedule->class_id,
        'subject_id' => $schedule->subject_id,
        'teacher_id' => $schedule->teacher_id,
        'classroom_id' => $schedule->classroom_id,
        'day_of_week' => 'tuesday', // Change from original
        'period_number' => $schedule->period_number + 1, // Change from original
        'start_time' => '10:00', // New start time
        'end_time' => '11:00', // New end time
        'notes' => 'Updated via test script - ' . now(),
        'updated_by' => 1,
    ];
    echo "✓ Update data prepared\n";
    echo "  - Day: {$updateData['day_of_week']}\n";
    echo "  - Period: {$updateData['period_number']}\n";
    echo "  - Start Time: {$updateData['start_time']}\n";
    echo "  - End Time: {$updateData['end_time']}\n";
    echo "  - Notes: {$updateData['notes']}\n\n";

    // 4. Perform update
    echo "4. Performing update...\n";
    $success = $schedule->update($updateData);

    if ($success || $schedule->wasChanged()) {
        echo "✓ Update executed successfully\n";
        echo "  - Changes: " . json_encode($schedule->getChanges()) . "\n\n";
    } else {
        echo "⚠ Update may have failed or no changes detected\n\n";
    }

    // 5. Verify in database
    echo "5. Verifying update in database...\n";
    $verifySchedule = Schedule::find($schedule->id);

    if ($verifySchedule) {
        echo "✓ Schedule found in database\n";

        // Check each field
        $fields_match = true;

        if ($verifySchedule->day_of_week === $updateData['day_of_week']) {
            echo "  ✓ day_of_week: {$verifySchedule->day_of_week}\n";
        } else {
            echo "  ❌ day_of_week: {$verifySchedule->day_of_week} (expected: {$updateData['day_of_week']})\n";
            $fields_match = false;
        }

        if ($verifySchedule->period_number == $updateData['period_number']) {
            echo "  ✓ period_number: {$verifySchedule->period_number}\n";
        } else {
            echo "  ❌ period_number: {$verifySchedule->period_number} (expected: {$updateData['period_number']})\n";
            $fields_match = false;
        }

        if ($verifySchedule->start_time === $updateData['start_time']) {
            echo "  ✓ start_time: {$verifySchedule->start_time}\n";
        } else {
            echo "  ❌ start_time: {$verifySchedule->start_time} (expected: {$updateData['start_time']})\n";
            $fields_match = false;
        }

        if ($verifySchedule->end_time === $updateData['end_time']) {
            echo "  ✓ end_time: {$verifySchedule->end_time}\n";
        } else {
            echo "  ❌ end_time: {$verifySchedule->end_time} (expected: {$updateData['end_time']})\n";
            $fields_match = false;
        }

        if ($verifySchedule->notes === $updateData['notes']) {
            echo "  ✓ notes: {$verifySchedule->notes}\n";
        } else {
            echo "  ❌ notes: {$verifySchedule->notes}\n";
            $fields_match = false;
        }

        echo "\n";

        if ($fields_match) {
            echo "✅ All fields updated correctly in database!\n\n";
        } else {
            echo "❌ Some fields were not updated correctly\n\n";
        }
    } else {
        echo "❌ Schedule not found in database after update\n\n";
    }

    // 6. Check updated_at timestamp
    echo "6. Checking updated_at timestamp...\n";
    if ($verifySchedule->updated_at) {
        echo "✓ updated_at: {$verifySchedule->updated_at}\n\n";
    } else {
        echo "❌ updated_at is not set\n\n";
    }

    // 7. Restore original data
    echo "7. Restoring original data...\n";
    $schedule->update($originalData);
    echo "✓ Original data restored\n\n";

    echo "=== Test Completed Successfully ===\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
