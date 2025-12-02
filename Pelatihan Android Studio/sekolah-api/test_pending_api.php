<?php

/**
 * Test Pending API
 */
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\Api\KurikulumController;

$controller = new KurikulumController();
$request = new Request();

echo "=== Testing Pending API ===\n\n";

try {
    $response = $controller->getPendingAttendances($request);
    $responseData = json_decode($response->getContent(), true);

    echo "Success: " . ($responseData['success'] ? 'true' : 'false') . "\n";
    echo "Message: " . $responseData['message'] . "\n\n";

    $data = $responseData['data'];
    echo "Date: " . $data['date'] . "\n";
    echo "Day: " . $data['day'] . "\n";
    echo "Total pending: " . $data['total_pending'] . "\n\n";

    if (count($data['all_pending']) > 0) {
        echo "First pending item:\n";
        $first = $data['all_pending'][0];
        echo "  ID: " . $first['id'] . "\n";
        echo "  Guru: " . $first['teacher_name'] . "\n";
        echo "  Kelas: " . $first['class_name'] . "\n";
        echo "  Mapel: " . $first['subject_name'] . "\n";
        echo "  Status: " . $first['status'] . "\n";
        echo "  Keterangan: " . $first['keterangan'] . "\n";
    }

    echo "\nGrouped by class:\n";
    foreach ($data['grouped_by_class'] as $group) {
        echo "  - " . $group['class_name'] . ": " . $group['total_pending'] . " pending\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Done ===\n";
