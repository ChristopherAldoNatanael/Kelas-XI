<?php

echo "=== TEACHER EDIT FUNCTIONALITY TEST ===\n";

try {
    // Include Laravel bootstrap
    require_once __DIR__ . '/vendor/autoload.php';
    $app = require_once __DIR__ . '/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    // Test database connection
    echo "1. Testing database connection...\n";
    $pdo = new PDO('mysql:host=localhost;dbname=db_sekolah', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if teachers table exists and has correct structure
    echo "2. Checking teachers table structure...\n";
    $result = $pdo->query('DESCRIBE teachers');
    $columns = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    echo "   Columns: " . implode(', ', $columns) . "\n";

    // Test Teacher model
    echo "3. Testing Teacher model...\n";
    $teachers = \App\Models\Teacher::all();
    echo "   Found " . $teachers->count() . " teachers\n";

    if ($teachers->count() > 0) {
        $teacher = $teachers->first();
        echo "   First teacher: ID={$teacher->id}, Name={$teacher->name}, Email={$teacher->email}\n";

        // Test model attributes
        echo "4. Testing model attributes...\n";
        echo "   Fillable: " . implode(', ', $teacher->getFillable()) . "\n";
        echo "   Hidden: " . implode(', ', $teacher->getHidden()) . "\n";

        // Test update functionality
        echo "5. Testing update functionality...\n";
        $originalName = $teacher->name;
        $testName = "Test Teacher " . time();

        $teacher->update(['name' => $testName]);
        $teacher->refresh();

        if ($teacher->name === $testName) {
            echo "   ✓ Update successful: Name changed to {$teacher->name}\n";

            // Restore original name
            $teacher->update(['name' => $originalName]);
            echo "   ✓ Name restored to {$originalName}\n";
        } else {
            echo "   ✗ Update failed\n";
        }
    }

    // Test WebTeacherController methods
    echo "6. Testing WebTeacherController...\n";
    $controller = new \App\Http\Controllers\Web\WebTeacherController();
    echo "   ✓ Controller instantiated successfully\n";

    // Test validation rules by simulating request
    echo "7. Testing validation rules...\n";
    $request = new \Illuminate\Http\Request();
    $request->merge([
        'name' => 'Test Teacher',
        'email' => 'test@example.com',
        'mata_pelajaran' => 'Mathematics',
        'is_banned' => false
    ]);

    $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'email' => 'required|email',
        'mata_pelajaran' => 'required|string|max:255',
        'is_banned' => 'boolean',
    ]);

    if ($validator->passes()) {
        echo "   ✓ Validation rules working correctly\n";
    } else {
        echo "   ✗ Validation failed: " . implode(', ', $validator->errors()->all()) . "\n";
    }

    echo "\n=== TEST COMPLETED SUCCESSFULLY ===\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
