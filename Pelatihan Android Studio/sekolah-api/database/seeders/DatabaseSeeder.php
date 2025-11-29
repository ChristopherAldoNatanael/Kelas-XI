<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Create admin user with better error handling
        try {
            $adminUser = User::firstOrCreate(
                ['email' => 'admin@gmail.com'],
                [
                    'name' => 'Administrator',
                    'password' => bcrypt('123456'),
                    'role' => 'admin',
                    'is_banned' => false,
                ]
            );

            if ($adminUser->wasRecentlyCreated) {
                $this->command->info('✓ Admin user created successfully');
            } else {
                $this->command->info('✓ Admin user already exists');
            }
        } catch (\Exception $e) {
            $this->command->error('Error creating admin user: ' . $e->getMessage());
        }

        try {
            $testUser = User::firstOrCreate(
                ['email' => 'test@example.com'],
                [
                    'name' => 'Test User',
                    'password' => bcrypt('123456'),
                    'role' => 'siswa',
                    'is_banned' => false,
                ]
            );

            if ($testUser->wasRecentlyCreated) {
                $this->command->info('✓ Test user created successfully');
            } else {
                $this->command->info('✓ Test user already exists');
            }
        } catch (\Exception $e) {
            $this->command->error('Error creating test user: ' . $e->getMessage());
        }

        // Jalankan semua seeder
        $this->call([
            TeacherSeeder::class,
            SubjectSeeder::class,
            ClassSeeder::class,
            SubjectTeacherSeeder::class,
            ClassroomSeeder::class,
        ]);
    }
}
