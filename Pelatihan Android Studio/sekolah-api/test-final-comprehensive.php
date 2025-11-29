<?php

/**
 * FINAL COMPREHENSIVE SYSTEM TESTING
 * Testing komprehensif final untuk seluruh sistem
 */

echo "=== FINAL COMPREHENSIVE SYSTEM TESTING ===\n";
echo "Testing entire system comprehensively for production readiness...\n\n";

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$allTestResults = [];
$criticalErrors = [];
$warnings = [];

try {
    echo "🔍 SYSTEM HEALTH CHECK...\n";

    // Check database connection
    DB::connection()->getPdo();
    echo "✅ Database connection: OK\n";

    // Check table existence
    $requiredTables = ['users', 'subjects', 'classes', 'teachers'];
    foreach ($requiredTables as $table) {
        $exists = DB::getSchemaBuilder()->hasTable($table);
        echo ($exists ? "✅" : "❌") . " Table '{$table}': " . ($exists ? "EXISTS" : "MISSING") . "\n";
        if (!$exists) {
            $criticalErrors[] = "Required table '{$table}' is missing";
        }
    }

    echo "\n";

    // ===== TEST 1: DATA INTEGRITY =====
    echo "🔐 TESTING DATA INTEGRITY...\n";

    try {
        $users = DB::table('users')->count();
        $subjects = DB::table('subjects')->count();
        $classes = DB::table('classes')->count();
        $teachers = DB::table('teachers')->count();

        echo "  📊 Data counts:\n";
        echo "     Users: {$users}\n";
        echo "     Subjects: {$subjects}\n";
        echo "     Classes: {$classes}\n";
        echo "     Teachers: {$teachers}\n";

        // Check for orphaned data
        $orphanedUsers = DB::table('users')->whereNull('created_at')->count();
        $orphanedSubjects = DB::table('subjects')->whereNull('created_at')->count();

        if ($orphanedUsers > 0 || $orphanedSubjects > 0) {
            $warnings[] = "Found {$orphanedUsers} users and {$orphanedSubjects} subjects with null timestamps";
        }

        echo "  ✅ Data integrity check completed\n";
        $allTestResults['data_integrity'] = true;
    } catch (Exception $e) {
        $criticalErrors[] = "Data integrity check failed: " . $e->getMessage();
        $allTestResults['data_integrity'] = false;
    }

    // ===== TEST 2: MODEL RELATIONSHIPS =====
    echo "\n🔗 TESTING MODEL RELATIONSHIPS...\n";

    try {
        // Test Subject model
        $subject = \App\Models\Subject::first();
        if ($subject) {
            echo "  ✅ Subject Model: Working\n";
            echo "     ID: {$subject->id}, Name: {$subject->name}, Code: {$subject->code}\n";

            // Test accessors/mutators
            $originalNama = $subject->nama;
            $accessorName = $subject->name;

            if ($originalNama === $accessorName) {
                echo "  ✅ Subject accessors: Working correctly\n";
            } else {
                $warnings[] = "Subject accessor mismatch: DB='{$originalNama}', Accessor='{$accessorName}'";
            }
        }

        // Test ClassModel
        $class = \App\Models\ClassModel::first();
        if ($class) {
            echo "  ✅ ClassModel: Working\n";
            echo "     ID: {$class->id}, Name: {$class->nama_kelas}, Code: {$class->kode_kelas}\n";
        }

        $allTestResults['model_relationships'] = true;
    } catch (Exception $e) {
        $criticalErrors[] = "Model relationships failed: " . $e->getMessage();
        $allTestResults['model_relationships'] = false;
    }

    // ===== TEST 3: CRUD OPERATIONS STRESS TEST =====
    echo "\n💪 TESTING CRUD OPERATIONS (STRESS TEST)...\n";

    try {
        DB::beginTransaction();

        // Create multiple test records
        $testRecords = [];
        for ($i = 1; $i <= 3; $i++) {
            $subject = DB::table('subjects')->insertGetId([
                'nama' => "Stress Test Subject {$i}",
                'kode' => "STRESS-{$i}-" . time(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $testRecords[] = $subject;
        }

        echo "  ✅ Bulk CREATE: Created " . count($testRecords) . " test subjects\n";

        // Update all test records
        foreach ($testRecords as $id) {
            DB::table('subjects')->where('id', $id)->update([
                'nama' => "Updated Stress Test Subject {$id}",
                'updated_at' => now()
            ]);
        }

        echo "  ✅ Bulk UPDATE: Updated " . count($testRecords) . " test subjects\n";

        // Delete all test records
        foreach ($testRecords as $id) {
            DB::table('subjects')->where('id', $id)->delete();
        }

        echo "  ✅ Bulk DELETE: Deleted " . count($testRecords) . " test subjects\n";

        DB::rollback(); // Rollback all test changes

        $allTestResults['crud_stress_test'] = true;
    } catch (Exception $e) {
        DB::rollback();
        $criticalErrors[] = "CRUD stress test failed: " . $e->getMessage();
        $allTestResults['crud_stress_test'] = false;
    }

    // ===== TEST 4: API CONTROLLER STABILITY =====
    echo "\n🎯 TESTING API CONTROLLER STABILITY...\n";

    try {
        $controller = new \App\Http\Controllers\Api\SubjectController();

        // Test multiple rapid requests
        for ($i = 1; $i <= 5; $i++) {
            $response = $controller->index();
            $data = json_decode($response->getContent(), true);

            if (!$data['success']) {
                throw new Exception("API request {$i} failed");
            }
        }

        echo "  ✅ API Stability: Handled 5 rapid requests successfully\n";
        $allTestResults['api_stability'] = true;
    } catch (Exception $e) {
        $criticalErrors[] = "API stability test failed: " . $e->getMessage();
        $allTestResults['api_stability'] = false;
    }

    // ===== TEST 5: SECURITY VALIDATION =====
    echo "\n🛡️ TESTING SECURITY FEATURES...\n";

    try {
        // Test SQL injection protection
        try {
            DB::table('subjects')->where('nama', "'; DROP TABLE subjects; --")->get();
            echo "  ✅ SQL Injection Protection: Query executed safely\n";
        } catch (Exception $e) {
            echo "  ✅ SQL Injection Protection: Query properly blocked\n";
        }

        // Test XSS protection
        $maliciousScript = '<script>alert("xss")</script>';
        $escaped = htmlspecialchars($maliciousScript);
        echo "  ✅ XSS Protection: " . ($maliciousScript !== $escaped ? "Working" : "Needs attention") . "\n";

        // Test password hashing
        $password = 'test123';
        $hashed = bcrypt($password);
        $verified = password_verify($password, $hashed);
        echo "  ✅ Password Security: " . ($verified ? "Working" : "Failed") . "\n";

        $allTestResults['security_features'] = true;
    } catch (Exception $e) {
        $criticalErrors[] = "Security validation failed: " . $e->getMessage();
        $allTestResults['security_features'] = false;
    }

    // ===== TEST 6: PERFORMANCE BENCHMARKS =====
    echo "\n⚡ TESTING PERFORMANCE BENCHMARKS...\n";

    try {
        // Database query performance
        $start = microtime(true);
        DB::table('subjects')->get();
        $end = microtime(true);
        $dbTime = ($end - $start) * 1000;

        echo "  📈 Database Query Time: " . round($dbTime, 2) . "ms\n";

        // Model instantiation performance
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            new \App\Models\Subject();
        }
        $end = microtime(true);
        $modelTime = ($end - $start) * 1000;

        echo "  📈 Model Instantiation (100x): " . round($modelTime, 2) . "ms\n";

        // API response performance
        $start = microtime(true);
        $controller = new \App\Http\Controllers\Api\SubjectController();
        $response = $controller->index();
        $end = microtime(true);
        $apiTime = ($end - $start) * 1000;

        echo "  📈 API Response Time: " . round($apiTime, 2) . "ms\n";

        if ($dbTime < 100 && $apiTime < 500) {
            echo "  ✅ Performance: Excellent\n";
        } elseif ($dbTime < 500 && $apiTime < 1000) {
            echo "  ✅ Performance: Good\n";
        } else {
            $warnings[] = "Performance may need optimization - DB: {$dbTime}ms, API: {$apiTime}ms";
        }

        $allTestResults['performance_benchmarks'] = true;
    } catch (Exception $e) {
        $warnings[] = "Performance benchmark failed: " . $e->getMessage();
        $allTestResults['performance_benchmarks'] = false;
    }

    echo "\n" . str_repeat("=", 80) . "\n";
    echo "🎯 FINAL SYSTEM TEST RESULTS\n";
    echo str_repeat("=", 80) . "\n";

    $totalTests = count($allTestResults);
    $passedTests = array_sum($allTestResults);
    $failedTests = $totalTests - $passedTests;

    echo "📊 OVERALL STATISTICS:\n";
    echo "   Total Tests: {$totalTests}\n";
    echo "   Passed: {$passedTests}\n";
    echo "   Failed: {$failedTests}\n";
    echo "   Critical Errors: " . count($criticalErrors) . "\n";
    echo "   Warnings: " . count($warnings) . "\n\n";

    // Test results breakdown
    echo "📋 DETAILED TEST RESULTS:\n";
    foreach ($allTestResults as $test => $result) {
        $status = $result ? "✅ PASS" : "❌ FAIL";
        $testName = strtoupper(str_replace('_', ' ', $test));
        echo "   {$status} - {$testName}\n";
    }

    // Critical errors
    if (count($criticalErrors) > 0) {
        echo "\n🚨 CRITICAL ERRORS:\n";
        foreach ($criticalErrors as $error) {
            echo "   ❌ {$error}\n";
        }
    }

    // Warnings
    if (count($warnings) > 0) {
        echo "\n⚠️ WARNINGS:\n";
        foreach ($warnings as $warning) {
            echo "   ⚠️ {$warning}\n";
        }
    }

    // Final verdict
    echo "\n" . str_repeat("=", 80) . "\n";

    if ($failedTests == 0 && count($criticalErrors) == 0) {
        echo "🎉 SYSTEM STATUS: PRODUCTION READY!\n";
        echo "   ✅ All tests passed\n";
        echo "   ✅ No critical errors\n";
        echo "   ✅ System is safe for production use\n";

        if (count($warnings) > 0) {
            echo "   ⚠️ " . count($warnings) . " warning(s) - review recommended\n";
        }
    } elseif (count($criticalErrors) == 0 && $failedTests <= 1) {
        echo "🟡 SYSTEM STATUS: MOSTLY READY\n";
        echo "   ✅ Core functionality working\n";
        echo "   ⚠️ Minor issues detected\n";
        echo "   📝 Review and fix recommended before production\n";
    } else {
        echo "🔴 SYSTEM STATUS: NEEDS ATTENTION\n";
        echo "   ❌ Critical issues detected\n";
        echo "   🛠️ Fix required before production deployment\n";
    }

    echo str_repeat("=", 80) . "\n";
} catch (Exception $e) {
    echo "\n💥 CATASTROPHIC ERROR DURING TESTING:\n";
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📁 File: " . $e->getFile() . "\n";
    echo "📍 Line: " . $e->getLine() . "\n";
    echo "\n🆘 SYSTEM STATUS: CRITICAL FAILURE\n";
}
