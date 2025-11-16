<?php

/**
 * TEST KEHADIRAN ENDPOINTS - PERFORMANCE CHECK
 * Test apakah endpoint kehadiran bikin server crash atau tidak
 */

echo "=================================================\n";
echo "   TEST KEHADIRAN ENDPOINTS - PERFORMANCE\n";
echo "=================================================\n\n";

$baseUrl = "http://127.0.0.1:8000/api";

// Test credentials (sesuaikan dengan database Anda)
$email = "siswa@example.com"; // Ganti dengan email siswa yang ada
$password = "password";        // Ganti dengan password yang benar

echo "STEP 1: Login sebagai Siswa\n";
echo "----------------------------\n";

$loginData = [
    'email' => $email,
    'password' => $password
];

$ch = curl_init("$baseUrl/auth/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$startLogin = microtime(true);
$response = curl_exec($ch);
$timeLogin = round((microtime(true) - $startLogin) * 1000, 2);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response Time: {$timeLogin}ms\n";

if ($httpCode !== 200) {
    echo "❌ LOGIN FAILED!\n";
    echo "Response: $response\n";
    exit(1);
}

$loginResult = json_decode($response, true);
if (!isset($loginResult['data']['token'])) {
    echo "❌ TOKEN NOT FOUND!\n";
    echo "Response: $response\n";
    exit(1);
}

$token = $loginResult['data']['token'];
$userName = $loginResult['data']['user']['nama'] ?? 'Unknown';
$userClass = $loginResult['data']['user']['class_id'] ?? 'N/A';

echo "✅ Login Success!\n";
echo "User: $userName (Class ID: $userClass)\n";
echo "Token: " . substr($token, 0, 20) . "...\n\n";

// ==============================================
// TEST 1: Get Today's Kehadiran Status
// ==============================================
echo "STEP 2: Test GET /api/siswa/kehadiran/today\n";
echo "--------------------------------------------\n";

$ch = curl_init("$baseUrl/siswa/kehadiran/today");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token",
    'Accept: application/json'
]);

$startToday = microtime(true);
$response = curl_exec($ch);
$timeToday = round((microtime(true) - $startToday) * 1000, 2);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response Time: {$timeToday}ms\n";

$todayResult = json_decode($response, true);

if ($httpCode === 200 && isset($todayResult['success']) && $todayResult['success']) {
    echo "✅ Today Status Success!\n";
    echo "Tanggal: " . ($todayResult['tanggal'] ?? 'N/A') . "\n";
    echo "Jumlah Jadwal: " . count($todayResult['schedules'] ?? []) . "\n";

    if (!empty($todayResult['schedules'])) {
        echo "\nContoh Jadwal Pertama:\n";
        $first = $todayResult['schedules'][0];
        echo "  - Period: " . ($first['period'] ?? 'N/A') . "\n";
        echo "  - Subject: " . ($first['subject'] ?? 'N/A') . "\n";
        echo "  - Teacher: " . ($first['teacher'] ?? 'N/A') . "\n";
        echo "  - Time: " . ($first['start_time'] ?? 'N/A') . " - " . ($first['end_time'] ?? 'N/A') . "\n";
        echo "  - Submitted: " . ($first['submitted'] ? 'Ya' : 'Belum') . "\n";
    }
} else {
    echo "⚠️ Today Status Warning/Error\n";
    echo "Response: " . substr($response, 0, 200) . "...\n";
}

echo "\n";

// ==============================================
// TEST 2: Submit Kehadiran (CRUD Test)
// ==============================================
echo "STEP 3: Test POST /api/siswa/kehadiran (Submit)\n";
echo "------------------------------------------------\n";

