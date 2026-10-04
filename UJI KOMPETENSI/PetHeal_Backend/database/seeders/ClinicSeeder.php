<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClinicSeeder extends Seeder
{
    public function run(): void
    {
        $clinics = $this->getClinicsData();

        foreach ($clinics as $data) {
            $clinic = Clinic::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name'          => $data['name'],
                    'address'       => $data['address'],
                    'phone'         => $data['phone'],
                    'email'         => $data['email'],
                    'primary_color' => $data['primary_color'],
                    'description'   => $data['description'],
                    'is_active'     => true,
                ]
            );

            $this->seedDoctors($clinic, $data['doctors']);
            $this->seedServices($clinic, $data['services']);
            $this->seedUsers($clinic, $data['admin_email'], $data['user_email']);
        }

        $this->command->info('ClinicSeeder completed: 3 clinics with doctors, services, and users.');
    }

    private function getClinicsData(): array
    {
        return [
            // ─── Clinic 1: Klinik PetHeal Pusat (Jakarta) ───
            [
                'name'          => 'Klinik PetHeal Pusat',
                'slug'          => 'petheal-pusat',
                'address'       => 'Jl. Sudirman No. 123, Jakarta Pusat, DKI Jakarta 10220',
                'phone'         => '021-5551234',
                'email'         => 'pusat@petheal.com',
                'primary_color' => '#18C964',
                'description'   => 'Klinik hewan pusat PetHeal — melayani konsultasi, vaksinasi, grooming, dan operasi.',
                'admin_email'   => 'admin.pusat@petheal.com',
                'user_email'    => 'user.pusat@petheal.com',
                'doctors'       => [
                    [
                        'name'            => 'Dr. Sarah Johnson',
                        'specialization'   => 'Dokter Hewan Umum',
                        'phone'           => '0812-1111-0001',
                        'email'           => 'sarah@petheal.com',
                        'available_days'  => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                        'start_time'      => '09:00',
                        'end_time'        => '17:00',
                    ],
                    [
                        'name'            => 'Dr. Michael Chen',
                        'specialization'   => 'Dokter Kulit Hewan',
                        'phone'           => '0812-1111-0002',
                        'email'           => 'michael@petheal.com',
                        'available_days'  => ['monday', 'wednesday', 'friday', 'saturday'],
                        'start_time'      => '10:00',
                        'end_time'        => '18:00',
                    ],
                ],
                'services' => [
                    ['name' => 'Medical Checkup',   'description' => 'Pemeriksaan kesehatan hewan menyeluruh.',              'price' => 150000,  'category' => 'Medical Checkup'],
                    ['name' => 'Vaksinasi',          'description' => 'Vaksinasi lengkap untuk anjing dan kucing.',           'price' => 100000,  'category' => 'Vaccination'],
                    ['name' => 'Grooming Lengkap',   'description' => 'Mandi, potong kuku, bersihkan telinga, dan bulu.',    'price' => 120000,  'category' => 'Grooming'],
                ],
            ],

            // ─── Clinic 2: Happy Paws (Bandung) ───
            [
                'name'          => 'Happy Paws',
                'slug'          => 'happy-paws',
                'address'       => 'Jl. Dago No. 45, Coblong, Bandung, Jawa Barat 40135',
                'phone'         => '022-7778899',
                'email'         => 'info@happypaws.id',
                'primary_color' => '#F97316',
                'description'   => 'Klinik hewan modern di Bandung — spesialis hewan eksotis dan hewan kecil.',
                'admin_email'   => 'admin@happypaws.id',
                'user_email'    => 'user@happypaws.id',
                'doctors'       => [
                    [
                        'name'            => 'Dr. Rina Amelia',
                        'specialization'   => 'Dokter Hewan Eksotis',
                        'phone'           => '0813-2222-0001',
                        'email'           => 'rina@happypaws.id',
                        'available_days'  => ['tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
                        'start_time'      => '08:00',
                        'end_time'        => '16:00',
                    ],
                    [
                        'name'            => 'Dr. Budi Santoso',
                        'specialization'   => 'Bedah Hewan Kecil',
                        'phone'           => '0813-2222-0002',
                        'email'           => 'budi@happypaws.id',
                        'available_days'  => ['monday', 'wednesday', 'friday'],
                        'start_time'      => '09:00',
                        'end_time'        => '17:00',
                    ],
                ],
                'services' => [
                    ['name' => 'Konsultasi Eksotis',    'description' => 'Konsultasi khusus hewan eksotis: reptil, burung, kelinci.', 'price' => 200000,  'category' => 'Medical Checkup'],
                    ['name' => 'Operasi Kecil',          'description' => 'Prosedur bedah minor dengan anestesi aman.',               'price' => 500000,  'category' => 'Surgery'],
                    ['name' => 'Spa & Grooming Premium', 'description' => 'Perawatan premium: aromatherapy, masker bulu, nail art.',  'price' => 180000,  'category' => 'Grooming'],
                ],
            ],

            // ─── Clinic 3: MeowCare (Surabaya) ───
            [
                'name'          => 'MeowCare',
                'slug'          => 'meowcare',
                'address'       => 'Jl. Pemuda No. 88, Gubeng, Surabaya, Jawa Timur 60271',
                'phone'         => '031-5556677',
                'email'         => 'halo@meowcare.id',
                'primary_color' => '#8B5CF6',
                'description'   => 'Klinik kucing terpercaya di Surabaya — fokus kesehatan kucing dan konsultasi gizi.',
                'admin_email'   => 'admin@meowcare.id',
                'user_email'    => 'user@meowcare.id',
                'doctors'       => [
                    [
                        'name'            => 'Dr. Dewi Lestari',
                        'specialization'   => 'Dokter Kucing Spesialis',
                        'phone'           => '0815-3333-0001',
                        'email'           => 'dewi@meowcare.id',
                        'available_days'  => ['monday', 'tuesday', 'thursday', 'friday', 'saturday'],
                        'start_time'      => '08:30',
                        'end_time'        => '16:30',
                    ],
                    [
                        'name'            => 'Dr. Farhan Maulana',
                        'specialization'   => 'Nutrisi & Gizi Hewan',
                        'phone'           => '0815-3333-0002',
                        'email'           => 'farhan@meowcare.id',
                        'available_days'  => ['wednesday', 'thursday', 'friday', 'saturday'],
                        'start_time'      => '10:00',
                        'end_time'        => '18:00',
                    ],
                ],
                'services' => [
                    ['name' => 'Checkup Kucing',       'description' => 'Pemeriksaan khusus kucing termasuk cek FIV/FeLV.',   'price' => 130000,  'category' => 'Medical Checkup'],
                    ['name' => 'Konsultasi Gizi',       'description' => 'Diet dan rencana makan khusus kucing Anda.',         'price' => 90000,   'category' => 'Others'],
                    ['name' => 'Rawat Inap Kucing',     'description' => 'Perawatan inap dengan kandang nyaman dan AC.',       'price' => 250000,  'category' => 'Others'],
                ],
            ],
        ];
    }

    private function seedDoctors(Clinic $clinic, array $doctors): void
    {
        foreach ($doctors as $data) {
            Doctor::updateOrCreate(
                ['email' => $data['email'], 'clinic_id' => $clinic->id],
                [
                    'name'           => $data['name'],
                    'specialization' => $data['specialization'],
                    'phone'          => $data['phone'],
                    'available_days' => $data['available_days'],
                    'start_time'     => $data['start_time'],
                    'end_time'       => $data['end_time'],
                    'is_active'      => true,
                ]
            );
        }
    }

    private function seedServices(Clinic $clinic, array $services): void
    {
        foreach ($services as $data) {
            Service::updateOrCreate(
                ['name' => $data['name'], 'clinic_id' => $clinic->id],
                [
                    'description' => $data['description'],
                    'price'       => $data['price'],
                    'category'    => $data['category'],
                    'is_active'   => true,
                ]
            );
        }
    }

    private function seedUsers(Clinic $clinic, string $adminEmail, string $userEmail): void
    {
        // Clinic admin
        User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name'      => 'Admin ' . $clinic->name,
                'password'  => Hash::make('admin123'),
                'role'      => 'clinic_admin',
                'clinic_id' => $clinic->id,
                'phone'     => $clinic->phone,
            ]
        );

        // Demo user (patient)
        User::updateOrCreate(
            ['email' => $userEmail],
            [
                'name'      => 'User ' . $clinic->name,
                'password'  => Hash::make('user123'),
                'role'      => 'user',
                'clinic_id' => $clinic->id,
                'phone'     => '0812-0000-' . str_pad($clinic->id, 4, '0', STR_PAD_LEFT),
            ]
        );
    }
}