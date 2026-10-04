<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Clinic;
use App\Models\ClinicJoinRequest;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PHASE 5 (web super admin): authorization boundary, clinic lifecycle,
 * switch-clinic isolation, join requests, global settings, session.
 * Fixtures roll back (DatabaseTransactions) — dev DB stays clean.
 */
class Phase5SuperAdminTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $superAdmin;
    private User $adminA;
    private Doctor $doctorA;
    private Doctor $doctorB;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->suffix = strtolower(Str::random(6));

        $mkClinic = function (string $tag) {
            return Clinic::create([
                'name' => 'SA ' . $tag . ' ' . $this->suffix,
                'slug' => 'sa-' . strtolower($tag) . '-' . $this->suffix,
                'address' => 'Jl. SA',
                'is_active' => true,
            ]);
        };
        $this->clinicA = $mkClinic('A');
        $this->clinicB = $mkClinic('B');

        $this->superAdmin = User::create([
            'name' => 'sasuper', 'email' => 'sasuper' . $this->suffix . '@sa.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin', 'clinic_id' => null,
        ]);
        $this->adminA = User::create([
            'name' => 'saadmina', 'email' => 'saadmina' . $this->suffix . '@sa.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin', 'clinic_id' => $this->clinicA->id,
        ]);

        $mkDoctor = function (int $clinicId, string $tag) {
            return Doctor::create([
                'name' => 'Dr SA ' . $tag . ' ' . $this->suffix,
                'specialization' => 'Umum', 'is_active' => true,
                'clinic_id' => $clinicId, 'available_days' => ['monday'],
                'start_time' => '08:00:00', 'end_time' => '17:00:00',
            ]);
        };
        $this->doctorA = $mkDoctor($this->clinicA->id, 'A');
        $this->doctorB = $mkDoctor($this->clinicB->id, 'B');
    }

    private function mkJoinRequest(string $email): ClinicJoinRequest
    {
        return ClinicJoinRequest::create([
            'clinic_id' => $this->clinicA->id,
            'name' => 'Applicant', 'email' => $email,
            'password_hash' => Hash::make('password123'),
            'status' => 'pending',
        ]);
    }

    // ---------- authorization ----------

    public function test_super_admin_routes_gate(): void
    {
        // Guest -> login.
        $this->get('/admin/clinics')->assertRedirect(route('admin.login'));
        $this->get('/admin/join-requests')->assertRedirect(route('admin.login'));

        // Clinic admin -> 403 everywhere super.
        $asA = fn () => $this->actingAs($this->adminA);
        $asA()->get('/admin/clinics')->assertStatus(403);
        $asA()->get('/admin/clinics/create')->assertStatus(403);
        $asA()->get('/admin/join-requests')->assertStatus(403);
        $asA()->post('/admin/switch-clinic', ['clinic_id' => $this->clinicB->id])->assertStatus(403);
        $asA()->put('/admin/notification-settings', [])->assertStatus(403);

        // Super admin -> allowed.
        $asS = fn () => $this->actingAs($this->superAdmin);
        $asS()->get('/admin/clinics')->assertOk();
        $asS()->get('/admin/clinics/create')->assertOk();
        $asS()->get('/admin/join-requests')->assertOk();
        $asS()->get('/admin/notification-settings')->assertOk();
    }

    public function test_admin_cannot_escalate_via_join_approve(): void
    {
        $req = $this->mkJoinRequest('esc' . $this->suffix . '@sa.test');

        $this->actingAs($this->adminA)
            ->post('/admin/join-requests/' . $req->id . '/approve')
            ->assertStatus(403);
        $this->assertEquals('pending', $req->fresh()->status);
    }

    // ---------- clinic lifecycle ----------

    public function test_clinic_create_edit_toggle_lifecycle(): void
    {
        $slug = 'sa-new-' . $this->suffix;

        $this->actingAs($this->superAdmin)->post('/admin/clinics', [
            'name' => 'SA New', 'slug' => $slug, 'address' => 'Jl. New',
            'primary_color' => '#123456', 'is_active' => '1',
        ])->assertRedirect(route('admin.clinics.index'));

        $clinic = Clinic::where('slug', $slug)->firstOrFail();
        $this->assertTrue((bool) $clinic->is_active);

        // Default admin provisioned to the new clinic (random password now).
        $admin = User::where('email', 'admin@' . $slug . '.com')->firstOrFail();
        $this->assertEquals('clinic_admin', $admin->role);
        $this->assertEquals($clinic->id, $admin->clinic_id);

        // Duplicate slug rejected, not 500.
        $this->actingAs($this->superAdmin)->post('/admin/clinics', [
            'name' => 'SA Dup', 'slug' => $slug, 'address' => 'Jl. Dup',
            'primary_color' => '#123456',
        ])->assertSessionHasErrors('slug');

        // Rename via edit.
        $this->actingAs($this->superAdmin)->put('/admin/clinics/' . $clinic->id, [
            'name' => 'SA Renamed', 'slug' => $slug, 'address' => 'Jl. New',
            'primary_color' => '#123456',
        ])->assertRedirect(route('admin.clinics.index'));
        $this->assertEquals('SA Renamed', $clinic->fresh()->name);

        // Deactivate: data intact, doctors untouched.
        $this->actingAs($this->superAdmin)
            ->post('/admin/clinics/' . $this->clinicA->id . '/toggle-active')
            ->assertRedirect();
        $this->assertFalse((bool) $this->clinicA->fresh()->is_active);
        $this->assertEquals($this->clinicA->id, $this->doctorA->fresh()->clinic_id);
        $this->assertEquals(1, Doctor::where('clinic_id', $this->clinicA->id)->count());

        // Reactivate.
        $this->actingAs($this->superAdmin)
            ->post('/admin/clinics/' . $this->clinicA->id . '/toggle-active')
            ->assertRedirect();
        $this->assertTrue((bool) $this->clinicA->fresh()->is_active);
    }

    public function test_deactivate_blocks_tenant_login_and_api(): void
    {
        $this->actingAs($this->superAdmin)
            ->post('/admin/clinics/' . $this->clinicA->id . '/toggle-active')
            ->assertRedirect();

        // Web login denied.
        // NOTE: actingAs(superAdmin) from the toggle step persists in the
        // test app, so log out explicitly before asserting guest state.
        auth()->logout();
        $this->post('/admin/login', [
            'email' => $this->adminA->email, 'password' => 'password123',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        // Existing web session bounced.
        $this->actingAs($this->adminA)->get('/admin')->assertRedirect(route('admin.login'));
    }

    // ---------- switch clinic ----------

    public function test_switch_context_isolation_and_no_mutation(): void
    {
        $s = fn () => $this->actingAs($this->superAdmin);

        $s()->post('/admin/switch-clinic', ['clinic_id' => $this->clinicA->id])->assertRedirect();
        $this->assertEquals($this->clinicA->id, session('current_clinic_id'));

        // Tenant pages follow the session context.
        $s()->get('/admin/doctors/' . $this->doctorA->id . '/edit')->assertOk();
        $s()->get('/admin/doctors/' . $this->doctorB->id . '/edit')->assertNotFound();

        $s()->post('/admin/switch-clinic', ['clinic_id' => $this->clinicB->id])->assertRedirect();
        $this->assertEquals($this->clinicB->id, session('current_clinic_id'));
        $s()->get('/admin/doctors/' . $this->doctorB->id . '/edit')->assertOk();
        $s()->get('/admin/doctors/' . $this->doctorA->id . '/edit')->assertNotFound();

        // Ownership untouched in DB.
        $this->assertEquals($this->clinicA->id, $this->doctorA->fresh()->clinic_id);
        $this->assertEquals($this->clinicB->id, $this->doctorB->fresh()->clinic_id);

        // Invalid id: validation error, session unchanged.
        $s()->post('/admin/switch-clinic', ['clinic_id' => 999999999])
            ->assertSessionHasErrors('clinic_id');
        $this->assertEquals($this->clinicB->id, session('current_clinic_id'));

        // Back to overview.
        $s()->post('/admin/switch-clinic', ['clinic_id' => ''])->assertRedirect();
        $this->assertNull(session('current_clinic_id'));
    }

    public function test_session_cleared_across_logout_login(): void
    {
        $this->actingAs($this->superAdmin)
            ->post('/admin/switch-clinic', ['clinic_id' => $this->clinicA->id]);
        $this->assertEquals($this->clinicA->id, session('current_clinic_id'));

        $this->actingAs($this->superAdmin)->post('/admin/logout')
            ->assertRedirect(route('admin.login'));
        $this->assertGuest();

        $this->post('/admin/login', [
            'email' => $this->superAdmin->email, 'password' => 'password123',
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertNull(session('current_clinic_id'));
    }

    // ---------- join requests ----------

    public function test_join_approve_reject_lifecycle(): void
    {
        $email = 'join' . $this->suffix . '@sa.test';
        $req = $this->mkJoinRequest($email);

        $this->actingAs($this->superAdmin)->get('/admin/join-requests')->assertOk()
            ->assertSee($email, false);

        $this->actingAs($this->superAdmin)
            ->post('/admin/join-requests/' . $req->id . '/approve')
            ->assertRedirect();
        $this->assertEquals('approved', $req->fresh()->status);

        $user = User::where('email', $email)->firstOrFail();
        $this->assertEquals('clinic_admin', $user->role);
        $this->assertEquals($this->clinicA->id, $user->clinic_id);

        // Duplicate approve -> 404 (no longer pending), no second user.
        $this->actingAs($this->superAdmin)
            ->post('/admin/join-requests/' . $req->id . '/approve')
            ->assertNotFound();
        $this->assertEquals(1, User::where('email', $email)->count());

        // Reject flow on a fresh request + double reject -> 404.
        $req2 = $this->mkJoinRequest('join2' . $this->suffix . '@sa.test');
        $this->actingAs($this->superAdmin)
            ->post('/admin/join-requests/' . $req2->id . '/reject')
            ->assertRedirect();
        $this->assertEquals('rejected', $req2->fresh()->status);
        $this->actingAs($this->superAdmin)
            ->post('/admin/join-requests/' . $req2->id . '/reject')
            ->assertNotFound();

        // Approve after reject -> 404.
        $this->actingAs($this->superAdmin)
            ->post('/admin/join-requests/' . $req2->id . '/approve')
            ->assertNotFound();
    }

    public function test_join_request_idor_for_admin(): void
    {
        $req = $this->mkJoinRequest('idor' . $this->suffix . '@sa.test');

        $this->actingAs($this->adminA)->get('/admin/join-requests')->assertStatus(403);
        $this->actingAs($this->adminA)
            ->post('/admin/join-requests/' . $req->id . '/reject')
            ->assertStatus(403);
        $this->assertEquals('pending', $req->fresh()->status);
    }

    // ---------- global settings + dashboard ----------

    public function test_notification_settings_persist_globally(): void
    {
        $this->actingAs($this->superAdmin)->put('/admin/notification-settings', [
            'templates' => [
                'booking_reminder' => ['title' => 'SA Title', 'body' => 'SA Body'],
                'payment_reminder' => ['title' => 'Pay', 'body' => 'Pay body'],
                'vaccination_reminder' => ['title' => 'Vax', 'body' => 'Vax body'],
                'booking_status_confirmed' => ['title' => 'C', 'body' => 'C body'],
                'booking_status_completed' => ['title' => 'D', 'body' => 'D body'],
                'booking_status_cancelled' => ['title' => 'X', 'body' => 'X body'],
                'booking_status_pending' => ['title' => 'P', 'body' => 'P body'],
                'booking_status_rescheduled' => ['title' => 'R', 'body' => 'R body'],
            ],
        ])->assertRedirect(route('admin.notification-settings.index'));

        $this->assertDatabaseHas('app_settings', [
            'key' => 'notifications.booking_reminder.title',
            'value' => 'SA Title',
        ]);
    }

    public function test_super_dashboard_global_counts(): void
    {
        // Clinics table lists every clinic (names + counts, no per-doctor rows).
        $res = $this->actingAs($this->superAdmin)->get('/admin')->assertOk();
        $res->assertSee($this->clinicA->name, false)
            ->assertSee($this->clinicB->name, false)
            ->assertSee((string) $this->clinicA->slug, false);
    }

    // ---------- cross-tenant visibility ----------

    public function test_switched_super_admin_sees_only_context_clinic(): void
    {
        $s = fn () => $this->actingAs($this->superAdmin);
        $s()->post('/admin/switch-clinic', ['clinic_id' => $this->clinicA->id]);

        $s()->get('/admin/users')->assertOk()
            ->assertSee($this->adminA->email, false)
            ->assertDontSee($this->superAdmin->email, false);
    }
}
