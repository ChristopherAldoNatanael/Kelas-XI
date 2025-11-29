<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TESTING SUBJECT API ENDPOINTS ===\n\n";

try {
    // Start Laravel application for testing
    $app->make(\Illuminate\Contracts\Http\Kernel::class);

    // Create a test request
    $request = new \Illuminate\Http\Request();

    // Test SubjectController methods
    $controller = new \App\Http\Controllers\Api\SubjectController();

    echo "1. Testing GET /api/subjects (index)...\n";
    $response = $controller->index();
    $data = json_decode($response->getContent(), true);
    echo "   Status: " . $response->getStatusCode() . "\n";
    echo "   Success: " . ($data['success'] ? 'true' : 'false') . "\n";
    echo "   Subjects count: " . count($data['data']) . "\n";

    if (count($data['data']) > 0) {
        $firstSubject = $data['data'][0];
        $subjectId = $firstSubject['id'];
        echo "   First subject: {$firstSubject['name']} ({$firstSubject['code']})\n";

        echo "\n2. Testing GET /api/subjects/{id} (show)...\n";
        $response = $controller->show($subjectId);
        $data = json_decode($response->getContent(), true);
        echo "   Status: " . $response->getStatusCode() . "\n";
        echo "   Success: " . ($data['success'] ? 'true' : 'false') . "\n";
        echo "   Subject: {$data['data']['name']} ({$data['data']['code']})\n";

        echo "\n3. Testing PUT /api/subjects/{id} (update)...\n";
        $updateRequest = new \Illuminate\Http\Request();
        $updateRequest->merge([
            'name' => 'Updated Subject Name',
            'code' => 'UPD-001'
        ]);

        $response = $controller->update($updateRequest, $subjectId);
        $data = json_decode($response->getContent(), true);
        echo "   Status: " . $response->getStatusCode() . "\n";
        echo "   Success: " . ($data['success'] ? 'true' : 'false') . "\n";

        if ($data['success']) {
            echo "   Updated subject: {$data['data']['name']} ({$data['data']['code']})\n";

            // Restore original values
            $restoreRequest = new \Illuminate\Http\Request();
            $restoreRequest->merge([
                'name' => $firstSubject['name'],
                'code' => $firstSubject['code']
            ]);

            $controller->update($restoreRequest, $subjectId);
            echo "   Restored original values\n";
        } else {
            echo "   Error: " . $data['message'] . "\n";
        }
    }

    echo "\n4. Testing POST /api/subjects (store)...\n";
    $createRequest = new \Illuminate\Http\Request();
    $createRequest->merge([
        'name' => 'New Test Subject',
        'code' => 'NTS-001'
    ]);

    $response = $controller->store($createRequest);
    $data = json_decode($response->getContent(), true);
    echo "   Status: " . $response->getStatusCode() . "\n";
    echo "   Success: " . ($data['success'] ? 'true' : 'false') . "\n";

    if ($data['success']) {
        echo "   Created subject: {$data['data']['name']} ({$data['data']['code']})\n";

        // Delete the test subject
        $testSubjectId = $data['data']['id'];
        $response = $controller->destroy($testSubjectId);
        $data = json_decode($response->getContent(), true);
        echo "   Deleted test subject\n";
    } else {
        echo "   Error: " . $data['message'] . "\n";
    }

    echo "\n✅ ALL API ENDPOINT TESTS COMPLETED!\n";
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
