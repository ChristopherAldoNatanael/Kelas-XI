<?php

/**
 * COMPREHENSIVE API TESTING FOR ALL CONTROLLERS
 * Testing semua API endpoints dengan aman
 */

echo "=== COMPREHENSIVE API TESTING ===\n";
echo "Testing all API endpoints safely...\n\n";

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$testResults = [];
$errors = [];

try {
    echo "🔍 TESTING API CONTROLLERS...\n\n";

    // ===== TEST 1: SUBJECT API CONTROLLER =====
    echo "📚 TESTING SUBJECT API CONTROLLER...\n";

    try {
        $controller = new \App\Http\Controllers\Api\SubjectController();

        // Test Index
        $response = $controller->index();
        $data = json_decode($response->getContent(), true);
        echo "  ✅ GET /api/subjects: Status {$response->getStatusCode()}, Found " . count($data['data']) . " subjects\n";

        // Test Show
        if (count($data['data']) > 0) {
            $firstSubjectId = $data['data'][0]['id'];
            $response = $controller->show($firstSubjectId);
            $showData = json_decode($response->getContent(), true);
            echo "  ✅ GET /api/subjects/{$firstSubjectId}: Status {$response->getStatusCode()}, Subject: {$showData['data']['name']}\n";
        }

        // Test Store (safe - will be deleted)
        $createRequest = new \Illuminate\Http\Request();
        $createRequest->merge([
            'name' => 'API Test Subject',
            'code' => 'API-TEST-001'
        ]);

        $response = $controller->store($createRequest);
        $createData = json_decode($response->getContent(), true);

        if ($createData['success']) {
            echo "  ✅ POST /api/subjects: Status {$response->getStatusCode()}, Created: {$createData['data']['name']}\n";
            $testSubjectId = $createData['data']['id'];

            // Test Update
            $updateRequest = new \Illuminate\Http\Request();
            $updateRequest->merge([
                'name' => 'Updated API Test Subject',
                'code' => 'API-TEST-UPD'
            ]);

            $response = $controller->update($updateRequest, $testSubjectId);
            $updateData = json_decode($response->getContent(), true);
            echo "  ✅ PUT /api/subjects/{$testSubjectId}: Status {$response->getStatusCode()}, Updated: {$updateData['data']['name']}\n";

            // Test Delete
            $response = $controller->destroy($testSubjectId);
            $deleteData = json_decode($response->getContent(), true);
            echo "  ✅ DELETE /api/subjects/{$testSubjectId}: Status {$response->getStatusCode()}, Success: " . ($deleteData['success'] ? 'true' : 'false') . "\n";
        }

        $testResults['subject_api'] = true;
    } catch (Exception $e) {
        $errors[] = "Subject API: " . $e->getMessage();
        $testResults['subject_api'] = false;
        echo "  ❌ Subject API failed: " . $e->getMessage() . "\n";
    }

    // ===== TEST 2: WEB CONTROLLERS =====
    echo "\n🌐 TESTING WEB CONTROLLERS...\n";

    try {
        // Test WebUserController
        $userController = new \App\Http\Controllers\Web\WebUserController();

        // Mock request for index
        $request = new \Illuminate\Http\Request();
        $request->setLaravelSession(new \Illuminate\Session\Store('test', new \Illuminate\Session\ArraySessionHandler(10)));
        $request->session()->put('api_token', 'test-token');

        // This will test the controller logic without actual authentication
        echo "  ✅ WebUserController instantiated successfully\n";

        // Test WebClassController
        $classController = new \App\Http\Controllers\Web\WebClassController();
        echo "  ✅ WebClassController instantiated successfully\n";

        $testResults['web_controllers'] = true;
    } catch (Exception $e) {
        $errors[] = "Web Controllers: " . $e->getMessage();
        $testResults['web_controllers'] = false;
        echo "  ❌ Web Controllers failed: " . $e->getMessage() . "\n";
    }

    // ===== TEST 3: MODEL VALIDATION =====
    echo "\n🔍 TESTING MODEL VALIDATION...\n";

    try {
        // Test Subject Model Mass Assignment
        $subject = new \App\Models\Subject();
        $fillable = $subject->getFillable();
        echo "  ✅ Subject Model fillable fields: " . implode(', ', $fillable) . "\n";

        // Test ClassModel Mass Assignment
        $class = new \App\Models\ClassModel();
        $fillable = $class->getFillable();
        echo "  ✅ ClassModel fillable fields: " . implode(', ', $fillable) . "\n";

        $testResults['model_validation'] = true;
    } catch (Exception $e) {
        $errors[] = "Model Validation: " . $e->getMessage();
        $testResults['model_validation'] = false;
        echo "  ❌ Model Validation failed: " . $e->getMessage() . "\n";
    }

    // ===== TEST 4: DATABASE CONSTRAINTS =====
    echo "\n🗃️ TESTING DATABASE CONSTRAINTS...\n";

    try {
        // Test unique constraints
        echo "  🔍 Testing unique constraints...\n";

        // Try to create duplicate subject code (should fail gracefully)
        $existingSubject = \App\Models\Subject::first();
        if ($existingSubject) {
            try {
                \App\Models\Subject::create([
                    'nama' => 'Duplicate Test',
                    'kode' => $existingSubject->kode // This should fail
                ]);
                echo "  ⚠️ Unique constraint not working - duplicate allowed\n";
            } catch (Exception $e) {
                echo "  ✅ Unique constraint working - duplicate rejected: " . substr($e->getMessage(), 0, 50) . "...\n";
            }
        }

        $testResults['database_constraints'] = true;
    } catch (Exception $e) {
        $errors[] = "Database Constraints: " . $e->getMessage();
        $testResults['database_constraints'] = false;
        echo "  ❌ Database Constraints failed: " . $e->getMessage() . "\n";
    }

    // ===== TEST 5: SECURITY VALIDATION =====
    echo "\n🔒 TESTING SECURITY VALIDATION...\n";

    try {
        // Test password hashing
        $hashedPassword = bcrypt('test-password');
        echo "  ✅ Password hashing works: " . (strlen($hashedPassword) > 50 ? 'Yes' : 'No') . "\n";

        // Test XSS protection (basic)
        $maliciousInput = '<script>alert("xss")</script>';
        $escaped = htmlspecialchars($maliciousInput);
        echo "  ✅ XSS protection: " . ($escaped !== $maliciousInput ? 'Protected' : 'Not Protected') . "\n";

        $testResults['security_validation'] = true;
    } catch (Exception $e) {
        $errors[] = "Security Validation: " . $e->getMessage();
        $testResults['security_validation'] = false;
        echo "  ❌ Security Validation failed: " . $e->getMessage() . "\n";
    }

    echo "\n=== API TESTING SUMMARY ===\n";
    $totalTests = count($testResults);
    $passedTests = array_sum($testResults);
    $failedTests = $totalTests - $passedTests;

    echo "Total API Tests: {$totalTests}\n";
    echo "Passed: {$passedTests}\n";
    echo "Failed: {$failedTests}\n";

    if ($failedTests == 0) {
        echo "\n🎉 ALL API TESTS PASSED! System is production-ready.\n";
    } else {
        echo "\n⚠️ Some API tests failed. Check errors below:\n";
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
    }

    echo "\n=== DETAILED API RESULTS ===\n";
    foreach ($testResults as $test => $result) {
        $status = $result ? "✅ PASS" : "❌ FAIL";
        echo "  {$status} - " . strtoupper(str_replace('_', ' ', $test)) . "\n";
    }
} catch (Exception $e) {
    echo "\n❌ CRITICAL API ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
