<?php

/**
 * Test Schedule Update - Simple Version
 */

// Load Laravel
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Schedule;

echo "=== Schedule Update Verification Test ===\n\n";

try {
    // Get first schedule
    echo "1. Fetching a schedule to test update...\n";
    $schedule = Schedule::with(['teacher', 'subject', 'classroom', 'class'])->first();

    if (!$schedule) {
        echo "❌ No schedule found in database\n";
        exit(1);
    }

    echo "✓ Found schedule ID: {$schedule->id}\n";
    echo "  - Day: {$schedule->day_of_week}\n";
    echo "  - Start Time: {$schedule->start_time}\n";
    echo "  - End Time: {$schedule->end_time}\n";
    echo "  - Period: {$schedule->period_number}\n";
    echo "  - Notes: " . ($schedule->notes ?: 'empty') . "\n\n";

    // Store original
    $originalData = $schedule->toArray();

    // Prepare update
    echo "2. Preparing update data...\n";
    $newNotes = 'Updated at ' . now()->format('Y-m-d H:i:s');
    echo "✓ New notes: {$newNotes}\n\n";

    // Perform update
    echo "3. Performing update...\n";
    $schedule->update(['notes' => $newNotes]);
    echo "✓ Update executed\n\n";

    // Verify
    echo "4. Verifying in database...\n";
    $verified = Schedule::find($schedule->id);

    if ($verified->notes === $newNotes) {
        echo "✅ SUCCESS: Update verified in database!\n";
        echo "   - Old notes: " . ($originalData['notes'] ?: 'empty') . "\n";
        echo "   - New notes: {$verified->notes}\n";
    } else {
        echo "❌ FAILED: Update not verified!\n";
        echo "   - Expected: {$newNotes}\n";
        echo "   - Got: " . ($verified->notes ?: 'empty') . "\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
