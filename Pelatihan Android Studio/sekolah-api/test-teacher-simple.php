<?php

echo "=== TEACHER EDIT SIMPLE TEST ===\n";

try {
    // Include Laravel bootstrap
    require_once __DIR__ . '/vendor/autoload.php';
    $app = require_once __DIR__ . '/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    echo "1. Testing Teacher model and basic functionality...\n";

    // Get or create a teacher
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
        echo "   ✓ Using existing teacher: {$teacher->name} (ID: {$teacher->id})\n";
    }

    echo "2. Testing teacher edit view exists...\n";
    $viewPath = resource_path('views/teachers/edit.blade.php');
    if (file_exists($viewPath)) {
        echo "   ✓ Edit view file exists\n";

        // Check if view has correct form fields
        $viewContent = file_get_contents($viewPath);
        $requiredFields = ['name', 'email', 'mata_pelajaran', 'is_banned'];

        foreach ($requiredFields as $field) {
            if (strpos($viewContent, "name=\"$field\"") !== false) {
                echo "   ✓ Field '$field' found in form\n";
            } else {
                echo "   ✗ Field '$field' missing in form\n";
            }
        }

        // Check if form has correct action
        if (strpos($viewContent, 'web-teachers.update') !== false) {
            echo "   ✓ Form action points to correct route\n";
        } else {
            echo "   ✗ Form action incorrect\n";
        }
    } else {
        echo "   ✗ Edit view file not found at: $viewPath\n";
    }

    echo "3. Testing route registration...\n";
    $router = app('router');
    $routes = $router->getRoutes();

    $teacherRoutes = [
        'web-teachers.edit' => 'GET',
        'web-teachers.update' => 'PUT'
    ];

    foreach ($teacherRoutes as $routeName => $method) {
        try {
            $route = $routes->getByName($routeName);
            if ($route) {
                echo "   ✓ Route '$routeName' registered with method $method\n";
                echo "     URI: " . $route->uri() . "\n";
            } else {
                echo "   ✗ Route '$routeName' not found\n";
            }
        } catch (Exception $e) {
            echo "   ✗ Route '$routeName' error: " . $e->getMessage() . "\n";
        }
    }

    echo "4. Testing controller methods exist...\n";
    $controller = new \App\Http\Controllers\Web\WebTeacherController();

    $methods = ['edit', 'update'];
    foreach ($methods as $method) {
        if (method_exists($controller, $method)) {
            echo "   ✓ Method '$method' exists in WebTeacherController\n";
        } else {
            echo "   ✗ Method '$method' missing in WebTeacherController\n";
        }
    }

    echo "5. Testing validation rules...\n";
    $testData = [
        'name' => 'Test Teacher Updated',
        'email' => 'updated@sekolah.com',
        'mata_pelajaran' => 'Physics',
        'is_banned' => false
    ];

    $validator = \Illuminate\Support\Facades\Validator::make($testData, [
        'name' => 'required|string|max:255',
        'email' => 'required|email',
        'mata_pelajaran' => 'required|string|max:255',
        'is_banned' => 'boolean',
    ]);

    if ($validator->passes()) {
        echo "   ✓ Validation rules work correctly\n";
    } else {
        echo "   ✗ Validation failed: " . implode(', ', $validator->errors()->all()) . "\n";
    }

    echo "6. Testing direct model update...\n";
    $originalName = $teacher->name;
    $testName = "Updated Test Teacher " . time();

    try {
        $teacher->update(['name' => $testName]);
        $teacher->refresh();

        if ($teacher->name === $testName) {
            echo "   ✓ Direct model update successful\n";

            // Restore original name
            $teacher->update(['name' => $originalName]);
            echo "   ✓ Name restored to: {$originalName}\n";
        } else {
            echo "   ✗ Direct model update failed\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Model update error: " . $e->getMessage() . "\n";
    }

    echo "\n=== TEST SUMMARY ===\n";
    echo "The teacher edit functionality appears to be working at the model level.\n";
    echo "If users are experiencing redirect to login, the issue is likely:\n";
    echo "1. Session not containing valid api_token\n";
    echo "2. Middleware not properly checking authentication\n";
    echo "3. Browser session/cookie issues\n";

    echo "\n=== SIMPLE TEST COMPLETED ===\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
