<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\Api\KepalaSekolahController;
use Illuminate\Http\Request;

echo "Testing Teachers on Leave with Leaves Table\n";
echo "============================================\n\n";

$controller = new KepalaSekolahController();
$request = Request::create('/api/kepala-sekolah/dashboard?week_offset=0', 'GET');
$response = $controller->dashboardOverview($request);
$data = json_decode($response->getContent(), true);

echo "Success: " . ($data['success'] ? 'Yes' : 'No') . "\n";

if (isset($data['error'])) {
    echo "Error: " . $data['error'] . "\n";
}

$teachersOnLeave = $data['data']['teachers_on_leave'] ?? [];
echo "Teachers on leave count: " . count($teachersOnLeave) . "\n\n";

if (!empty($teachersOnLeave)) {
    echo "Details:\n";
    foreach (array_slice($teachersOnLeave, 0, 10) as $i => $t) {
        echo ($i + 1) . ". " . ($t['original_teacher_name'] ?? 'Unknown') . "\n";
        echo "   Date: " . ($t['date'] ?? '-') . " (" . ($t['day'] ?? '-') . ")\n";
        echo "   Status: " . ($t['status'] ?? '-') . "\n";
        echo "   Class: " . ($t['class_name'] ?? '-') . " - " . ($t['subject_name'] ?? '-') . "\n";
        echo "   Time: " . ($t['time'] ?? '-') . "\n";
        echo "   Keterangan: " . ($t['keterangan'] ?? '-') . "\n";
        echo "   Source: " . ($t['source'] ?? 'unknown') . "\n";
        if (!empty($t['substitute_teacher_name'])) {
            echo "   Substitute: " . $t['substitute_teacher_name'] . "\n";
        }
        echo "\n";
    }
}

echo "Test completed!\n";
