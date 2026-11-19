<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorScheduleException;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Jam kerja dokter: kolom MySQL `time` menyimpan "09:00:00", sedangkan form
 * dan validasi memakai "H:i". Sebelumnya form edit merender nilai mentah
 * "09:00:00" sehingga menyimpan jam yang sama ditolak dengan
 * "The start time field must match the format H:i."
 *
 * Test ini mengunci dua lapisan perbaikan:
 * - form edit menampilkan "09:00" (bukan "09:00:00"),
 * - controller menerima nilai berdetik dari klien lama lalu menormalkannya.
 */
class DoctorWorkingHoursTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $admin;
    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'WorkHours ' . $suffix,
            'slug' => 'work-hours-' . $suffix,
            'address' => 'Jl. Work Hours',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'workhoursadmin' . $suffix,
            'email' => 'workhoursadmin' . $suffix . '@workhours.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'name' => 'Dr Hours ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday', 'wednesday'],
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);
    }

    public function test_edit_form_renders_hours_without_seconds(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/doctors/' . $this->doctor->id . '/edit');

        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('id="start_time" value="09:00"', $html);
        $this->assertStringContainsString('id="end_time" value="17:00"', $html);
        $this->assertStringNotContainsString('value="09:00:00"', $html);
        $this->assertStringNotContainsString('value="17:00:00"', $html);
    }

    public function test_update_accepts_legacy_hours_with_seconds(): void
    {
        $response = $this->actingAs($this->admin)->put('/admin/doctors/' . $this->doctor->id, [
            'name' => $this->doctor->name,
            'specialization' => $this->doctor->specialization,
            'available_days' => ['monday', 'wednesday'],
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_active' => 1,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.doctors.index'));

        $fresh = $this->doctor->fresh();
        $this->assertSame('09:00:00', (string) $fresh->start_time);
        $this->assertSame('17:00:00', (string) $fresh->end_time);
    }

    public function test_update_still_rejects_invalid_hours(): void
    {
        $response = $this->actingAs($this->admin)->put('/admin/doctors/' . $this->doctor->id, [
            'name' => $this->doctor->name,
            'specialization' => $this->doctor->specialization,
            'available_days' => ['monday'],
            'start_time' => '25:99',
            'end_time' => '17:00',
        ]);

        $response->assertSessionHasErrors('start_time');
    }

    public function test_schedule_exception_accepts_legacy_hours_with_seconds(): void
    {
        $date = now()->addDay()->toDateString();

        $response = $this->actingAs($this->admin)
            ->post('/admin/doctors/' . $this->doctor->id . '/schedule-exceptions', [
                'date' => $date,
                'mode' => 'custom',
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'reason' => 'Rapat internal',
            ]);

        $response->assertSessionHasNoErrors();

        $exception = DoctorScheduleException::query()
            ->where('doctor_id', $this->doctor->id)
            ->whereDate('date', $date)
            ->first();

        $this->assertNotNull($exception);
        // Accessor mengembalikan kontrak API "H:i".
        $this->assertSame('10:00', $exception->start_time);
        $this->assertSame('12:00', $exception->end_time);
    }
}
