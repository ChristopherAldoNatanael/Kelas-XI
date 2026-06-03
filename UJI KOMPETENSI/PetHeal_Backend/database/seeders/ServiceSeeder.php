<?php
namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'name' => 'Medical Checkup',
                'description' => 'Complete physical examination and health assessment for your pet.',
                'price' => 150000,
                'category' => 'Medical Checkup',
            ],
            [
                'name' => 'Vaccination',
                'description' => 'Essential vaccinations to protect your pet from common diseases.',
                'price' => 100000,
                'category' => 'Vaccination',
            ],
            [
                'name' => 'Grooming',
                'description' => 'Full grooming service including bath, haircut, nail trimming, and ear cleaning.',
                'price' => 120000,
                'category' => 'Grooming',
            ],
            [
                'name' => 'Surgery',
                'description' => 'Surgical procedures performed by our experienced veterinary surgeons.',
                'price' => 500000,
                'category' => 'Surgery',
            ],
            [
                'name' => 'Dental Cleaning',
                'description' => 'Professional dental cleaning and oral health check for your pet.',
                'price' => 200000,
                'category' => 'Dental',
            ],
            [
                'name' => 'Lab Test',
                'description' => 'Comprehensive laboratory tests including blood work and urinalysis.',
                'price' => 175000,
                'category' => 'Lab Test',
            ],
            [
                'name' => 'X-Ray',
                'description' => 'Digital X-ray imaging for diagnostic purposes.',
                'price' => 250000,
                'category' => 'Medical Checkup',
            ],
            [
                'name' => 'Hospitalization',
                'description' => 'In-patient care with 24/7 monitoring and treatment.',
                'price' => 300000,
                'category' => 'Others',
            ],
        ];

        foreach ($services as $service) {
            Service::create($service);
        }

        $this->command->info('Created ' . count($services) . ' services.');
    }
}
