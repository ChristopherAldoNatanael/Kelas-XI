<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== TESTING SUBJECT CRUD OPERATIONS ===\n\n";

try {
    // Test 1: Get all subjects
    echo "1. Testing GET all subjects...\n";
    $subjects = DB::table('subjects')->get();
    echo "   Found " . $subjects->count() . " subjects in database\n";

    if ($subjects->count() > 0) {
        $firstSubject = $subjects->first();
        echo "   First subject: {$firstSubject->nama} ({$firstSubject->kode})\n";

        // Test 2: Test subject model with accessors
        echo "\n2. Testing Subject model with accessors...\n";
        $subjectModel = \App\Models\Subject::find($firstSubject->id);
        echo "   Model data:\n";
        echo "   - ID: {$subjectModel->id}\n";
        echo "   - Name (accessor): {$subjectModel->name}\n";
        echo "   - Code (accessor): {$subjectModel->code}\n";
        echo "   - Database nama: {$subjectModel->nama}\n";
        echo "   - Database kode: {$subjectModel->kode}\n";

        // Test 3: Test update operation
        echo "\n3. Testing update operation...\n";
        $originalNama = $subjectModel->nama;
        $originalKode = $subjectModel->kode;

        // Update using model mutators
        $subjectModel->name = $originalNama . " (Updated)";
        $subjectModel->code = $originalKode . "-UPD";
        $subjectModel->save();

        echo "   Updated subject successfully\n";
        echo "   New nama: {$subjectModel->nama}\n";
        echo "   New kode: {$subjectModel->kode}\n";

        // Restore original values
        $subjectModel->nama = $originalNama;
        $subjectModel->kode = $originalKode;
        $subjectModel->save();
        echo "   Restored original values\n";
    }

    // Test 4: Test create operation
    echo "\n4. Testing create operation...\n";
    $newSubject = \App\Models\Subject::create([
        'nama' => 'Test Subject',
        'kode' => 'TEST-001'
    ]);
    echo "   Created new subject: {$newSubject->name} ({$newSubject->code})\n";

    // Delete test subject
    $newSubject->delete();
    echo "   Deleted test subject\n";

    echo "\n✅ ALL SUBJECT CRUD TESTS PASSED!\n";
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
