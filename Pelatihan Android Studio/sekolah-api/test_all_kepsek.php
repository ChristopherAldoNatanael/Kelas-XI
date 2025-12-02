<?php
// Test all Kepala Sekolah endpoints

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "=== Testing All Kepala Sekolah Endpoints ===" . PHP_EOL;

// Test Dashboard
$request = Illuminate\Http\Request::create('/api/kepala-sekolah/dashboard', 'GET');
$response = $kernel->handle($request);
$data = json_decode($response->getContent(), true);
echo "Dashboard: " . ($data['success'] ? 'OK ✓' : 'FAILED ✗') . PHP_EOL;
if (!$data['success']) echo "  Error: " . ($data['error'] ?? $data['message'] ?? 'Unknown') . PHP_EOL;

// Test Attendance List
$request = Illuminate\Http\Request::create('/api/kepala-sekolah/attendance', 'GET');
$response = $kernel->handle($request);
$data = json_decode($response->getContent(), true);
echo "Attendance List: " . ($data['success'] ? 'OK ✓' : 'FAILED ✗') . PHP_EOL;
if (!$data['success']) echo "  Error: " . ($data['error'] ?? $data['message'] ?? 'Unknown') . PHP_EOL;

// Test Teacher Performance
$request = Illuminate\Http\Request::create('/api/kepala-sekolah/teacher-performance', 'GET');
$response = $kernel->handle($request);
$data = json_decode($response->getContent(), true);
echo "Teacher Performance: " . ($data['success'] ? 'OK ✓' : 'FAILED ✗') . PHP_EOL;
if (!$data['success']) echo "  Error: " . ($data['error'] ?? $data['message'] ?? 'Unknown') . PHP_EOL;

// Test Teachers on Leave
$request = Illuminate\Http\Request::create('/api/kepala-sekolah/teachers-on-leave', 'GET');
$response = $kernel->handle($request);
$data = json_decode($response->getContent(), true);
echo "Teachers on Leave: " . ($data['success'] ? 'OK ✓' : 'FAILED ✗') . PHP_EOL;
if (!$data['success']) echo "  Error: " . ($data['error'] ?? $data['message'] ?? 'Unknown') . PHP_EOL;

echo PHP_EOL . "=== All tests completed ===" . PHP_EOL;
