<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed admin user
        $this->call(AdminUserSeeder::class);
        
        // Create sample doctors
        $doctors = [
            [
                'name' => 'Dr. Sarah Johnson',
                'specialization' => 'Veterinary Surgeon',
                'phone' => '+62 812-3456-7890',
                'email' => 'sarah@petheal.com',
                'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'start_time' => '09:00',
                'end_time' => '17:00',
                'is_active' => true,
            ],
            [
                'name' => 'Dr. Michael Chen',
                'specialization' => 'Pet Dermatologist',
                'phone' => '+62 813-4567-8901',
                'email' => 'michael@petheal.com',
                'available_days' => ['monday', 'wednesday', 'friday', 'saturday'],
                'start_time' => '10:00',
                'end_time' => '18:00',
                'is_active' => true,
            ],
            [
                'name' => 'Dr. Emily Williams',
                'specialization' => 'Veterinary Cardiologist',
                'phone' => '+62 814-5678-9012',
                'email' => 'emily@petheal.com',
                'available_days' => ['tuesday', 'thursday', 'saturday'],
                'start_time' => '08:00',
                'end_time' => '16:00',
                'is_active' => true,
            ],
        ];

        foreach ($doctors as $doctor) {
            Doctor::create($doctor);
        }

        $this->command->info('Created ' . count($doctors) . ' doctors.');

        // Seed services
        $this->call(ServiceSeeder::class);
    }
}
