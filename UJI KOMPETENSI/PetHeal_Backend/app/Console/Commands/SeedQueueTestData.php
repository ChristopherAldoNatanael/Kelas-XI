<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Data uji antrian untuk aplikasi Android dan papan admin.
 *
 * Idempoten: menjalankan ulang akan mengatur tiga booking QA ke kondisi awal.
 */
class SeedQueueTestData extends Command
{
    protected $signature = 'qa:seed-queue-test
        {--clinic=1 : ID klinik}
        {--doctor=8 : ID dokter}
        {--owner-email=qa.owner@petheal.test}
        {--owner-password=PetHealQa123!}
        {--admin-email=qa.admin@petheal.test}
        {--admin-password=PetHealAdmin123!}';

    protected $description = 'Buat data uji booking + antrian untuk QA Android';

    public function handle(): int
    {
        $clinic = Clinic::find((int) $this->option('clinic'));
        $doctor = Doctor::find((int) $this->option('doctor'));

        if (! $clinic || ! $doctor) {
            $this->error('Klinik atau dokter tidak ditemukan.');

            return self::FAILURE;
        }

        if ((int) $doctor->clinic_id !== (int) $clinic->id) {
            $this->error('Dokter bukan milik klinik yang dipilih.');

            return self::FAILURE;
        }

        $owner = User::updateOrCreate(
            ['email' => (string) $this->option('owner-email')],
            [
                'name' => 'QA Pemilik Antrian',
                'password' => Hash::make((string) $this->option('owner-password')),
                'role' => 'user',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => (string) $this->option('admin-email')],
            [
                'name' => 'QA Admin Antrian',
                'password' => Hash::make((string) $this->option('admin-password')),
                'role' => 'clinic_admin',
                'clinic_id' => $clinic->id,
                'email_verified_at' => now(),
            ]
        );

        $pet = Pet::firstOrCreate(
            ['user_id' => $owner->id, 'name' => 'Mochi QA'],
            [
                'species' => 'Cat',
                'breed' => 'Domestic Shorthair',
                'gender' => 'female',
                'date_of_birth' => now()->subYears(2)->toDateString(),
                'weight' => 4.2,
            ]
        );

        $today = today()->toDateString();
        $amount = 150000.0;

        $definitions = [
            'A' => ['time' => '09:00:00', 'marker' => '[QA-QUEUE] A — siap check-in'],
            'B' => ['time' => '10:00:00', 'marker' => '[QA-QUEUE] B — antrian dipanggil'],
            'C' => ['time' => '10:30:00', 'marker' => '[QA-QUEUE] C — antrian cadangan'],
        ];

        $bookings = [];

        DB::transaction(function () use ($owner, $pet, $doctor, $clinic, $today, $amount, $definitions, &$bookings) {
            foreach ($definitions as $key => $definition) {
                $booking = Booking::where('user_id', $owner->id)
                    ->where('notes', $definition['marker'])
                    ->first();

                if (! $booking) {
                    $booking = new Booking([
                        'user_id' => $owner->id,
                        'pet_id' => $pet->id,
                        'doctor_id' => $doctor->id,
                        'clinic_id' => $clinic->id,
                    ]);
                }

                $booking->forceFill([
                    'pet_id' => $pet->id,
                    'doctor_id' => $doctor->id,
                    'clinic_id' => $clinic->id,
                    'booking_date' => $today,
                    'booking_time' => $definition['time'],
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                    'completed_at' => null,
                    'cancellation_reason' => null,
                    'notes' => $definition['marker'],
                    'service_type' => 'medical_checkup',
                    'payment_method' => 'cash',
                    'payment_type' => 'full',
                    'total_amount' => $amount,
                    'paid_amount' => $amount,
                    'remaining_amount' => 0,
                    'payment_status' => 'paid',
                    'payment_date' => now(),
                    'queue_number' => null,
                    'queue_date' => null,
                    'queue_status' => null,
                    'checked_in_at' => null,
                    'called_at' => null,
                    'queue_finished_at' => null,
                    'queue_note' => null,
                ])->save();

                $bookings[$key] = $booking->fresh();
            }

            // B dan C sudah check-in agar admin bisa menguji "Panggil berikutnya".
            foreach (['B', 'C'] as $key) {
                $booking = $bookings[$key];
                $nextNumber = (int) Booking::where('clinic_id', $clinic->id)
                    ->whereDate('queue_date', $today)
                    ->max('queue_number') + 1;

                $booking->forceFill([
                    'queue_date' => $today,
                    'queue_number' => $nextNumber,
                    'queue_status' => QueueService::STATUS_WAITING,
                    'checked_in_at' => now(),
                ])->save();

                $bookings[$key] = $booking->fresh();
            }
        });

        $bookings['A'] = $bookings['A']->fresh();
        $bookings['B'] = $bookings['B']->fresh();
        $bookings['C'] = $bookings['C']->fresh();

        $this->info('Data uji antrian siap.');
        $this->table(
            ['Item', 'Nilai'],
            [
                ['Klinik', $clinic->name . ' (#' . $clinic->id . ')'],
                ['Dokter', $doctor->name . ' (#' . $doctor->id . ')'],
                ['Login pemilik', $this->option('owner-email') . ' / ' . $this->option('owner-password')],
                ['Login admin', $this->option('admin-email') . ' / ' . $this->option('admin-password')],
                ['Booking A', '#' . $bookings['A']->id . ' — belum check-in (uji check-in Android)'],
                ['Booking B', '#' . $bookings['B']->id . ' — ' . QueueService::label($bookings['B']->queue_number) . ' menunggu (uji panggil)'],
                ['Booking C', '#' . $bookings['C']->id . ' — ' . QueueService::label($bookings['C']->queue_number) . ' menunggu (cadangan)'],
                ['Papan admin', '/admin/queue'],
            ]
        );

        $this->warn('Jalankan ulang command ini untuk mengembalikan ketiga booking ke kondisi awal.');

        return self::SUCCESS;
    }
}
