<?php

echo "=== TEACHER EDIT HTTP TEST ===\n";

// Test the actual HTTP endpoints
$baseUrl = 'http://localhost:8000';
$teacherId = 1; // Using the existing teacher

// First, get login token
echo "1. Getting login token...\n";
$loginData = [
    'email' => 'admin@sekolah.com',
    'password' => 'admin123'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/login');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $loginResponse = json_decode($response, true);
    if (isset($loginResponse['access_token'])) {
        $token = $loginResponse['access_token'];
        echo "   ✓ Login successful, token obtained\n";

        // Test accessing teacher edit page
        echo "2. Testing teacher edit page access...\n";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/web-teachers/' . $teacherId . '/edit');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Cookie: api_token=' . $token . '; laravel_session=test'
        ]);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't follow redirects

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        echo "   HTTP Code: $httpCode\n";
        if ($redirectUrl) {
            echo "   Redirect URL: $redirectUrl\n";
        }

        if ($httpCode === 200) {
            echo "   ✓ Teacher edit page accessible\n";
            if (strpos($response, 'Edit Teacher') !== false) {
                echo "   ✓ Edit form loaded correctly\n";
            } else {
                echo "   ⚠ Edit form might have issues\n";
            }
        } elseif ($httpCode === 302) {
            if (strpos($redirectUrl, '/login') !== false) {
                echo "   ✗ Redirected to login page - authentication issue\n";
            } else {
                echo "   ℹ Redirected to: $redirectUrl\n";
            }
        } else {
            echo "   ✗ Failed to access teacher edit page\n";
        }

        // Test with session-based authentication
        echo "3. Testing with session-based authentication...\n";

        // First, login via web form
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/login');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'email' => 'admin@sekolah.com',
            'password' => 'admin123',
            '_token' => 'test-token' // This might need CSRF token
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
        curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo "   Web login HTTP Code: $httpCode\n";

        // Now try to access teacher edit with cookies
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/web-teachers/' . $teacherId . '/edit');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        echo "   Edit page with cookies HTTP Code: $httpCode\n";
        if ($redirectUrl) {
            echo "   Redirect URL: $redirectUrl\n";
        }
    } else {
        echo "   ✗ Login failed - no token received\n";
    }
} else {
    echo "   ✗ Login request failed with HTTP code: $httpCode\n";
    echo "   Response: $response\n";
}

echo "\n=== HTTP TEST COMPLETED ===\n";
