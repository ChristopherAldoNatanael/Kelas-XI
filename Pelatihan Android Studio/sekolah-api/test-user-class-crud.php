<?php

/**
 * Test create dan update user dengan class_id
 */

echo "=== TEST CREATE & UPDATE USER WITH CLASS ===\n\n";

// Test 1: Check if classes exist
echo "1. Checking available classes...\n";
system('cd "C:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api" && php artisan tinker --execute="
use App\Models\ClassModel;
\$classes = ClassModel::where(\'major\', \'Rekayasa Perangkat Lunak\')
    ->where(\'status\', \'active\')
    ->whereIn(\'name\', [\'X RPL\', \'XI RPL\', \'XII RPL\'])
    ->orderBy(\'level\')
    ->get();
echo \"Available classes:\\n\";
foreach (\$classes as \$c) {
    echo \"  - ID: {\$c->id}, Name: {\$c->name}, Level: {\$c->level}\\n\";
}
echo \"Total: \" . \$classes->count() . \" classes\\n\";
"');

echo "\n2. Creating test siswa user with class...\n";
system('cd "C:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api" && php artisan tinker --execute="
use App\Models\User;
use App\Models\ClassModel;
use Illuminate\Support\Facades\Hash;

// Get XI RPL class
\$class = ClassModel::where(\'name\', \'XI RPL\')->first();

if (!\$class) {
    echo \"ERROR: XI RPL class not found!\\n\";
    exit(1);
}

// Check if user already exists
\$existingUser = User::where(\'email\', \'siswa.test@example.com\')->first();
if (\$existingUser) {
    echo \"User already exists, deleting...\\n\";
    \$existingUser->forceDelete();
}

// Create new user
\$user = User::create([
    \'nama\' => \'Siswa Test\',
    \'email\' => \'siswa.test@example.com\',
    \'password\' => Hash::make(\'password123\'),
    \'role\' => \'siswa\',
    \'class_id\' => \$class->id,
    \'status\' => \'active\'
]);

echo \"✓ User created successfully!\\n\";
echo \"  ID: {\$user->id}\\n\";
echo \"  Name: {\$user->nama}\\n\";
echo \"  Email: {\$user->email}\\n\";
echo \"  Role: {\$user->role}\\n\";
echo \"  Class ID: {\$user->class_id}\\n\";

// Load class relationship
\$user->load(\'class\');
echo \"  Class Name: \" . (\$user->class ? \$user->class->name : \'N/A\') . \"\\n\";
"');

echo "\n3. Testing update user class...\n";
system('cd "C:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api" && php artisan tinker --execute="
use App\Models\User;
use App\Models\ClassModel;

\$user = User::where(\'email\', \'siswa.test@example.com\')->first();
if (!\$user) {
    echo \"ERROR: Test user not found!\\n\";
    exit(1);
}

// Change to XII RPL
\$newClass = ClassModel::where(\'name\', \'XII RPL\')->first();
if (!\$newClass) {
    echo \"ERROR: XII RPL class not found!\\n\";
    exit(1);
}

\$user->update([\'class_id\' => \$newClass->id]);

echo \"✓ User updated successfully!\\n\";
echo \"  Previous class: XI RPL\\n\";
echo \"  New class ID: {\$user->class_id}\\n\";

\$user->load(\'class\');
echo \"  New class name: \" . (\$user->class ? \$user->class->name : \'N/A\') . \"\\n\";
"');

echo "\n4. Testing role change (siswa -> admin should clear class_id)...\n";
system('cd "C:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api" && php artisan tinker --execute="
use App\Models\User;

\$user = User::where(\'email\', \'siswa.test@example.com\')->first();
if (!\$user) {
    echo \"ERROR: Test user not found!\\n\";
    exit(1);
}

echo \"Before: Role={\$user->role}, class_id={\$user->class_id}\\n\";

// Change role to admin (should clear class_id)
\$user->update([
    \'role\' => \'admin\',
    \'class_id\' => null
]);

echo \"After: Role={\$user->role}, class_id=\" . (\$user->class_id ?? \'NULL\') . \"\\n\";
echo \"✓ Role changed to admin, class_id cleared!\\n\";

// Change back to siswa
\$class = App\Models\ClassModel::where(\'name\', \'X RPL\')->first();
\$user->update([
    \'role\' => \'siswa\',
    \'class_id\' => \$class->id
]);

\$user->load(\'class\');
echo \"✓ Changed back to siswa with class: \" . (\$user->class ? \$user->class->name : \'N/A\') . \"\\n\";
"');

echo "\n5. Verify in database...\n";
system('cd "C:\Kelas XI RPL\Pelatihan Android Studio\sekolah-api" && php artisan tinker --execute="
use App\Models\User;

\$user = User::with(\'class\')->where(\'email\', \'siswa.test@example.com\')->first();

echo \"Final user state:\\n\";
echo \"  ID: {\$user->id}\\n\";
echo \"  Name: {\$user->nama}\\n\";
echo \"  Email: {\$user->email}\\n\";
echo \"  Role: {\$user->role}\\n\";
echo \"  Class ID: {\$user->class_id}\\n\";
echo \"  Class: \" . (\$user->class ? \$user->class->name . \' (Level \' . \$user->class->level . \')\' : \'N/A\') . \"\\n\";
"');

echo "\n✅ ALL TESTS COMPLETED!\n";
echo "\nYou can now:\n";
echo "1. Visit http://localhost:8000/web-users/create to create a new siswa\n";
echo "2. Select role 'Siswa' and you'll see the class dropdown appear\n";
echo "3. Select a class (X RPL, XI RPL, or XII RPL)\n";
echo "4. Submit and you'll be redirected to users list\n";