if (!empty($todayResult['schedules'])) {
    $firstSchedule = $todayResult['schedules'][0];
    $scheduleId = $firstSchedule['schedule_id'];
    $today = date('Y-m-d');

    $submitData = [
        'schedule_id' => $scheduleId,
        'tanggal' => $today,
        'guru_hadir' => true,
        'catatan' => 'Test performance - guru hadir tepat waktu'
    ];

    echo "Submitting untuk Schedule ID: $scheduleId\n";

    $ch = curl_init("$baseUrl/siswa/kehadiran");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($submitData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $startSubmit = microtime(true);
    $response = curl_exec($ch);
    $timeSubmit = round((microtime(true) - $startSubmit) * 1000, 2);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Code: $httpCode\n";
    echo "Response Time: {$timeSubmit}ms\n";

    $submitResult = json_decode($response, true);

    if (($httpCode === 200 || $httpCode === 201) && isset($submitResult['success']) && $submitResult['success']) {
        echo "✅ Submit Success!\n";
        echo "Message: " . ($submitResult['message'] ?? 'N/A') . "\n";
    } else {
        echo "⚠️ Submit Warning/Error\n";
        echo "Response: " . substr($response, 0, 200) . "...\n";
    }
} else {
    echo "⚠️ Skip - Tidak ada jadwal hari ini\n";
}

echo "\n";

// ==============================================
// TEST 3: Get Riwayat (History)
// ==============================================
echo "STEP 4: Test GET /api/siswa/kehadiran/riwayat\n";
echo "----------------------------------------------\n";

$ch = curl_init("$baseUrl/siswa/kehadiran/riwayat");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token",
    'Accept: application/json'
]);

$startRiwayat = microtime(true);
$response = curl_exec($ch);
$timeRiwayat = round((microtime(true) - $startRiwayat) * 1000, 2);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response Time: {$timeRiwayat}ms\n";

$riwayatResult = json_decode($response, true);

if ($httpCode === 200 && isset($riwayatResult['success']) && $riwayatResult['success']) {
    echo "✅ Riwayat Success!\n";
    echo "Total Records: " . ($riwayatResult['total'] ?? 0) . "\n";

    if (!empty($riwayatResult['data'])) {
        echo "\nContoh Record Pertama:\n";
        $first = $riwayatResult['data'][0];
        echo "  - ID: " . ($first['id'] ?? 'N/A') . "\n";
        echo "  - Tanggal: " . ($first['tanggal'] ?? 'N/A') . "\n";
        echo "  - Guru Hadir: " . ($first['guru_hadir'] ? 'Ya' : 'Tidak') . "\n";
        echo "  - Subject: " . ($first['schedule']['subject'] ?? 'N/A') . "\n";
        echo "  - Teacher: " . ($first['schedule']['teacher'] ?? 'N/A') . "\n";
        echo "  - Submitted By: " . ($first['submitted_by'] ?? 'N/A') . "\n";
    }
} else {
    echo "⚠️ Riwayat Warning/Error\n";
    echo "Response: " . substr($response, 0, 200) . "...\n";
}

echo "\n";

// ==============================================
// PERFORMANCE SUMMARY
// ==============================================
echo "=================================================\n";
echo "   PERFORMANCE SUMMARY\n";
echo "=================================================\n\n";

$totalTime = $timeLogin + $timeToday + ($timeSubmit ?? 0) + $timeRiwayat;

echo "Login Time:          {$timeLogin}ms\n";
echo "Today Status Time:   {$timeToday}ms\n";
if (isset($timeSubmit)) {
    echo "Submit Time:         {$timeSubmit}ms\n";
}
echo "Riwayat Time:        {$timeRiwayat}ms\n";
echo "----------------------------\n";
echo "TOTAL TIME:          {$totalTime}ms\n\n";

// Performance rating
if ($totalTime < 500) {
    echo "🚀 EXCELLENT! Server sangat cepat!\n";
} elseif ($totalTime < 1000) {
    echo "✅ GOOD! Server performanya bagus!\n";
} elseif ($totalTime < 2000) {
    echo "⚠️ MODERATE! Performa masih oke tapi bisa di-optimize.\n";
} else {
    echo "❌ SLOW! Server perlu optimasi serius!\n";
}

echo "\n";
echo "TIPS OPTIMASI:\n";
echo "- Pastikan database di-index dengan baik\n";
echo "- Gunakan eager loading untuk relationship\n";
echo "- Aktifkan caching untuk data yang sering diakses\n";
echo "- Monitor database queries dengan Laravel Debugbar\n";

echo "\n=================================================\n";
echo "Test selesai!\n";
echo "=================================================\n";
