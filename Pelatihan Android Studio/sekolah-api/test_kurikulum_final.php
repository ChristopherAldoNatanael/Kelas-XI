<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\KurikulumController;
use Illuminate\Http\Request;

$controller = new KurikulumController();

echo "=== Testing Kurikulum APIs ===\n\n";

// Test 1: History API
echo "1. Testing attendanceHistory API...\n";
try {
    $request = new Request();
    $response = $controller->attendanceHistory($request);
    $data = json_decode($response->getContent(), true);

    echo "   Status: " . ($data['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "   Records: " . count($data['data']) . "\n";
    if (count($data['data']) > 0) {
        $first = $data['data'][0];
        echo "   Sample: {$first['teacher_name']} - {$first['class_name']} - {$first['status']}\n";
    }
} catch (\Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Pending API
echo "2. Testing getPendingAttendances API...\n";
try {
    $request = new Request(['date' => date('Y-m-d')]);
    $response = $controller->getPendingAttendances($request);
    $data = json_decode($response->getContent(), true);

    echo "   Status: " . ($data['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "   Total pending: " . ($data['data']['total_pending'] ?? 0) . "\n";
    echo "   Date: " . ($data['data']['date'] ?? 'N/A') . "\n";
    echo "   Day: " . ($data['data']['day'] ?? 'N/A') . "\n";
} catch (\Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Dashboard API
echo "3. Testing dashboardOverview API...\n";
try {
    $request = new Request(['date' => date('Y-m-d')]);
    $response = $controller->dashboardOverview($request);
    $data = json_decode($response->getContent(), true);

    echo "   Status: " . ($data['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "   Day: " . ($data['day'] ?? 'N/A') . "\n";
    echo "   Stats - Hadir: " . ($data['stats']['hadir'] ?? 0) . ", Telat: " . ($data['stats']['telat'] ?? 0) . ", Tidak Hadir: " . ($data['stats']['tidak_hadir'] ?? 0) . "\n";
} catch (\Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Class Management API
echo "4. Testing classManagement API...\n";
try {
    $request = new Request(['date' => date('Y-m-d')]);
    $response = $controller->classManagement($request);
    $data = json_decode($response->getContent(), true);

    echo "   Status: " . ($data['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "   Day: " . ($data['day'] ?? 'N/A') . "\n";
    echo "   Summary - Tidak Hadir: " . ($data['summary']['tidak_hadir_count'] ?? 0) . ", Telat: " . ($data['summary']['telat_count'] ?? 0) . ", Izin: " . ($data['summary']['izin_count'] ?? 0) . "\n";
} catch (\Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== All Tests Completed ===\n";
