<?php

/**
 * Test Kehadiran Endpoints - Debugging JSON Response
 * Run: php test-kehadiran-debug.php
 */

echo "=== TEST KEHADIRAN ENDPOINTS - DEBUG MODE ===\n\n";

// Configuration
$baseUrl = 'http://127.0.0.1:8000/api';

// Step 1: Login to get token
echo "1. Login sebagai siswa...\n";
$loginData = [
    'email' => 'siswa@example.com',
    'password' => 'password123'
];

$ch = curl_init("$baseUrl/auth/login");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response Code: $httpCode\n";
$loginResponse = json_decode($response, true);

if (!isset($loginResponse['data']['token'])) {
    echo "❌ Login failed!\n";
    echo "Response: " . print_r($loginResponse, true) . "\n";
    exit(1);
}

$token = $loginResponse['data']['token'];
echo "✅ Login successful! Token: " . substr($token, 0, 20) . "...\n\n";

// Step 2: Test Today's Status Endpoint
echo "2. Testing GET /api/siswa/kehadiran/today...\n";
$ch = curl_init("$baseUrl/siswa/kehadiran/today");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response Length: " . strlen($response) . " bytes\n";

// Validate JSON
$jsonData = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ INVALID JSON! Error: " . json_last_error_msg() . "\n";
    echo "Raw Response (first 1000 chars):\n";
    echo substr($response, 0, 1000) . "\n";

    // Find problematic character
    echo "\n🔍 Searching for problematic character...\n";
    for ($i = 0; $i < strlen($response); $i++) {
        $char = $response[$i];
        if (!ctype_print($char) && $char !== "\n" && $char !== "\r" && $char !== "\t") {
            echo "Found non-printable char at position $i: " . ord($char) . "\n";
            echo "Context: " . substr($response, max(0, $i - 50), 100) . "\n";
            break;
        }
    }
    exit(1);
}

echo "✅ Valid JSON!\n";
echo "Success: " . ($jsonData['success'] ? 'true' : 'false') . "\n";
echo "Schedules count: " . count($jsonData['schedules'] ?? []) . "\n";

// Check each schedule
if (isset($jsonData['schedules'])) {
    echo "\nSchedules detail:\n";
    foreach ($jsonData['schedules'] as $idx => $schedule) {
        echo "  [$idx] Period {$schedule['period']}: {$schedule['subject']} - {$schedule['teacher']}\n";

        // Check for problematic fields
        foreach ($schedule as $key => $value) {
            if (is_string($value) && preg_match('/[^\x20-\x7E\s]/', $value)) {
                echo "    ⚠️  Field '$key' contains non-ASCII characters!\n";
            }
        }
    }
}

echo "\n✅ Today's status endpoint OK!\n\n";

// Step 3: Test Riwayat Endpoint
echo "3. Testing GET /api/siswa/kehadiran/riwayat...\n";
$ch = curl_init("$baseUrl/siswa/kehadiran/riwayat");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response Length: " . strlen($response) . " bytes\n";

$jsonData = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ INVALID JSON! Error: " . json_last_error_msg() . "\n";
    echo "Raw Response (first 1000 chars):\n";
    echo substr($response, 0, 1000) . "\n";
    exit(1);
}

echo "✅ Valid JSON!\n";
echo "Success: " . ($jsonData['success'] ? 'true' : 'false') . "\n";
echo "Total records: " . ($jsonData['total'] ?? 0) . "\n";

echo "\n=== ✅ ALL TESTS PASSED! ===\n";
