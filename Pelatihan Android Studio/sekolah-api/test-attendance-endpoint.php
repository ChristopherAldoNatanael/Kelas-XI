<?php

/**
 * TEST ATTENDANCE ENDPOINT - Check for server crash
 * Test siswa/kehadiran/riwayat endpoint
 */

echo "=================================================\n";
echo "   TEST ATTENDANCE ENDPOINT (RIWAYAT)\n";
echo "=================================================\n\n";

$baseUrl = "http://127.0.0.1:8000/api";
$email = "test@example.com";
$password = "password";

// Step 1: Login
echo "STEP 1: Login as Student\n";
echo "----------------------------\n";

$ch = curl_init("$baseUrl/auth/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => $email,
    'password' => $password
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$startTime = microtime(true);
$response = curl_exec($ch);
$loginTime = round((microtime(true) - $startTime) * 1000, 2);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response Time: {$loginTime}ms\n";

if ($httpCode !== 200) {
    echo "❌ LOGIN FAILED!\n";
    echo "Response: $response\n";
    exit(1);
}

$loginResult = json_decode($response, true);
if (!isset($loginResult['data']['token'])) {
    echo "❌ TOKEN NOT FOUND!\n";
    exit(1);
}

$token = $loginResult['data']['token'];
echo "✅ Login successful\n";
echo "Token: " . substr($token, 0, 20) . "...\n\n";

// Step 2: Get Attendance History
echo "STEP 2: Get Attendance History (Riwayat)\n";
echo "-------------------------------------------\n";

$ch = curl_init("$baseUrl/siswa/kehadiran/riwayat");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout

$startTime = microtime(true);
$response = curl_exec($ch);
$responseTime = round((microtime(true) - $startTime) * 1000, 2);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response Time: {$responseTime}ms\n";

if ($error) {
    echo "❌ CURL ERROR: $error\n";
    exit(1);
}

if ($httpCode !== 200) {
    echo "❌ REQUEST FAILED!\n";
    echo "Response: $response\n";
    exit(1);
}

$result = json_decode($response, true);
echo "✅ Request successful\n";
echo "Total records: " . ($result['total'] ?? 0) . "\n";

// Performance check
if ($responseTime > 3000) {
    echo "⚠️  WARNING: Response time > 3 seconds (SLOW!)\n";
} elseif ($responseTime > 1000) {
    echo "⚠️  Response time > 1 second (could be optimized)\n";
} else {
    echo "✅ Response time is good\n";
}

echo "\nSample data:\n";
echo json_encode(array_slice($result['data'] ?? [], 0, 3), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

echo "\n\n=================================================\n";
echo "   TEST COMPLETED\n";
echo "=================================================\n";
