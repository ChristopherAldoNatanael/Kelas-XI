<?php

/**
 * Check for null NIP in teachers
 */
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Teacher;

$teachers = Teacher::whereNull('nip')->orWhere('nip', '')->get();

echo "Teachers with null/empty NIP:\n";
foreach ($teachers as $t) {
    echo "ID: " . $t->id . ", Nama: " . $t->nama . ", NIP: " . ($t->nip ?? 'NULL') . "\n";
}

if (count($teachers) == 0) {
    echo "None found.\n";
}

// Check all teachers
echo "\nAll teachers NIP status:\n";
$all = Teacher::all();
foreach ($all as $t) {
    $nipStatus = $t->nip ? 'OK' : 'NULL/EMPTY';
    echo "ID: " . $t->id . " | " . $t->nama . " | NIP: " . $nipStatus . "\n";
}
