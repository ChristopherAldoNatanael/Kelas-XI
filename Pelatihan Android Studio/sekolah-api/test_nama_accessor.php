<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "Testing nama accessor and mutator...\n";
echo "=====================================\n\n";

// Test getting nama
$user = User::find(1);
echo "User ID: {$user->id}\n";
echo "Database name field: {$user->name}\n";
echo "Accessor nama: {$user->nama}\n\n";

// Test setting nama
echo "Setting nama to 'Updated Test Name'...\n";
$user->nama = 'Updated Test Name';
$user->save();

echo "After save:\n";
echo "Database name field: {$user->name}\n";
echo "Accessor nama: {$user->nama}\n\n";

// Test mass assignment
echo "Testing mass assignment...\n";
$user->update(['nama' => 'Mass Assigned Name']);

echo "After mass assignment:\n";
echo "Database name field: {$user->name}\n";
echo "Accessor nama: {$user->nama}\n\n";

echo "Test completed!\n";
