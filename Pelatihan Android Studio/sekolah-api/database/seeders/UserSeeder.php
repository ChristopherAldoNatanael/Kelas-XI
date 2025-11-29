<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::firstOrCreate([
            'email' => 'admin@example.com'
        ], [
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_banned' => false
        ]);

        // Create kurikulum user
        User::firstOrCreate([
            'email' => 'kurikulum@example.com'
        ], [
            'name' => 'Waka Kurikulum',
            'email' => 'kurikulum@example.com',
            'password' => Hash::make('password'),
            'role' => 'kurikulum',
            'is_banned' => false
        ]);

        // Create kepala sekolah user
        User::firstOrCreate([
            'email' => 'kepsek@example.com'
        ], [
            'name' => 'Kepala Sekolah',
            'email' => 'kepsek@example.com',
            'password' => Hash::make('password'),
            'role' => 'kepala_sekolah',
            'is_banned' => false
        ]);

        // Create siswa users for different classes
        $classes = \App\Models\ClassModel::all();

        if ($classes->isNotEmpty()) {
            // Create one siswa per class
            foreach ($classes as $index => $class) {
                $email = 'siswa' . ($index + 1) . '@example.com';
                User::firstOrCreate([
                    'email' => $email
                ], [
                    'name' => 'Siswa ' . $class->nama_kelas,
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'role' => 'siswa',
                    'class_id' => $class->id,
                    'is_banned' => false
                ]);
            }
        } else {
            // Fallback if no classes exist
            User::firstOrCreate([
                'email' => 'siswa@example.com'
            ], [
                'name' => 'Siswa Test',
                'email' => 'siswa@example.com',
                'password' => Hash::make('password'),
                'role' => 'siswa',
                'is_banned' => false
            ]);
        }

        $this->command->info('Users seeded successfully!');
        $this->command->info('Login credentials:');
        $this->command->info('Admin: admin@example.com / password');
        $this->command->info('Kurikulum: kurikulum@example.com / password');
        $this->command->info('Kepala Sekolah: kepsek@example.com / password');
        $this->command->info('Siswa: siswa@example.com / password');
    }
}
