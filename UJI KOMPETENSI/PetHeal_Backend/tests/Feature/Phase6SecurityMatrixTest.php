<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\DeviceToken;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Notification;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PHASE 6 (security matrix): actor x target negative tests across
 * GET/POST/PUT/DELETE, IDOR, field smuggling, CSRF.
 */
class Phase6SecurityMatrixTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $adminA;
    private User $userA;
    private User $userB;
    private User $doctorUser;
    private Doctor $doctorB;
    private Service $serviceB;
    private Booking $bookingB;
    private Pet $petB;
    private MedicalRecord $recordB;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->suffix = strtolower(Str::random(6));

        $mkClinic = function (string $tag) {
            return Clinic::create([
                'name' => 'SEC ' . $tag . ' ' . $this->suffix,
                'slug' => 'sec-' . strtolower($tag) . '-' . $this->suffix,
                'address' => 'Jl. SEC',
                'is_active' => true,
            ]);
        };
        $this->clinicA = $mkClinic('A');
        $this->clinicB = $mkClinic('B');

        $mkUser = function (string $tag, ?int $clinicId, string $role = 'user') {
            return User::create([
                'name' => $tag, 'email' => $tag . $this->suffix . '@sec.test',
                'password' => Hash::make('password123'),
                'role' => $role, 'clinic_id' => $clinicId,
            ]);
        };
        $this->adminA = $mkUser('secadmina', $this->clinicA->id, 'clinic_admin');
        $this->userA = $mkUser('secusera', $this->clinicA->id);
        $this->userB = $mkUser('secuserb', $this->clinicB->id);
        $this->doctorUser = $mkUser('secdoctor', $this->clinicA->id, 'doctor');

        $this->doctorB = Doctor::create([
            'name' => 'Dr SEC B', 'specialization' => 'Umum',
            'is_active' => true, 'clinic_id' => $this->clinicB->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00', 'end_time' => '17:00:00',
        ]);
        $this->serviceB = Service::create([
            'name' => 'Layanan SEC B', 'price' => 60000,
            'is_active' => true, 'clinic_id' => $this->clinicB->id,
        ]);
        $this->petB = Pet::create([
            'user_id' => $this->userB->id, 'name' => 'SecPetB',
            'species' => 'Dog', 'gender' => 'female',
        ]);
        $this->bookingB = Booking::create([
            'user_id' => $this->userB->id, 'pet_id' => $this->petB->id,
            'doctor_id' => $this->doctorB->id, 'service_id' => $this->serviceB->id,
            'booking_date' => Carbon::tomorrow()->format('Y-m-d'),
            'booking_time' => '09:00', 'status' => 'pending',
            'payment_status' => 'pending', 'payment_type' => 'full',
            'total_amount' => 60000, 'paid_amount' => 0,
            'remaining_amount' => 60000, 'clinic_id' => $this->clinicB->id,
        ]);
        $this->recordB = MedicalRecord::create([
            'booking_id' => $this->bookingB->id, 'pet_id' => $this->petB->id,
            'doctor_id' => $this->doctorB->id, 'clinic_id' => $this->clinicB->id,
            'diagnosis' => 'SecretB', 'treatment' => 'T',
        ]);
    }

    private function apiAs(User $user, array $headers = [])
    {
        auth()->forgetGuards();
        $token = $user->createToken('phase6sec-test')->plainTextToken;

        return $this->withHeaders(array_merge([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ], $headers));
    }

    // ---------- web IDOR: PUT/DELETE ----------

    public function test_web_idor_put_delete_denied(): void
    {
        $asA = fn () => $this->actingAs($this->adminA);

        $asA()->put('/admin/doctors/' . $this->doctorB->id, [
            'name' => 'Hijacked', 'specialization' => 'Umum',
            'available_days' => ['monday'],
            'start_time' => '09:00', 'end_time' => '17:00',
        ])->assertNotFound();
        $this->assertEquals('Dr SEC B', $this->doctorB->fresh()->name);

        $asA()->put('/admin/services/' . $this->serviceB->id, [
            'name' => 'Hijacked', 'price' => 1,
        ])->assertNotFound();
        $this->assertEquals('Layanan SEC B', $this->serviceB->fresh()->name);

        $asA()->delete('/admin/doctors/' . $this->doctorB->id)->assertNotFound();
        $this->assertNotNull(Doctor::find($this->doctorB->id));

        $asA()->get('/admin/medical-records/' . $this->recordB->id)->assertNotFound();
        $asA()->get('/admin/payments/' . $this->bookingB->id)->assertNotFound();
        $asA()->get('/admin/users/' . $this->userB->id)->assertNotFound();
    }

    public function test_web_super_only_and_doctor_role_denied(): void
    {
        $this->actingAs($this->adminA)->get('/admin/clinics/' . $this->clinicB->id . '/edit')->assertStatus(403);

        // doctor role cannot enter admin panel at all.
        $this->actingAs($this->doctorUser)->get('/admin')->assertRedirect(route('admin.login'));

        // Guest POST cannot mutate.
        $this->post('/admin/doctors', ['name' => 'Ghost'])->assertRedirect(route('admin.login'));
        $this->post('/admin/bookings/' . $this->bookingB->id . '/confirm')
            ->assertRedirect(route('admin.login'));
        $this->assertEquals('pending', $this->bookingB->fresh()->status);
    }

    // ---------- web field smuggling ----------

    public function test_web_field_smuggling_ignored(): void
    {
        $this->actingAs($this->adminA)->put('/admin/services/' . $this->serviceB->id, [
            'name' => 'x', 'price' => 1, 'clinic_id' => $this->clinicA->id,
        ])->assertNotFound();
        $this->assertEquals($this->clinicB->id, $this->serviceB->fresh()->clinic_id);

        $this->actingAs($this->adminA)->post('/admin/users', [
            'name' => 'S', 'email' => 'smug' . $this->suffix . '@sec.test',
            'password' => 'password123', 'role' => 'super_admin',
            'is_active' => true, 'clinic_id' => $this->clinicB->id,
        ])->assertRedirect(route('admin.users.index'));
        $created = User::where('email', 'smug' . $this->suffix . '@sec.test')->firstOrFail();
        $this->assertEquals('user', $created->role);
        $this->assertEquals($this->clinicA->id, $created->clinic_id);
    }

    // ---------- API IDOR ----------

    public function test_api_idor_denied(): void
    {
        $apiA = fn () => $this->apiAs($this->userA);

        $apiA()->putJson('/api/pets/' . $this->petB->id, ['name' => 'Hijacked'])->assertStatus(404);
        $this->assertEquals('SecPetB', $this->petB->fresh()->name);

        $apiA()->deleteJson('/api/bookings/' . $this->bookingB->id)->assertStatus(404);
        $this->assertNotNull(Booking::find($this->bookingB->id));

        $apiA()->getJson('/api/medical-records/' . $this->recordB->id)->assertStatus(404);

        $apiA()->postJson('/api/medical-records/' . $this->recordB->id . '/pay')->assertStatus(404);

        $apiA()->getJson('/api/payment/booking/' . $this->bookingB->id)->assertStatus(404);

        $apiA()->postJson('/api/doctors/' . $this->doctorB->id . '/reviews', [
            'booking_id' => $this->bookingB->id, 'rating' => 5,
        ])->assertStatus(404);
    }

    public function test_cross_user_notifications_and_tokens_isolated(): void
    {
        $notifB = Notification::create([
            'user_id' => $this->userB->id, 'clinic_id' => $this->clinicB->id,
            'title' => 'Hi B', 'body' => 'secret', 'type' => 'general',
        ]);

        $this->apiAs($this->userA)->postJson('/api/notifications/' . $notifB->id . '/read')
            ->assertStatus(404);
        $this->assertNull($notifB->fresh()->read_at);

        $tokenB = DeviceToken::create([
            'user_id' => $this->userB->id,
            'token' => 'token-b-' . $this->suffix, 'device_type' => 'android',
        ]);
        $this->apiAs($this->userA)->deleteJson('/api/device-token', [
            'token' => 'token-b-' . $this->suffix,
        ])->assertOk();
        $this->assertNotNull(DeviceToken::find($tokenB->id));
    }

    // ---------- API auth + CSRF ----------

    public function test_api_unauth_and_csrf_wired(): void
    {
        $this->getJson('/api/bookings')->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->postJson('/api/bookings', [])->assertStatus(401);

        // CSRF tokens are bypassed by the framework itself when
        // APP_ENV=testing, so a live 419 cannot be observed in-suite.
        // Instead prove the middleware is wired into the web group
        // (production behavior), which is the actual security property.
        $web = $this->app->make('router')->getMiddlewareGroups()['web'] ?? [];
        $this->assertContains(
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            $web
        );
    }

    // ---------- inactive clinic full denial ----------

    public function test_inactive_clinic_denies_everything(): void
    {
        $this->clinicA->update(['is_active' => false]);

        $this->apiAs($this->userA)->getJson('/api/pets')->assertStatus(403);
        $this->apiAs($this->userA)->getJson('/api/dashboard')->assertStatus(403);

        $this->actingAs($this->adminA)->get('/admin/bookings/' . $this->bookingB->id)
            ->assertRedirect(route('admin.login'));
    }
}
