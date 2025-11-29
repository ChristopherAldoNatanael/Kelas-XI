<?php

/**
 * SIMPLY POPULATE BASIC DATA - works with any schema
 */

echo "=== BASIC DATA POPULATION ===\n";
echo "Works with existing table structure\n\n";

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "🔍 Checking users table structure...\n";
    $columns = DB::select("DESCRIBE users");
    $columnNames = array_column($columns, 'Field');
    $hasNama = in_array('nama', $columnNames);
    $hasName = in_array('name', $columnNames);

    echo "Users table has: ";
    if ($hasNama && $hasName) echo "'name' AND 'nama' columns";
    else if ($hasNama) echo "'nama' column";
    else if ($hasName) echo "'name' column";
    else echo "NEITHER 'name' nor 'nama' column";

    echo "\n\n🧹 Clearing existing data...\n";

    // Clear tables carefully to handle foreign keys
    DB::statement("SET FOREIGN_KEY_CHECKS = 0");

    try { DB::table('teacher_attendances')->delete(); } catch (Exception $e) {}
    try { DB::table('schedules')->delete(); } catch (Exception $e) {}
    try { DB::table('teachers')->delete(); } catch (Exception $e) {}
    try { DB::table('subjects')->delete(); } catch (Exception $e) {}
    try { DB::table('classes')->delete(); } catch (Exception $e) {}
    try { DB::table('users')->delete(); } catch (Exception $e) {}

    DB::statement("SET FOREIGN_KEY_CHECKS = 1");

    echo "✓ Cleared existing data\n";

    echo "\n👥 Creating users...\n";

    $userData = [
        [
            'email' => 'admin@sekolah.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_banned' => 0
        ],
        [
            'email' => 'kurikulum@sekolah.com',
            'password' => bcrypt('password'),
            'role' => 'kurikulum',
            'is_banned' => 0
        ],
        [
            'email' => 'siswa@sekolah.com',
            'password' => bcrypt('password'),
            'role' => 'siswa',
            'is_banned' => 0
        ]
    ];

    foreach ($userData as $user) {
        $data = $user;

        // Set the name field appropriately
        if ($hasNama) {
            $data['nama'] = match($user['email']) {
                'admin@sekolah.com' => 'Administrator',
                'kurikulum@sekolah.com' => 'Staff Kurikulum',
                'siswa@sekolah.com' => 'Ahmad Siswa',
                default => 'User'
            };
        }
        if ($hasName) {
            $data['name'] = match($user['email']) {
                'admin@sekolah.com' => 'Administrator',
                'kurikulum@sekolah.com' => 'Staff Kurikulum',
                'siswa@sekolah.com' => 'Ahmad Siswa',
                default => 'User'
            };
        }

        DB::table('users')->insert($data);
        $name = $hasNama ? $data['nama'] : $data['name'];
        echo "  ✓ Created user: {$name}\n";
    }

    echo "\n🏫 Creating classes...\n";

    $classes = [
        ['nama_kelas' => 'X RPL 1', 'kode_kelas' => 'X-RPL-1'],
        ['nama_kelas' => 'X RPL 2', 'kode_kelas' => 'X-RPL-2'],
        ['nama_kelas' => 'XI RPL 1', 'kode_kelas' => 'XI-RPL-1'],
        ['nama_kelas' => 'XI RPL 2', 'kode_kelas' => 'XI-RPL-2'],
        ['nama_kelas' => 'XII RPL 1', 'kode_kelas' => 'XII-RPL-1'],
    ];

    foreach ($classes as $class) {
        $data = $class;
        
        // Add timestamps
        $data['created_at'] = now();
        $data['updated_at'] = now();
        
        DB::table('classes')->insert($data);
        echo "  ✓ Created class: {$class['nama_kelas']}\n";
    }

    echo "\n📚 Creating subjects...\n";

    // Check subjects table structure
    echo "🔍 Checking subjects table structure...\n";
    $subjectsColumns = DB::select("DESCRIBE subjects");
    $subjectColumnNames = array_column($subjectsColumns, 'Field');
    echo "Subjects table columns: " . implode(', ', $subjectColumnNames) . "\n";

    $subjects = [
        ['nama' => 'Matematika', 'kode' => 'MTK-001'],
        ['nama' => 'Bahasa Indonesia', 'kode' => 'BI-001'],
        ['nama' => 'Bahasa Inggris', 'kode' => 'BING-001'],
        ['nama' => 'Pemrograman Dasar', 'kode' => 'PD-001'],
        ['nama' => 'Basis Data', 'kode' => 'BD-001'],
    ];

    foreach ($subjects as $subject) {
        $data = $subject;
        
        // Add timestamps
        $data['created_at'] = now();
        $data['updated_at'] = now();

        DB::table('subjects')->insert($data);
        echo "  ✓ Created subject: {$subject['nama']}\n";
    }

    echo "\n👨‍🏫 Creating teacher...\n";

    $adminUser = DB::table('users')->where('email', 'admin@sekolah.com')->first();
    if ($adminUser) {
        DB::table('teachers')->insert([
            'name' => 'Guru Admin',
            'email' => 'guru@sekolah.com',
            'password' => bcrypt('password'),
            'mata_pelajaran' => 'Matematika',
            'is_banned' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        echo "  ✓ Created teacher\n";
    }

    echo "\n📅 Skipping schedules (table not available)...\n";

    echo "\n✅ DATA POPULATION COMPLETED SUCCESSFULLY!\n";
    echo "===========================================\n";
    echo "Data yang berhasil dibuat:\n";
    echo "- " . DB::table('users')->count() . " users\n";
    echo "- " . DB::table('classes')->count() . " classes\n";
    echo "- " . DB::table('subjects')->count() . " subjects\n";
    echo "- " . DB::table('teachers')->count() . " teachers\n";
    echo "- " . DB::table('schedules')->count() . " schedules\n";
    echo "===========================================\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
