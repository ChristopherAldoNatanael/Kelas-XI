<?php

echo "=== TEACHER EDIT AUTHENTICATION TEST ===\n";

try {
    // Include Laravel bootstrap
    require_once __DIR__ . '/vendor/autoload.php';
    $app = require_once __DIR__ . '/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    echo "1. Testing session-based authentication mechanism...\n";

    // Simulate session with api_token
    session(['api_token' => 'test-token-123']);

    echo "   Session api_token set: " . session('api_token') . "\n";

    // Test the controller method directly
    echo "2. Testing WebTeacherController edit method...\n";

    $controller = new \App\Http\Controllers\Web\WebTeacherController();

    // Mock request with session
    $request = new \Illuminate\Http\Request();
    $request->setLaravelSession(app('session'));

    try {
        // Create a teacher to test with
        $teacher = \App\Models\Teacher::first();
        if (!$teacher) {
            echo "   Creating test teacher...\n";
            $teacher = \App\Models\Teacher::create([
                'name' => 'Test Teacher',
                'email' => 'test.teacher@sekolah.com',
                'password' => bcrypt('password'),
                'mata_pelajaran' => 'Mathematics',
                'is_banned' => false
            ]);
            echo "   ✓ Test teacher created with ID: {$teacher->id}\n";
        } else {
            echo "   ✓ Using existing teacher with ID: {$teacher->id}\n";
        }

        // Test edit method without token (should redirect)
        echo "3. Testing edit without token...\n";
        session()->forget('api_token'); // Remove token

        try {
            $response = $controller->edit($teacher->id);
            if ($response instanceof \Illuminate\Http\RedirectResponse) {
                $targetUrl = $response->getTargetUrl();
                if (strpos($targetUrl, '/login') !== false) {
                    echo "   ✓ Correctly redirected to login page\n";
                } else {
                    echo "   ⚠ Redirected to: $targetUrl\n";
                }
            } else {
                echo "   ✗ Did not redirect as expected\n";
            }
        } catch (Exception $e) {
            echo "   ⚠ Exception: " . $e->getMessage() . "\n";
        }

        // Test edit method with token (should work)
        echo "4. Testing edit with token...\n";
        session(['api_token' => 'valid-test-token']);

        try {
            $response = $controller->edit($teacher->id);
            if ($response instanceof \Illuminate\View\View) {
                echo "   ✓ Successfully returned edit view\n";
                echo "   View name: " . $response->name() . "\n";

                // Check if teacher data is passed to view
                $viewData = $response->getData();
                if (isset($viewData['teacher']) && $viewData['teacher']->id == $teacher->id) {
                    echo "   ✓ Teacher data correctly passed to view\n";
                } else {
                    echo "   ✗ Teacher data not found in view\n";
                }
            } else {
                echo "   ✗ Did not return view as expected\n";
                if ($response instanceof \Illuminate\Http\RedirectResponse) {
                    echo "   Redirected to: " . $response->getTargetUrl() . "\n";
                }
            }
        } catch (Exception $e) {
            echo "   ✗ Exception: " . $e->getMessage() . "\n";
        }

        // Test update method
        echo "5. Testing update method...\n";
        session(['api_token' => 'valid-test-token']);

        $updateRequest = new \Illuminate\Http\Request();
        $updateRequest->setLaravelSession(app('session'));
        $updateRequest->merge([
            'name' => 'Updated Teacher Name',
            'email' => $teacher->email,
            'mata_pelajaran' => 'Updated Subject',
            'is_banned' => false
        ]);

        try {
            $response = $controller->update($updateRequest, $teacher->id);
            if ($response instanceof \Illuminate\Http\RedirectResponse) {
                $targetUrl = $response->getTargetUrl();
                if (strpos($targetUrl, 'teachers') !== false) {
                    echo "   ✓ Successfully updated and redirected to teachers index\n";

                    // Verify the update
                    $teacher->refresh();
                    if ($teacher->name === 'Updated Teacher Name') {
                        echo "   ✓ Teacher name updated successfully\n";

                        // Restore original name
                        $teacher->update(['name' => 'Test Teacher']);
                        echo "   ✓ Name restored\n";
                    } else {
                        echo "   ✗ Teacher name was not updated\n";
                    }
                } else {
                    echo "   ⚠ Redirected to unexpected URL: $targetUrl\n";
                }
            } else {
                echo "   ✗ Update did not redirect as expected\n";
            }
        } catch (Exception $e) {
            echo "   ✗ Update exception: " . $e->getMessage() . "\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Controller test failed: " . $e->getMessage() . "\n";
    }

    echo "\n=== AUTHENTICATION TEST COMPLETED ===\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
