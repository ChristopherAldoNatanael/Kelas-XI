<?php

/**
 * Test endpoint siswa setelah cleanup kelas RPL
 */

// Simulate API requests
$baseUrl = 'http://localhost:8000/api';

echo "=== TEST ENDPOINT SETELAH CLEANUP ===\n\n";

// 1. Test dropdown classes
echo "1. Testing GET /dropdown/classes?major=Rekayasa Perangkat Lunak\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "$baseUrl/dropdown/classes?major=Rekayasa%20Perangkat%20Lunak");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Status: $httpCode\n";
if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "   Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
    echo "   Total kelas: " . count($data['data']) . "\n";
    echo "   Kelas:\n";
    foreach ($data['data'] as $class) {
        echo "      - {$class['name']} (ID: {$class['id']}, Level: {$class['level']})\n";
    }

    if (count($data['data']) === 3) {
        echo "   ✅ Jumlah kelas sudah benar (3 kelas)\n";
    } else {
        echo "   ❌ Jumlah kelas salah (harusnya 3)\n";
    }
} else {
    echo "   ❌ Request failed\n";
}

echo "\n";

// 2. Test schedules-mobile dengan class_id
echo "2. Testing GET /schedules-mobile?class_id=21 (X RPL)\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "$baseUrl/schedules-mobile?class_id=21");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Status: $httpCode\n";
if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "   Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
    echo "   Total jadwal: " . count($data['data']) . "\n";
    if (isset($data['data'][0])) {
        $first = $data['data'][0];
        echo "   Sample: {$first['day_of_week']} Period {$first['period_number']}\n";
        echo "           Subject: {$first['subject']['name']}\n";
        echo "   ✅ Jadwal berhasil dimuat\n";
    }
} else {
    echo "   ❌ Request failed\n";
}

echo "\n";

// 3. Test jadwal hari ini
$today = strtolower(date('l'));
echo "3. Testing GET /jadwal/hari-ini?class_id=22 (XI RPL, Hari: $today)\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "$baseUrl/jadwal/hari-ini?class_id=22");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   Status: $httpCode\n";
if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "   Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
    echo "   Total jadwal hari ini: " . count($data['data']) . "\n";
    echo "   ✅ Endpoint hari ini berfungsi\n";
} else {
    echo "   ❌ Request failed\n";
}

echo "\n✅ TEST SELESAI!\n";
