<?php
// Test HTTP request ke endpoint jadwal-siswa
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Ambil token siswa
$siswa = DB::table('users')->where('class_id', 1)->first();
$tokenRecord = DB::table('personal_access_tokens')
    ->where('tokenable_id', $siswa->id)
    ->where('tokenable_type', 'App\\Models\\User')
    ->first();

if (!$tokenRecord) {
    echo "Token tidak ditemukan!\n";
    exit;
}

// Token format: id|hash
// Kita perlu token asli, tapi kita bisa test dengan endpoint internal
echo "Testing dengan user: {$siswa->name}\n";
echo "Token ID: {$tokenRecord->id}\n\n";

// Simulate request langsung ke controller
$request = new \Illuminate\Http\Request();

// Set bearer token - kita pakai plain token yang sama
$plainToken = null;

// Cari token yang valid
$tokens = DB::table('personal_access_tokens')
    ->where('tokenable_id', $siswa->id)
    ->get();

echo "Tokens for user:\n";
foreach ($tokens as $t) {
    echo "  ID: {$t->id}, Name: {$t->name}, Token (hash): " . substr($t->token, 0, 20) . "...\n";
}

echo "\n=== TESTING DIRECT QUERY ===\n";

// Test query langsung
$className = DB::table('classes')->where('id', $siswa->class_id)->value('nama_kelas');
echo "Kelas: $className\n";

$schedules = DB::table('schedules')
    ->where('kelas', $className)
    ->count();

echo "Jumlah jadwal: $schedules\n";

echo "\n=== TEST HTTP REQUEST ===\n";

// Test dengan curl
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/jadwal-siswa');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
// Note: kita tidak punya plain token, jadi test tanpa auth dulu
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "Error: $error\n";
} else {
    echo "Response length: " . strlen($response) . " bytes\n";
    echo "Response: " . substr($response, 0, 500) . "\n";
}
