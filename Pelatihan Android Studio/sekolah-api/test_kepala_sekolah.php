<?php

/**
 * Test Kepala Sekolah Dashboard API
 */
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Api\KepalaSekolahController;

$controller = new KepalaSekolahController();
$request = new Request();

echo "=== Testing Kepala Sekolah Dashboard API ===\n\n";

try {
    $response = $controller->dashboardOverview($request);
    $data = json_decode($response->getContent(), true);
    echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
    echo "Message: " . $data['message'] . "\n\n";

    if (!$data['success']) {
        echo "Error: " . ($data['error'] ?? 'Unknown') . "\n";
        exit;
    }

    $result = $data['data'];

    echo "=== Week Info ===\n";
    echo "Week: " . $result['week_info']['week_label'] . "\n";
    echo "Period: " . $result['week_info']['week_start'] . " - " . $result['week_info']['week_end'] . "\n\n";

    echo "=== This Week Statistics ===\n";
    echo "Total: " . $result['this_week']['total'] . "\n";
    echo "Hadir: " . $result['this_week']['hadir'] . "\n";
    echo "Telat: " . $result['this_week']['telat'] . "\n";
    echo "Tidak Hadir: " . $result['this_week']['tidak_hadir'] . "\n";
    echo "Izin: " . $result['this_week']['izin'] . "\n";
    echo "Diganti: " . $result['this_week']['diganti'] . "\n";
    echo "Attendance Rate: " . $result['this_week']['attendance_rate'] . "%\n\n";

    echo "=== Last Week Statistics ===\n";
    echo "Total: " . $result['last_week']['total'] . "\n";
    echo "Hadir: " . $result['last_week']['hadir'] . "\n";
    echo "Attendance Rate: " . $result['last_week']['attendance_rate'] . "%\n\n";

    echo "=== Trends ===\n";
    echo "Hadir trend: " . ($result['trends']['hadir']['is_positive'] ? '↑' : '↓') . " " . $result['trends']['hadir']['value'] . "\n";
    echo "Telat trend: " . ($result['trends']['telat']['is_positive'] ? '↑' : '↓') . " " . $result['trends']['telat']['value'] . "\n\n";

    echo "=== Teachers on Leave ===\n";
    echo "Total: " . count($result['teachers_on_leave']) . "\n";
    foreach (array_slice($result['teachers_on_leave'], 0, 3) as $leave) {
        echo "  - " . $leave['original_teacher_name'] . " (" . $leave['status'] . ")";
        if ($leave['substitute_teacher_name']) {
            echo " → diganti oleh " . $leave['substitute_teacher_name'];
        }
        echo "\n";
    }

    echo "\n=== Daily Breakdown ===\n";
    foreach ($result['daily_breakdown'] as $day) {
        echo $day['day'] . " (" . $day['date'] . "): H:" . $day['hadir'] . " T:" . $day['telat'] . " TH:" . $day['tidak_hadir'] . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Done ===\n";
