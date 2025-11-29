<?php

echo "=== TESTING SUBJECT API WITH HTTP REQUESTS ===\n\n";

$baseUrl = 'http://localhost:8000/api';

// Function to make HTTP requests
function makeRequest($url, $method = 'GET', $data = null)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

try {
    // Test 1: GET all subjects
    echo "1. Testing GET {$baseUrl}/subjects...\n";
    $response = makeRequest("{$baseUrl}/subjects");
    echo "   Status: {$response['status']}\n";
    echo "   Success: " . ($response['body']['success'] ? 'true' : 'false') . "\n";
    echo "   Subjects count: " . count($response['body']['data']) . "\n";

    if (count($response['body']['data']) > 0) {
        $firstSubject = $response['body']['data'][0];
        $subjectId = $firstSubject['id'];
        echo "   First subject: {$firstSubject['name']} ({$firstSubject['code']})\n";

        // Test 2: GET specific subject
        echo "\n2. Testing GET {$baseUrl}/subjects/{$subjectId}...\n";
        $response = makeRequest("{$baseUrl}/subjects/{$subjectId}");
        echo "   Status: {$response['status']}\n";
        echo "   Success: " . ($response['body']['success'] ? 'true' : 'false') . "\n";
        echo "   Subject: {$response['body']['data']['name']} ({$response['body']['data']['code']})\n";

        // Test 3: PUT update subject
        echo "\n3. Testing PUT {$baseUrl}/subjects/{$subjectId}...\n";
        $updateData = [
            'name' => 'HTTP Test Updated Subject',
            'code' => 'HTTP-001'
        ];
        $response = makeRequest("{$baseUrl}/subjects/{$subjectId}", 'PUT', $updateData);
        echo "   Status: {$response['status']}\n";
        echo "   Success: " . ($response['body']['success'] ? 'true' : 'false') . "\n";

        if ($response['body']['success']) {
            echo "   Updated subject: {$response['body']['data']['name']} ({$response['body']['data']['code']})\n";

            // Restore original values
            $restoreData = [
                'name' => $firstSubject['name'],
                'code' => $firstSubject['code']
            ];
            $response = makeRequest("{$baseUrl}/subjects/{$subjectId}", 'PUT', $restoreData);
            echo "   Restored original values\n";
        } else {
            echo "   Update failed: " . $response['body']['message'] . "\n";
            if (isset($response['body']['error'])) {
                echo "   Error: " . $response['body']['error'] . "\n";
            }
        }
    }

    // Test 4: POST create new subject
    echo "\n4. Testing POST {$baseUrl}/subjects...\n";
    $createData = [
        'name' => 'HTTP Test New Subject',
        'code' => 'HTTP-NEW-001'
    ];
    $response = makeRequest("{$baseUrl}/subjects", 'POST', $createData);
    echo "   Status: {$response['status']}\n";
    echo "   Success: " . ($response['body']['success'] ? 'true' : 'false') . "\n";

    if ($response['body']['success']) {
        echo "   Created subject: {$response['body']['data']['name']} ({$response['body']['data']['code']})\n";

        // Delete the test subject
        $testSubjectId = $response['body']['data']['id'];
        $response = makeRequest("{$baseUrl}/subjects/{$testSubjectId}", 'DELETE');
        echo "   Deleted test subject\n";
    } else {
        echo "   Creation failed: " . $response['body']['message'] . "\n";
        if (isset($response['body']['error'])) {
            echo "   Error: " . $response['body']['error'] . "\n";
        }
    }

    echo "\n✅ ALL HTTP API TESTS COMPLETED!\n";
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
