<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Services\DoctorScheduleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Jam layanan klinik harus mengikat jadwal dokter.
 *
 * Sebelumnya slot hanya mengikuti available_days/start_time dokter, sehingga
 * pasien bisa memesan jam ketika klinik belum buka atau sudah tutup.
 */
class ClinicHoursSchedulingTest extends TestCase
{
    use DatabaseTransactions;

    private function clinic(array $attributes = []): Clinic
    {
        $suffix = Str::lower(Str::random(6));

        return Clinic::create(array_merge([
            'name' => 'Clinic Hours ' . $suffix,
            'slug' => 'clinic-hours-' . $suffix,
            'address' => 'Jl. Jam Klinik',
            'is_active' => true,
            'opening_time' => '10:00',
            'closing_time' => '15:00',
            'operating_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ], $attributes));
    }

    private function doctor(Clinic $clinic, array $attributes = []): Doctor
    {
        return Doctor::create(array_merge([
            'name' => 'Dr Jam ' . Str::random(4),
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $clinic->id,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ], $attributes));
    }

    public function test_slots_are_intersection_of_doctor_and_clinic_hours(): void
    {
        $doctor = $this->doctor($this->clinic());
        $date = Carbon::parse('next monday')->toDateString();

        $schedule = app(DoctorScheduleService::class)->effectiveSchedule($doctor, $date);
        $slots = app(DoctorScheduleService::class)->slotsFor($doctor, $date, $schedule);

        $this->assertTrue($schedule['is_working_day']);
        $this->assertSame('10:00', $schedule['start_time']);
        $this->assertSame('15:00', $schedule['end_time']);
        $this->assertSame('10:00', $slots[0]['time']);
        $this->assertSame('14:30', end($slots)['time']);
        $this->assertCount(10, $slots);
    }

    public function test_closed_clinic_has_no_slots_even_when_doctor_works(): void
    {
        $doctor = $this->doctor($this->clinic([
            'operating_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ]));

        // Cari Sabtu berikutnya: dokter praktik, klinik tutup.
        $date = Carbon::parse('next saturday')->toDateString();
        $schedule = app(DoctorScheduleService::class)->effectiveSchedule($doctor, $date);

        $this->assertFalse($schedule['is_working_day']);
        $this->assertTrue($schedule['clinic_closed']);
        $this->assertStringContainsString('Klinik tutup', (string) $schedule['reason']);
        $this->assertSame([], app(DoctorScheduleService::class)->slotsFor($doctor, $date, $schedule));
    }

    public function test_clinic_without_hours_keeps_old_doctor_only_behavior(): void
    {
        $doctor = $this->doctor($this->clinic([
            'opening_time' => null,
            'closing_time' => null,
            'operating_days' => null,
        ]));
        $date = Carbon::parse('next monday')->toDateString();

        $schedule = app(DoctorScheduleService::class)->effectiveSchedule($doctor, $date);

        $this->assertTrue($schedule['is_working_day']);
        $this->assertNull($schedule['clinic_is_open']);
        $this->assertSame('08:00', $schedule['start_time']);
        $this->assertSame('17:00', $schedule['end_time']);
    }

    public function test_slot_outside_clinic_hours_is_rejected(): void
    {
        $doctor = $this->doctor($this->clinic());
        $date = Carbon::parse('next monday')->toDateString();

        $issue = app(DoctorScheduleService::class)->validateSlot($doctor, $date, '09:00');

        $this->assertNotNull($issue);
        $this->assertStringContainsString('10:00', $issue);
    }

    public function test_closed_clinic_message_is_used_for_validation(): void
    {
        $doctor = $this->doctor($this->clinic([
            'operating_days' => ['monday'],
        ]));
        $date = Carbon::parse('next tuesday')->toDateString();

        $issue = app(DoctorScheduleService::class)->validateSlot($doctor, $date, '10:00');

        $this->assertNotNull($issue);
        $this->assertStringContainsString('Klinik tutup', (string) $issue);
    }
}
