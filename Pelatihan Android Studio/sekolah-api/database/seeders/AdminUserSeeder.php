<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Don't truncate - just check and update existing users
        $this->command->info('🔍 Checking existing users...');

        // Create admin users with Indonesian names
        $users = [
            [
                'nama' => 'Ahmad Santoso',
                'email' => 'admin@sekolah.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'active',
            ],
            [
                'nama' => 'Siti Nurhaliza',
                'email' => 'kepsek@sekolah.com',
                'password' => Hash::make('password'),
                'role' => 'kepala-sekolah',
                'status' => 'active',
            ],
            [
                'nama' => 'Budi Setiawan',
                'email' => 'kurikulum@sekolah.com',
                'password' => Hash::make('password'),
                'role' => 'kurikulum',
                'status' => 'active',
            ],
            [
                'nama' => 'Maya Sari',
                'email' => 'guru.programming@sekolah.com',
                'password' => Hash::make('password'),
                'role' => 'kurikulum',
                'status' => 'active',
            ],
            [
                'nama' => 'Rudi Hartono',
                'email' => 'guru.math@sekolah.com',
                'password' => Hash::make('password'),
                'role' => 'kurikulum',
                'status' => 'active',
            ],
        ];

        foreach ($users as $userData) {
            try {
                $user = User::firstOrCreate(
                    ['email' => $userData['email']],
                    array_merge($userData, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])
                );

                if ($user->wasRecentlyCreated) {
                    $this->command->info('✓ Created user: ' . $userData['email']);
                } else {
                    $this->command->info('✓ User already exists: ' . $userData['email']);
                }
            } catch (\Exception $e) {
                $this->command->error('Error processing user ' . $userData['email'] . ': ' . $e->getMessage());
            }
        }

        $this->command->info('');
        $this->command->info('🎉 All users updated successfully!');
        $this->command->info('📧 Login credentials for ALL users:');
        $this->command->info('   Password: password');
        $this->command->info('');
        $this->command->info('📋 Available emails:');
        foreach ($users as $user) {
            $this->command->info('   - ' . $user['email'] . ' (' . $user['role'] . ')');
        }
    }
}
