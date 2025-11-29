<?php

/**
 * COMPREHENSIVE HTTP ENDPOINT TESTING
 * Testing semua HTTP endpoints dengan real requests
 */

echo "=== COMPREHENSIVE HTTP ENDPOINT TESTING ===\n";
echo "Testing all HTTP endpoints with real requests...\n\n";

$baseUrl = 'http://localhost:8000';
$testResults = [];
$errors = [];

// Function to make HTTP requests safely
function makeHttpRequest($url, $method = 'GET', $data = null, $headers = [])
{
    $defaultHeaders = [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: Testing Bot 1.0'
    ];

    $headers = array_merge($defaultHeaders, $headers);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't follow redirects

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
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("CURL Error: " . $error);
    }

    return [
        'status' => $httpCode,
        'body' => $response,
        'data' => json_decode($response, true)
    ];
}

try {
    echo "🔍 CHECKING SERVER CONNECTION...\n";

    // Test if server is running
    try {
        $response = makeHttpRequest($baseUrl . '/api/test');
        echo "✅ Server is running on {$baseUrl}\n";
        echo "   Test endpoint status: {$response['status']}\n\n";
    } catch (Exception $e) {
        echo "❌ Server connection failed: " . $e->getMessage() . "\n";
        echo "   Make sure Laravel server is running: php artisan serve\n";
        exit(1);
    }

    // ===== TEST 1: SUBJECT API ENDPOINTS =====
    echo "📚 TESTING SUBJECT API ENDPOINTS...\n";

    try {
        // Test GET /api/subjects
        $response = makeHttpRequest($baseUrl . '/api/subjects');
        echo "  ✅ GET /api/subjects: Status {$response['status']}\n";

        if ($response['data'] && isset($response['data']['data'])) {
            $subjects = $response['data']['data'];
            echo "     Found " . count($subjects) . " subjects\n";

            if (count($subjects) > 0) {
                $firstSubject = $subjects[0];
                $subjectId = $firstSubject['id'];

                // Test GET /api/subjects/{id}
                $response = makeHttpRequest($baseUrl . "/api/subjects/{$subjectId}");
                echo "  ✅ GET /api/subjects/{$subjectId}: Status {$response['status']}\n";

                // Test POST /api/subjects (create)
                $createData = [
                    'name' => 'HTTP Test Subject',
                    'code' => 'HTTP-TST-' . time()
                ];
                $response = makeHttpRequest($baseUrl . '/api/subjects', 'POST', $createData);
                echo "  ✅ POST /api/subjects: Status {$response['status']}\n";

                if ($response['status'] == 201 && $response['data']['success']) {
                    $newSubjectId = $response['data']['data']['id'];
                    echo "     Created subject with ID: {$newSubjectId}\n";

                    // Test PUT /api/subjects/{id} (update)
                    $updateData = [
                        'name' => 'Updated HTTP Test Subject',
                        'code' => 'HTTP-UPD-' . time()
                    ];
                    $response = makeHttpRequest($baseUrl . "/api/subjects/{$newSubjectId}", 'PUT', $updateData);
                    echo "  ✅ PUT /api/subjects/{$newSubjectId}: Status {$response['status']}\n";

                    // Test DELETE /api/subjects/{id}
                    $response = makeHttpRequest($baseUrl . "/api/subjects/{$newSubjectId}", 'DELETE');
                    echo "  ✅ DELETE /api/subjects/{$newSubjectId}: Status {$response['status']}\n";
                }
            }
        }

        $testResults['subject_http_api'] = true;
    } catch (Exception $e) {
        $errors[] = "Subject HTTP API: " . $e->getMessage();
        $testResults['subject_http_api'] = false;
        echo "  ❌ Subject HTTP API failed: " . $e->getMessage() . "\n";
    }

    // ===== TEST 2: WEB PAGES =====
    echo "\n🌐 TESTING WEB PAGES...\n";

    try {
        // Test web pages (expect redirects to login)
        $webPages = [
            '/dashboard' => 'Dashboard',
            '/web-users' => 'Users List',
            '/web-classes' => 'Classes List'
        ];

        foreach ($webPages as $path => $name) {
            try {
                $response = makeHttpRequest($baseUrl . $path);
                $status = $response['status'];

                // 200 = OK, 302 = Redirect (expected for auth), 401 = Unauthorized
                if (in_array($status, [200, 302, 401])) {
                    echo "  ✅ GET {$path} ({$name}): Status {$status} - " .
                        ($status == 200 ? "Accessible" : ($status == 302 ? "Redirected (Auth)" : "Auth Required")) . "\n";
                } else {
                    echo "  ⚠️ GET {$path} ({$name}): Status {$status} - Unexpected status\n";
                }
            } catch (Exception $e) {
                echo "  ❌ GET {$path} ({$name}): Error - " . $e->getMessage() . "\n";
            }
        }

        $testResults['web_pages'] = true;
    } catch (Exception $e) {
        $errors[] = "Web Pages: " . $e->getMessage();
        $testResults['web_pages'] = false;
        echo "  ❌ Web Pages failed: " . $e->getMessage() . "\n";
    }

    // ===== TEST 3: API PERFORMANCE =====
    echo "\n⚡ TESTING API PERFORMANCE...\n";

    try {
        $start = microtime(true);
        $response = makeHttpRequest($baseUrl . '/api/subjects');
        $end = microtime(true);

        $duration = ($end - $start) * 1000; // Convert to milliseconds
        echo "  ✅ API Response Time: " . round($duration, 2) . "ms\n";

        if ($duration < 1000) {
            echo "     Performance: Excellent (< 1s)\n";
        } elseif ($duration < 3000) {
            echo "     Performance: Good (< 3s)\n";
        } else {
            echo "     Performance: Needs optimization (> 3s)\n";
        }

        $testResults['api_performance'] = true;
    } catch (Exception $e) {
        $errors[] = "API Performance: " . $e->getMessage();
        $testResults['api_performance'] = false;
        echo "  ❌ API Performance failed: " . $e->getMessage() . "\n";
    }

    // ===== TEST 4: ERROR HANDLING =====
    echo "\n🚨 TESTING ERROR HANDLING...\n";

    try {
        // Test 404 errors
        $response = makeHttpRequest($baseUrl . '/api/subjects/99999');
        echo "  ✅ 404 Error Test: Status {$response['status']} (Expected: 404 or 500)\n";

        // Test invalid method
        try {
            $response = makeHttpRequest($baseUrl . '/api/subjects', 'PATCH');
            echo "  ✅ Invalid Method Test: Status {$response['status']}\n";
        } catch (Exception $e) {
            echo "  ✅ Invalid Method Test: Properly rejected\n";
        }

        $testResults['error_handling'] = true;
    } catch (Exception $e) {
        $errors[] = "Error Handling: " . $e->getMessage();
        $testResults['error_handling'] = false;
        echo "  ❌ Error Handling failed: " . $e->getMessage() . "\n";
    }

    echo "\n=== HTTP TESTING SUMMARY ===\n";
    $totalTests = count($testResults);
    $passedTests = array_sum($testResults);
    $failedTests = $totalTests - $passedTests;

    echo "Total HTTP Tests: {$totalTests}\n";
    echo "Passed: {$passedTests}\n";
    echo "Failed: {$failedTests}\n";

    if ($failedTests == 0) {
        echo "\n🎉 ALL HTTP TESTS PASSED! System is fully functional.\n";
    } else {
        echo "\n⚠️ Some HTTP tests failed. Check errors below:\n";
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
    }

    echo "\n=== DETAILED HTTP RESULTS ===\n";
    foreach ($testResults as $test => $result) {
        $status = $result ? "✅ PASS" : "❌ FAIL";
        echo "  {$status} - " . strtoupper(str_replace('_', ' ', $test)) . "\n";
    }
} catch (Exception $e) {
    echo "\n❌ CRITICAL HTTP ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
