<?php

/**
 * Test Kehadiran Endpoints - Performance Optimized
 * Tes untuk memastikan endpoint kehadiran tidak membebani server
 */

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

$baseUrl = 'http://localhost:8000/api';
$client = new Client(['base_uri' => $baseUrl, 'http_errors' => false]);

echo "=" . str_repeat("=", 70) . "\n";
echo "  TEST KEHADIRAN ENDPOINTS - PERFORMANCE CHECK\n";
echo "=" . str_repeat("=", 70) . "\n\n";

// Step 1: Login as Siswa
echo "1️⃣  Login sebagai Siswa...\n";
$loginResponse = $client->post('/auth/login', [
    'json' => [
        'email' => 'siswa@gmail.com',
        'password' => 'password'
    ]
]);

$loginData = json_decode($loginResponse->getBody(), true);

if (!isset($loginData['success']) || !$loginData['success']) {
    echo "   ❌ Login gagal!\n";
    echo "   Response: " . $loginResponse->getBody() . "\n";
    exit(1);
}

$token = $loginData['data']['token'];
$userName = $loginData['data']['user']['nama'];
$classId = $loginData['data']['user']['class_id'];

echo "   ✅ Login berhasil!\n";
echo "   👤 User: $userName\n";
echo "   🏫 Class ID: $classId\n\n";

// Step 2: Test Today's Kehadiran Status (CRITICAL - ini yang bikin server mati)
echo "2️⃣  Test GET /api/siswa/kehadiran/today (CRITICAL TEST)...\n";
$startTime = microtime(true);

$todayResponse = $client->get('/siswa/kehadiran/today', [
    'headers' => ['Authorization' => "Bearer $token"]
]);

$responseTime = round((microtime(true) - $startTime) * 1000, 2);
$todayData = json_decode($todayResponse->getBody(), true);

if ($todayResponse->getStatusCode() === 200 && $todayData['success']) {
    echo "   ✅ Response time: {$responseTime}ms\n";
    echo "   ✅ Success! Tanggal: {$todayData['tanggal']}\n";
    echo "   ✅ Total schedules hari ini: " . count($todayData['schedules']) . "\n";

    if ($responseTime > 1000) {
        echo "   ⚠️  WARNING: Response time > 1 detik! Perlu optimasi lebih lanjut.\n";
    } elseif ($responseTime > 500) {
        echo "   ⚠️  PERHATIAN: Response time agak lambat.\n";
    } else {
        echo "   🚀 EXCELLENT: Response time sangat cepat!\n";
    }

    if (!empty($todayData['schedules'])) {
        echo "   📋 Sample schedule:\n";
        $sample = $todayData['schedules'][0];
        echo "      - Period: {$sample['period']}\n";
        echo "      - Subject: {$sample['subject']}\n";
        echo "      - Teacher: {$sample['teacher']}\n";
        echo "      - Submitted: " . ($sample['submitted'] ? 'Yes' : 'No') . "\n";
    }
} else {
    echo "   ❌ FAILED!\n";
    echo "   Status: " . $todayResponse->getStatusCode() . "\n";
    echo "   Response: " . $todayResponse->getBody() . "\n";
}
echo "\n";

// Step 3: Test Riwayat (Another heavy endpoint)
echo "3️⃣  Test GET /api/siswa/kehadiran/riwayat...\n";
$startTime = microtime(true);

$riwayatResponse = $client->get('/siswa/kehadiran/riwayat', [
    'headers' => ['Authorization' => "Bearer $token"]
]);

$responseTime = round((microtime(true) - $startTime) * 1000, 2);
$riwayatData = json_decode($riwayatResponse->getBody(), true);

if ($riwayatResponse->getStatusCode() === 200 && $riwayatData['success']) {
    echo "   ✅ Response time: {$responseTime}ms\n";
    echo "   ✅ Success! Total riwayat: {$riwayatData['total']}\n";

    if ($responseTime > 1000) {
        echo "   ⚠️  WARNING: Response time > 1 detik!\n";
    } elseif ($responseTime > 500) {
        echo "   ⚠️  PERHATIAN: Response time agak lambat.\n";
    } else {
        echo "   🚀 EXCELLENT: Response time sangat cepat!\n";
    }
} else {
    echo "   ❌ FAILED!\n";
    echo "   Status: " . $riwayatResponse->getStatusCode() . "\n";
    echo "   Response: " . $riwayatResponse->getBody() . "\n";
}
echo "\n";

// Step 4: Test Submit Kehadiran (if there are schedules today)
if (!empty($todayData['schedules'])) {
    $firstSchedule = $todayData['schedules'][0];

    echo "4️⃣  Test POST /api/siswa/kehadiran (Submit kehadiran)...\n";
    $startTime = microtime(true);

    $submitResponse = $client->post('/siswa/kehadiran', [
        'headers' => ['Authorization' => "Bearer $token"],
        'json' => [
            'schedule_id' => $firstSchedule['schedule_id'],
            'tanggal' => $todayData['tanggal'],
            'guru_hadir' => true,
            'catatan' => 'Test kehadiran - guru hadir tepat waktu'
        ]
    ]);

    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
    $submitData = json_decode($submitResponse->getBody(), true);

    if ($submitResponse->getStatusCode() === 201 || ($submitResponse->getStatusCode() === 200 && $submitData['success'])) {
        echo "   ✅ Response time: {$responseTime}ms\n";
        echo "   ✅ Success! {$submitData['message']}\n";
    } else {
        echo "   ❌ FAILED!\n";
        echo "   Status: " . $submitResponse->getStatusCode() . "\n";
        echo "   Response: " . $submitResponse->getBody() . "\n";
    }
} else {
    echo "4️⃣  SKIP: Tidak ada jadwal hari ini untuk test submit\n";
}

echo "\n";
echo "=" . str_repeat("=", 70) . "\n";
echo "  ✅ ALL TESTS COMPLETED!\n";
echo "=" . str_repeat("=", 70) . "\n";
echo "\n";
echo "📊 PERFORMANCE SUMMARY:\n";
echo "   - Jika response time < 500ms  = 🚀 EXCELLENT\n";
echo "   - Jika response time < 1000ms = ✅ GOOD\n";
echo "   - Jika response time > 1000ms = ⚠️  NEEDS OPTIMIZATION\n";
echo "\n";
