<?php

// Test the dropdown/classes endpoint
$url = 'http://localhost:8000/api/dropdown/classes?major=RPL';
echo "Testing: $url\n";
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Accept: application/json'
    ]
]);
$result = file_get_contents($url, false, $context);
echo "Response: $result\n\n";

$url2 = 'http://localhost:8000/api/dropdown/classes?major=Rekayasa Perangkat Lunak';
echo "Testing: $url2\n";
$result2 = file_get_contents($url2, false, $context);
echo "Response: $result2\n\n";

$url3 = 'http://localhost:8000/api/dropdown/classes-public?major=RPL';
echo "Testing public endpoint: $url3\n";
$result3 = file_get_contents($url3, false, $context);
echo "Response: $result3\n\n";

$url4 = 'http://localhost:8000/api/dropdown/classes-public?major=Rekayasa Perangkat Lunak';
echo "Testing public endpoint: $url4\n";
$result4 = file_get_contents($url4, false, $context);
echo "Response: $result4\n\n";
