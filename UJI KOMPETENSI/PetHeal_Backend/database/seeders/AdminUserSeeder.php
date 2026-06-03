<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Administrator',
            'email' => 'admin@petheal.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'firebase_uid' => null, // Admin doesn't use Firebase auth
            'phone' => '081234567890',
        ]);

        $this->command->info('Admin user created: admin@petheal.com / admin123');
    }
}
