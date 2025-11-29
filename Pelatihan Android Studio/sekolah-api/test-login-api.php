<?php

// Test authentication via HTTP API
$url = 'http://localhost:8000/api/login';
$data = [
    'email' => 'admin@example.com',
    'password' => 'password'
];

echo "🔑 Testing Login API...\n";
echo "URL: {$url}\n";
echo "Email: {$data['email']}\n";
echo "Password: {$data['password']}\n\n";

// Use curl to test the API
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

echo "HTTP Code: {$httpCode}\n\n";

if ($curlError) {
    echo "cURL Error: {$curlError}\n";
    echo "Make sure the Laravel server is running (php artisan serve)\n";
    exit(1);
}

if ($httpCode === 200) {
    echo "✅ SUCCESS! Login successful\n";
    $jsonResponse = json_decode($response, true);
    echo "Response: " . substr(json_encode($jsonResponse, JSON_PRETTY_PRINT), 0, 500) . "...\n";
    echo "\n🎉 AUTHENTICATION FIXED SUCCESSFULLY!\n";
    echo "The 'Invalid credentials' error has been resolved.\n";
} else {
    echo "❌ FAILED! Login failed\n";
    echo "Response: {$response}\n\n";

    if ($httpCode === 401) {
        echo "Error: Invalid credentials (the fix may not be applied correctly)\n";
    } elseif ($httpCode === 500) {
        echo "Error: Server error (check Laravel logs)\n";
    } else {
        echo "Error: Unexpected HTTP code {$httpCode}\n";
    }

    echo "\nMake sure:\n";
    echo "1. Laravel server is running\n";
    echo "2. Database is seeded with users\n";
    echo "3. User model has SoftDeletes trait\n";
}
