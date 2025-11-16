<?php

/**
 * CHECK & CREATE SISWA USER FOR TESTING
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "=================================================\n";
echo "   CHECK SISWA USER FOR TESTING\n";
echo "=================================================\n\n";

// Cari user dengan role siswa
$siswa = User::where('role', 'siswa')->first();

if ($siswa) {
    echo "✅ Siswa user ditemukan!\n\n";
    echo "ID: {$siswa->id}\n";
    echo "Nama: {$siswa->nama}\n";
    echo "Email: {$siswa->email}\n";
    echo "Role: {$siswa->role}\n";
    echo "Class ID: " . ($siswa->class_id ?? 'NULL') . "\n\n";

    if (!$siswa->class_id) {
        echo "⚠️ WARNING: Siswa belum di-assign ke class!\n";
        echo "Silakan assign manual di database atau gunakan:\n";
        echo "php assign-siswa-to-class.php\n\n";
    }

    echo "CREDENTIALS UNTUK TEST:\n";
    echo "Email: {$siswa->email}\n";
    echo "Password: password (default)\n\n";
} else {
    echo "❌ Tidak ada user dengan role 'siswa'\n\n";
    echo "Membuat user siswa baru...\n";

    // Create siswa user
    $newSiswa = User::create([
        'nama' => 'Test Siswa',
        'email' => 'siswa@test.com',
        'password' => Hash::make('password'),
        'role' => 'siswa',
        'class_id' => null, // Akan di-assign nanti
        'status' => 'active'
    ]);

    echo "✅ User siswa berhasil dibuat!\n\n";
    echo "ID: {$newSiswa->id}\n";
    echo "Nama: {$newSiswa->nama}\n";
    echo "Email: {$newSiswa->email}\n";
    echo "Password: password\n\n";

    echo "⚠️ Jangan lupa assign ke class dengan:\n";
    echo "php assign-siswa-to-class.php\n";
}

echo "=================================================\n";
echo "Gunakan credentials di atas untuk test-kehadiran-simple.php\n";
echo "=================================================\n";
