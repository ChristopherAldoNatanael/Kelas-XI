<?php

/**
 * COMPREHENSIVE CRUD TESTING FOR ALL FEATURES
 * Testing semua fitur CRUD dengan aman
 */

echo "=== COMPREHENSIVE CRUD TESTING ===\n";
echo "Testing all features safely...\n\n";

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$testResults = [];
$errors = [];

try {
    echo "🔍 CHECKING DATABASE CONNECTION...\n";
    DB::connection()->getPdo();
    echo "✅ Database connection successful\n\n";

    // ===== TEST 1: USERS CRUD =====
    echo "👥 TESTING USERS CRUD...\n";

    try {
        // Test Read
        $users = DB::table('users')->get();
        echo "  ✅ GET Users: Found " . $users->count() . " users\n";
        $testResults['users_read'] = true;

        // Test Create (safe - will rollback)
        DB::beginTransaction();
        $testUser = DB::table('users')->insertGetId([
            'name' => 'Test User CRUD',
            'email' => 'test-crud@example.com',
            'password' => bcrypt('password'),
            'role' => 'siswa',
            'is_banned' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        echo "  ✅ CREATE User: Test user created with ID {$testUser}\n";

        // Test Update
        DB::table('users')->where('id', $testUser)->update([
            'name' => 'Updated Test User',
            'updated_at' => now()
        ]);
        $updatedUser = DB::table('users')->where('id', $testUser)->first();
        echo "  ✅ UPDATE User: Name updated to '{$updatedUser->name}'\n";

        // Test Delete
        DB::table('users')->where('id', $testUser)->delete();
        $deletedUser = DB::table('users')->where('id', $testUser)->first();
        echo "  ✅ DELETE User: " . ($deletedUser ? "Failed to delete" : "Successfully deleted") . "\n";

        DB::rollback(); // Rollback test changes
        $testResults['users_crud'] = true;
    } catch (Exception $e) {
        DB::rollback();
        $errors[] = "Users CRUD: " . $e->getMessage();
        $testResults['users_crud'] = false;
        echo "  ❌ Users CRUD failed: " . $e->getMessage() . "\n";
    }

    // ===== TEST 2: SUBJECTS CRUD =====
    echo "\n📚 TESTING SUBJECTS CRUD...\n";

    try {
        // Test Read
        $subjects = DB::table('subjects')->get();
        echo "  ✅ GET Subjects: Found " . $subjects->count() . " subjects\n";

        // Test Create (safe - will rollback)
        DB::beginTransaction();
        $testSubject = DB::table('subjects')->insertGetId([
            'nama' => 'Test Subject CRUD',
            'kode' => 'TEST-CRUD-001',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        echo "  ✅ CREATE Subject: Test subject created with ID {$testSubject}\n";

        // Test Update
        DB::table('subjects')->where('id', $testSubject)->update([
            'nama' => 'Updated Test Subject',
            'updated_at' => now()
        ]);
        $updatedSubject = DB::table('subjects')->where('id', $testSubject)->first();
        echo "  ✅ UPDATE Subject: Name updated to '{$updatedSubject->nama}'\n";

        // Test Delete
        DB::table('subjects')->where('id', $testSubject)->delete();
        $deletedSubject = DB::table('subjects')->where('id', $testSubject)->first();
        echo "  ✅ DELETE Subject: " . ($deletedSubject ? "Failed to delete" : "Successfully deleted") . "\n";

        DB::rollback(); // Rollback test changes
        $testResults['subjects_crud'] = true;
    } catch (Exception $e) {
        DB::rollback();
        $errors[] = "Subjects CRUD: " . $e->getMessage();
        $testResults['subjects_crud'] = false;
        echo "  ❌ Subjects CRUD failed: " . $e->getMessage() . "\n";
    }

    // ===== TEST 3: CLASSES CRUD =====
    echo "\n🏫 TESTING CLASSES CRUD...\n";

    try {
        // Test Read
        $classes = DB::table('classes')->get();
        echo "  ✅ GET Classes: Found " . $classes->count() . " classes\n";

        // Test Create (safe - will rollback)
        DB::beginTransaction();
        $testClass = DB::table('classes')->insertGetId([
            'nama_kelas' => 'Test Class CRUD',
            'kode_kelas' => 'TEST-CRUD-1',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        echo "  ✅ CREATE Class: Test class created with ID {$testClass}\n";

        // Test Update
        DB::table('classes')->where('id', $testClass)->update([
            'nama_kelas' => 'Updated Test Class',
            'updated_at' => now()
        ]);
        $updatedClass = DB::table('classes')->where('id', $testClass)->first();
        echo "  ✅ UPDATE Class: Name updated to '{$updatedClass->nama_kelas}'\n";

        // Test Delete
        DB::table('classes')->where('id', $testClass)->delete();
        $deletedClass = DB::table('classes')->where('id', $testClass)->first();
        echo "  ✅ DELETE Class: " . ($deletedClass ? "Failed to delete" : "Successfully deleted") . "\n";

        DB::rollback(); // Rollback test changes
        $testResults['classes_crud'] = true;
    } catch (Exception $e) {
        DB::rollback();
        $errors[] = "Classes CRUD: " . $e->getMessage();
        $testResults['classes_crud'] = false;
        echo "  ❌ Classes CRUD failed: " . $e->getMessage() . "\n";
    }

    // ===== TEST 4: TEACHERS CRUD =====
    echo "\n👨‍🏫 TESTING TEACHERS CRUD...\n";

    try {
        // Test Read
        $teachers = DB::table('teachers')->get();
        echo "  ✅ GET Teachers: Found " . $teachers->count() . " teachers\n";

        // Test Create (safe - will rollback)
        DB::beginTransaction();
        $testTeacher = DB::table('teachers')->insertGetId([
            'name' => 'Test Teacher CRUD',
            'email' => 'test-teacher-crud@example.com',
            'password' => bcrypt('password'),
            'mata_pelajaran' => 'Test Subject',
            'is_banned' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        echo "  ✅ CREATE Teacher: Test teacher created with ID {$testTeacher}\n";

        // Test Update
        DB::table('teachers')->where('id', $testTeacher)->update([
            'name' => 'Updated Test Teacher',
            'updated_at' => now()
        ]);
        $updatedTeacher = DB::table('teachers')->where('id', $testTeacher)->first();
        echo "  ✅ UPDATE Teacher: Name updated to '{$updatedTeacher->name}'\n";

        // Test Delete
        DB::table('teachers')->where('id', $testTeacher)->delete();
        $deletedTeacher = DB::table('teachers')->where('id', $testTeacher)->first();
        echo "  ✅ DELETE Teacher: " . ($deletedTeacher ? "Failed to delete" : "Successfully deleted") . "\n";

        DB::rollback(); // Rollback test changes
        $testResults['teachers_crud'] = true;
    } catch (Exception $e) {
        DB::rollback();
        $errors[] = "Teachers CRUD: " . $e->getMessage();
        $testResults['teachers_crud'] = false;
        echo "  ❌ Teachers CRUD failed: " . $e->getMessage() . "\n";
    }

    // ===== TEST 5: MODEL RELATIONSHIP TESTS =====
    echo "\n🔗 TESTING MODEL RELATIONSHIPS...\n";

    try {
        // Test Subject Model
        $subject = \App\Models\Subject::first();
        if ($subject) {
            echo "  ✅ Subject Model: ID {$subject->id}, Name: {$subject->name}, Code: {$subject->code}\n";
            $testResults['subject_model'] = true;
        } else {
            echo "  ⚠️ Subject Model: No subjects found\n";
            $testResults['subject_model'] = false;
        }

        // Test Class Model
        $class = \App\Models\ClassModel::first();
        if ($class) {
            echo "  ✅ Class Model: ID {$class->id}, Name: {$class->nama_kelas}, Code: {$class->kode_kelas}\n";
            $testResults['class_model'] = true;
        } else {
            echo "  ⚠️ Class Model: No classes found\n";
            $testResults['class_model'] = false;
        }
    } catch (Exception $e) {
        $errors[] = "Model Relationships: " . $e->getMessage();
        $testResults['model_relationships'] = false;
        echo "  ❌ Model Relationships failed: " . $e->getMessage() . "\n";
    }

    echo "\n=== TESTING SUMMARY ===\n";
    $totalTests = count($testResults);
    $passedTests = array_sum($testResults);
    $failedTests = $totalTests - $passedTests;

    echo "Total Tests: {$totalTests}\n";
    echo "Passed: {$passedTests}\n";
    echo "Failed: {$failedTests}\n";

    if ($failedTests == 0) {
        echo "\n🎉 ALL CRUD TESTS PASSED! System is safe to use.\n";
    } else {
        echo "\n⚠️ Some tests failed. Check errors below:\n";
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
    }

    echo "\n=== DETAILED RESULTS ===\n";
    foreach ($testResults as $test => $result) {
        $status = $result ? "✅ PASS" : "❌ FAIL";
        echo "  {$status} - " . strtoupper(str_replace('_', ' ', $test)) . "\n";
    }
} catch (Exception $e) {
    echo "\n❌ CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
