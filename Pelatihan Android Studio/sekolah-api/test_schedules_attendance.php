<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\Api\KepalaSekolahController;
use Illuminate\Http\Request;

echo "Testing Schedules With Attendance API\n";
echo "=====================================\n\n";

$controller = new KepalaSekolahController();

// Test for Senin (Monday) this week
$request = Request::create('/api/kepala-sekolah/schedules-attendance?day=Senin&week_offset=0', 'GET');
$response = $controller->schedulesWithAttendance($request);
$data = json_decode($response->getContent(), true);

echo "Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";
echo "Message: " . $data['message'] . "\n";
if (isset($data['error'])) {
    echo "Error: " . $data['error'] . "\n";
}
echo "Total schedules: " . count($data['data'] ?? []) . "\n";

if (!empty($data['data'])) {
    echo "\nSample schedules:\n";
    foreach (array_slice($data['data'], 0, 5) as $i => $schedule) {
        echo "\n" . ($i + 1) . ". " . $schedule['class_name'] . "\n";
        echo "   Subject: " . $schedule['subject_name'] . "\n";
        echo "   Teacher: " . $schedule['teacher_name'] . "\n";
        echo "   Period: " . $schedule['period'] . " (" . $schedule['time_start'] . " - " . $schedule['time_end'] . ")\n";
        echo "   Status: " . ($schedule['attendance_status'] ?? 'Belum ada') . "\n";
        if ($schedule['substitute_teacher']) {
            echo "   Pengganti: " . $schedule['substitute_teacher'] . "\n";
        }
    }

    // Group by class for summary
    $byClass = [];
    foreach ($data['data'] as $s) {
        $byClass[$s['class_name']][] = $s;
    }

    echo "\n\nSummary by Class:\n";
    foreach ($byClass as $className => $schedules) {
        $hadir = count(array_filter($schedules, fn($s) => $s['attendance_status'] === 'hadir'));
        $telat = count(array_filter($schedules, fn($s) => $s['attendance_status'] === 'telat'));
        $tidakHadir = count(array_filter($schedules, fn($s) => $s['attendance_status'] === 'tidak_hadir'));
        $izin = count(array_filter($schedules, fn($s) => in_array($s['attendance_status'], ['izin', 'diganti'])));
        $belum = count(array_filter($schedules, fn($s) => $s['attendance_status'] === null));

        echo "  $className: " . count($schedules) . " jadwal ";
        echo "(H:$hadir T:$telat TH:$tidakHadir I:$izin B:$belum)\n";
    }
} else {
    echo "\nNo schedules found for Monday\n";
}

echo "\n\nTest completed!\n";
