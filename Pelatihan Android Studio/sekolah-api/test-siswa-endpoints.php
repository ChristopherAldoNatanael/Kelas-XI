<?php

/**
 * Test Siswa Endpoints - Cek apakah semua endpoint siswa berfungsi
 * Jalankan dengan: php test-siswa-endpoints.php
 */

echo "=== TESTING SISWA ENDPOINTS ===\n\n";

$baseUrl = 'http://127.0.0.1:8000/api';

function testEndpoint($url, $description)
{
    echo "Testing: {$description}\n";
    echo "URL: {$url}\n";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);

    echo "Status: {$httpCode}\n";

    if ($httpCode == 200) {
        echo "✅ SUCCESS\n";
        if (isset($decoded['data'])) {
            $count = is_array($decoded['data']) ? count($decoded['data']) : 1;
            echo "Data count: {$count}\n";
            if ($count > 0 && is_array($decoded['data'])) {
                echo "Sample data: " . json_encode(array_slice($decoded['data'], 0, 1), JSON_PRETTY_PRINT) . "\n";
            }
        }
    } else {
        echo "❌ FAILED\n";
        echo "Response: " . substr($response, 0, 200) . "...\n";
    }

    echo "\n" . str_repeat("-", 80) . "\n\n";
}

// Test 1: Classes Endpoint (Public)
testEndpoint(
    "{$baseUrl}/dropdown/classes-public?major=Rekayasa%20Perangkat%20Lunak",
    "Get RPL Classes (Public)"
);

// Test 2: Classes Endpoint (With possible auth)
testEndpoint(
    "{$baseUrl}/dropdown/classes?major=Rekayasa%20Perangkat%20Lunak",
    "Get RPL Classes (Standard)"
);

// Test 3: All Schedules (Public)
testEndpoint(
    "{$baseUrl}/schedules-public",
    "Get All Schedules (Public)"
);

// Test 4: Mobile Schedules
testEndpoint(
    "{$baseUrl}/schedules-mobile",
    "Get Mobile Schedules"
);

// Test 5: Today's Schedule (Public)
testEndpoint(
    "{$baseUrl}/jadwal/hari-ini-public",
    "Get Today's Schedule (Public)"
);

// Test 6: Today's Schedule with class filter
testEndpoint(
    "{$baseUrl}/jadwal/hari-ini?class_id=1",
    "Get Today's Schedule for Class 1"
);

// Test 7: Subjects
testEndpoint(
    "{$baseUrl}/subjects",
    "Get All Subjects"
);

// Test 8: Teachers
testEndpoint(
    "{$baseUrl}/teachers",
    "Get All Teachers"
);

// Test 9: Classrooms
testEndpoint(
    "{$baseUrl}/classrooms",
    "Get All Classrooms"
);

echo "\n=== TESTING COMPLETED ===\n";
echo "Check the results above to see if all endpoints are working properly.\n";
echo "✅ = Endpoint working correctly\n";
echo "❌ = Endpoint has issues\n";
