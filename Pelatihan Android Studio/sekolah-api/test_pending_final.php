<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\KurikulumController;
use Illuminate\Http\Request;

$controller = new KurikulumController();

echo "=== Testing Pending API ===\n";
$response = $controller->getPendingAttendances(new Request(['date' => date('Y-m-d')]));
$data = json_decode($response->getContent(), true);
echo "Total Pending: " . ($data['data']['total_pending'] ?? 0) . "\n\n";

if (!empty($data['data']['all_pending'])) {
    foreach ($data['data']['all_pending'] as $item) {
        echo "- " . $item['teacher_name'] . " | " . $item['class_name'] . " | " . $item['subject_name'] . "\n";
    }
} else {
    echo "No pending data found\n";
}

echo "\n=== Testing Class Management API ===\n";
$response2 = $controller->classManagement(new Request(['date' => date('Y-m-d')]));
$data2 = json_decode($response2->getContent(), true);
echo "Summary:\n";
echo "  Pending: " . ($data2['summary']['pending_count'] ?? 0) . "\n";
echo "  Tidak Hadir: " . ($data2['summary']['tidak_hadir_count'] ?? 0) . "\n";
echo "  Telat: " . ($data2['summary']['telat_count'] ?? 0) . "\n";
echo "  Izin: " . ($data2['summary']['izin_count'] ?? 0) . "\n";

if (!empty($data2['grouped_by_class'])) {
    echo "\nGrouped by Class:\n";
    foreach ($data2['grouped_by_class'] as $group) {
        echo "  - " . $group['class_name'] . " (" . $group['total_issues'] . " issues)\n";
    }
}
