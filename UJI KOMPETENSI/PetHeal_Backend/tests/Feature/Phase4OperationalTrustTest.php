<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorScheduleException;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Services\FCMService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PHASE 4 (Operational Trust & Proof).
 *
 * Cakupan: cuti/jam khusus per tanggal (API slot, create/reschedule booking,
 * UI admin), status reminder, dan dedupe reminder otomatis harian.
 */
class Phase4OperationalTrustTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $userA;
    private User $adminA;
    private Doctor $doctorA;
    private Doctor $doctorB;
    private Service $serviceA;
    private Pet $petA;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->suffix = strtolower(Str::random(6));

        $mkClinic = fn (string $tag) => Clinic::create([
            'name' => 'OT ' . $tag . ' ' . $this->suffix,
            'slug' => 'ot-' . strtolower($tag) . '-' . $this->suffix,
            'address' => 'Jl. Operational Trust',
            'is_active' => true,
        ]);
        $this->clinicA = $mkClinic('A');
        $this->clinicB = $mkClinic('B');

        $mkUser = function (string $tag, string $role, ?int $clinicId) {
            return User::create([
                'name' => $tag . ' ' . $this->suffix,
                'email' => strtolower($tag) . '-' . $this->suffix . '@ot.test',
                'password' => Hash::make('password123'),
                'role' => $role,
                'clinic_id' => $clinicId,
            ]);
        };
        $this->userA = $mkUser('ownerA', 'user', $this->clinicA->id);
        $this->adminA = $mkUser('adminA', 'clinic_admin', $this->clinicA->id);

        $mkDoctor = fn (int $clinicId, string $tag) => Doctor::create([
            'name' => 'Dr OT ' . $tag . ' ' . $this->suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $clinicId,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
        $this->doctorA = $mkDoctor($this->clinicA->id, 'A');
        $this->doctorB = $mkDoctor($this->clinicB->id, 'B');

        $this->serviceA = Service::create([
            'name' => 'Konsultasi OT ' . $this->suffix,
            'price' => 100000,
            'is_active' => true,
            'clinic_id' => $this->clinicA->id,
        ]);

        $this->petA = Pet::create([
            'user_id' => $this->userA->id,
            'name' => 'OT Pet ' . $this->suffix,
            'species' => 'Cat',
            'gender' => 'male',
        ]);
    }

    private function apiAs(User $user)
    {
        auth()->forgetGuards();
        $token = $user->createToken('phase4-test')->plainTextToken;

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
    }

    private function tomorrow(): string
    {
        return Carbon::tomorrow()->format('Y-m-d');
    }

    private function makeException(array $attributes = []): DoctorScheduleException
    {
        return DoctorScheduleException::create(array_merge([
            'doctor_id' => $this->doctorA->id,
            'clinic_id' => $this->clinicA->id,
            'date' => $this->tomorrow(),
            'is_closed' => true,
            'reason' => 'Cuti tahunan',
        ], $attributes));
    }

    private function makeBooking(string $date, string $time, string $status = 'pending'): Booking
    {
        return Booking::create([
            'user_id' => $this->userA->id,
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->serviceA->id,
            'booking_date' => $date,
            'booking_time' => $time,
            'status' => $status,
            'payment_status' => 'pending',
            'payment_type' => 'full',
            'total_amount' => 100000,
            'paid_amount' => 0,
            'remaining_amount' => 100000,
            'clinic_id' => $this->clinicA->id,
        ]);
    }

    // ---------- Slot API: cuti & jam khusus ----------

    public function test_slots_report_leave_day_with_metadata(): void
    {
        $this->makeException();

        $this->apiAs($this->userA)
            ->getJson('/api/doctors/' . $this->doctorA->id . '/slots?date=' . $this->tomorrow())
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.is_closed', true)
            ->assertJsonPath('meta.is_working_day', false)
            ->assertJsonPath('meta.reason', 'Cuti tahunan');
    }

    public function test_slots_follow_custom_hours_for_one_date(): void
    {
        $this->makeException([
            'is_closed' => false,
            'start_time' => '10:00',
            'end_time' => '12:00',
            'reason' => 'Praktik pagi saja',
        ]);

        $response = $this->apiAs($this->userA)
            ->getJson('/api/doctors/' . $this->doctorA->id . '/slots?date=' . $this->tomorrow())
            ->assertOk()
            ->assertJsonPath('meta.is_closed', false)
            ->assertJsonPath('meta.has_exception', true)
            ->assertJsonPath('meta.start_time', '10:00')
            ->assertJsonPath('meta.end_time', '12:00');

        $this->assertSame(
            ['10:00', '10:30', '11:00', '11:30'],
            collect($response->json('data'))->pluck('time')->all()
        );
    }

    public function test_custom_hours_can_open_a_weekly_closed_day(): void
    {
        $this->doctorA->update(['available_days' => ['monday']]);
        $date = Carbon::tomorrow();

        // Pastikan tanggal uji bukan Senin, supaya benar-benar membuktikan
        // pengecualian membuka hari yang biasanya tutup.
        if ($date->isMonday()) {
            $date = $date->addDay();
        }

        $this->makeException([
            'date' => $date->format('Y-m-d'),
            'is_closed' => false,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $this->apiAs($this->userA)
            ->getJson('/api/doctors/' . $this->doctorA->id . '/slots?date=' . $date->format('Y-m-d'))
            ->assertOk()
            ->assertJsonPath('meta.is_working_day', true)
            ->assertJsonCount(4, 'data');
    }

    // ---------- Booking create/reschedule ----------

    public function test_booking_is_rejected_on_leave_day(): void
    {
        $this->makeException();

        $this->apiAs($this->userA)->postJson('/api/bookings', [
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->serviceA->id,
            'booking_date' => $this->tomorrow(),
            'booking_time' => '09:00',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'slot_unavailable');

        $this->assertSame(0, Booking::where('doctor_id', $this->doctorA->id)->count());
    }

    public function test_booking_is_rejected_outside_effective_hours(): void
    {
        $this->makeException([
            'is_closed' => false,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $this->apiAs($this->userA)->postJson('/api/bookings', [
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->serviceA->id,
            'booking_date' => $this->tomorrow(),
            'booking_time' => '09:00',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'slot_unavailable');
    }

    public function test_booking_is_rejected_on_off_grid_time(): void
    {
        $this->apiAs($this->userA)->postJson('/api/bookings', [
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->serviceA->id,
            'booking_date' => $this->tomorrow(),
            'booking_time' => '09:07',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'slot_unavailable');
    }

    public function test_booking_inside_custom_hours_succeeds(): void
    {
        $this->makeException([
            'is_closed' => false,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $this->apiAs($this->userA)->postJson('/api/bookings', [
            'pet_id' => $this->petA->id,
            'doctor_id' => $this->doctorA->id,
            'service_id' => $this->serviceA->id,
            'booking_date' => $this->tomorrow(),
            'booking_time' => '10:30',
        ])->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('bookings', [
            'doctor_id' => $this->doctorA->id,
            'booking_time' => '10:30',
        ]);
    }

    public function test_reschedule_is_rejected_on_leave_day(): void
    {
        $booking = $this->makeBooking(Carbon::today()->format('Y-m-d'), '09:00');
        $this->makeException();

        $this->apiAs($this->userA)->postJson('/api/bookings/' . $booking->id . '/reschedule', [
            'booking_date' => $this->tomorrow(),
            'booking_time' => '09:00',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'slot_unavailable');

        $this->assertSame(Carbon::today()->format('Y-m-d'), $booking->fresh()->booking_date);
    }

    public function test_reschedule_still_returns_conflict_code_for_taken_slot(): void
    {
        $existing = $this->makeBooking($this->tomorrow(), '09:00');
        $booking = $this->makeBooking(Carbon::today()->format('Y-m-d'), '10:00');

        $this->apiAs($this->userA)->postJson('/api/bookings/' . $booking->id . '/reschedule', [
            'booking_date' => $this->tomorrow(),
            'booking_time' => '09:00',
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'slot_conflict');

        $this->assertNotNull($existing->fresh());
    }

    // ---------- Admin UI: cuti/jam khusus ----------

    public function test_admin_can_store_and_delete_exception_for_own_clinic(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/doctors/' . $this->doctorA->id . '/schedule-exceptions', [
                'date' => $this->tomorrow(),
                'mode' => 'closed',
                'reason' => 'Seminar nasional',
            ])
            ->assertRedirect(route('admin.doctors.schedule', [
                'id' => $this->doctorA->id,
                'date' => $this->tomorrow(),
            ]));

        $exception = DoctorScheduleException::where('doctor_id', $this->doctorA->id)->first();
        $this->assertNotNull($exception);
        $this->assertTrue($exception->is_closed);
        $this->assertSame($this->clinicA->id, $exception->clinic_id);
        $this->assertSame($this->adminA->id, $exception->created_by);

        $this->assertTrue(
            AuditLog::where('action', 'doctor.schedule_exception.save')
                ->where('model_id', $this->doctorA->id)
                ->exists()
        );

        $this->actingAs($this->adminA)
            ->delete('/admin/doctors/' . $this->doctorA->id . '/schedule-exceptions/' . $exception->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('doctor_schedule_exceptions', ['id' => $exception->id]);
        $this->assertTrue(
            AuditLog::where('action', 'doctor.schedule_exception.delete')
                ->where('model_id', $this->doctorA->id)
                ->exists()
        );
    }

    public function test_exception_is_unique_per_doctor_and_date(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/doctors/' . $this->doctorA->id . '/schedule-exceptions', [
                'date' => $this->tomorrow(),
                'mode' => 'closed',
            ])->assertRedirect();

        $this->actingAs($this->adminA)
            ->post('/admin/doctors/' . $this->doctorA->id . '/schedule-exceptions', [
                'date' => $this->tomorrow(),
                'mode' => 'custom',
                'start_time' => '09:00',
                'end_time' => '12:00',
                'reason' => 'Hanya pagi',
            ])->assertRedirect();

        $rows = DoctorScheduleException::where('doctor_id', $this->doctorA->id)
            ->whereDate('date', $this->tomorrow())
            ->get();

        $this->assertCount(1, $rows);
        $this->assertFalse($rows->first()->is_closed);
        $this->assertSame('09:00', $rows->first()->start_time);
    }

    public function test_admin_cannot_touch_other_clinic_doctor_schedule(): void
    {
        $this->actingAs($this->adminA)
            ->get('/admin/doctors/' . $this->doctorB->id . '/schedule')
            ->assertNotFound();

        $this->actingAs($this->adminA)
            ->post('/admin/doctors/' . $this->doctorB->id . '/schedule-exceptions', [
                'date' => $this->tomorrow(),
                'mode' => 'closed',
            ])
            ->assertNotFound();

        $this->assertSame(0, DoctorScheduleException::where('doctor_id', $this->doctorB->id)->count());
    }

    public function test_store_warns_about_existing_bookings_without_cancelling_them(): void
    {
        $booking = $this->makeBooking($this->tomorrow(), '09:00');

        $this->actingAs($this->adminA)
            ->post('/admin/doctors/' . $this->doctorA->id . '/schedule-exceptions', [
                'date' => $this->tomorrow(),
                'mode' => 'closed',
                'reason' => 'Cuti mendadak',
            ])
            ->assertSessionHas('warning');

        // Booking lama tidak boleh dibatalkan diam-diam.
        $this->assertSame('pending', $booking->fresh()->status);
    }

    public function test_admin_schedule_page_renders_exceptions(): void
    {
        $this->makeException();

        $this->actingAs($this->adminA)
            ->get('/admin/doctors/' . $this->doctorA->id . '/schedule?date=' . $this->tomorrow())
            ->assertOk()
            ->assertSee('Jadwal & Cuti Dokter')
            ->assertSee('Cuti tahunan')
            ->assertSee('Simpan pengecualian');
    }

    public function test_schedule_board_shows_leave_badge_and_review_flag(): void
    {
        $this->makeException(['reason' => 'Cuti tahunan']);
        $this->makeBooking($this->tomorrow(), '09:00');

        $this->actingAs($this->adminA)
            ->get('/admin/schedule?date=' . $this->tomorrow())
            ->assertOk()
            ->assertSee('Cuti')
            ->assertSee('Di luar jadwal')
            ->assertSee('Cuti tahunan');
    }

    // ---------- Reminder status ----------

    public function test_manual_reminder_records_last_reminder_at_only_when_delivered(): void
    {
        $booking = $this->makeBooking($this->tomorrow(), '09:00');

        $this->mock(FCMService::class, function ($mock) {
            $mock->shouldReceive('sendManualReminder')->once()->andReturn(true);
        });

        $this->actingAs($this->adminA)
            ->post('/admin/bookings/' . $booking->id . '/send-reminder', [
                'reminder_type' => 'tomorrow',
            ])
            ->assertRedirect();

        $this->assertNotNull($booking->fresh()->last_reminder_at);
    }

    public function test_failed_manual_reminder_keeps_status_empty(): void
    {
        $booking = $this->makeBooking($this->tomorrow(), '09:00');

        $this->mock(FCMService::class, function ($mock) {
            $mock->shouldReceive('sendManualReminder')->once()->andReturn(false);
        });

        $this->actingAs($this->adminA)
            ->post('/admin/bookings/' . $booking->id . '/send-reminder', [
                'reminder_type' => 'tomorrow',
            ])
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertNull($booking->fresh()->last_reminder_at);
    }

    public function test_automatic_reminder_is_not_sent_twice_on_the_same_day(): void
    {
        $booking = $this->makeBooking($this->tomorrow(), '09:00');
        $sentFor = [];

        $this->mock(FCMService::class, function ($mock) use (&$sentFor) {
            $mock->shouldReceive('sendBookingReminder')
                ->andReturnUsing(function ($userId, $petName, $date, $time, $bookingId) use (&$sentFor) {
                    $sentFor[] = $bookingId;
                    return true;
                });
            $mock->shouldReceive('sendVaccinationReminder')->andReturn(true);
        });

        $this->artisan('reminders:send')->assertExitCode(0);
        $this->assertNotNull($booking->fresh()->last_reminder_at);
        $this->assertSame(1, collect($sentFor)->filter(fn ($id) => $id === $booking->id)->count());

        // Run kedua pada hari yang sama tidak boleh mengirim ulang.
        $this->artisan('reminders:send')->assertExitCode(0);
        $this->assertSame(1, collect($sentFor)->filter(fn ($id) => $id === $booking->id)->count());
    }

    public function test_booking_detail_shows_reminder_status(): void
    {
        $booking = $this->makeBooking($this->tomorrow(), '09:00');

        $this->actingAs($this->adminA)
            ->get('/admin/bookings/' . $booking->id)
            ->assertOk()
            ->assertSee('Belum diingatkan');

        $booking->update(['last_reminder_at' => now()]);

        $this->actingAs($this->adminA)
            ->get('/admin/bookings/' . $booking->id)
            ->assertOk()
            ->assertSee('Terakhir diingatkan');
    }
}
