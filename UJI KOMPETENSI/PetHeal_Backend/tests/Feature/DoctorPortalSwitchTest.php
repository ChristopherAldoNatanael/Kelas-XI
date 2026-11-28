<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin yang membuka portal dokter tidak boleh dilempar kembali ke dasbor
 * admin tanpa penjelasan. Sesi admin harus ditutup lebih dulu karena portal
 * dokter memakai akun dan guard yang sama.
 */
class DoctorPortalSwitchTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinic;
    private User $admin;
    private User $doctorUser;
    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = strtolower(Str::random(6));

        $this->clinic = Clinic::create([
            'name' => 'Klinik Switch ' . $suffix,
            'slug' => 'klinik-switch-' . $suffix,
            'address' => 'Jl. Switch',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Switch',
            'email' => 'admin-switch-' . $suffix . '@test.local',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctorUser = User::create([
            'name' => 'Dr Switch ' . $suffix,
            'email' => 'dokter-switch-' . $suffix . '@test.local',
            'password' => Hash::make('password123'),
            'role' => 'doctor',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'name' => 'Dr Switch ' . $suffix,
            'specialization' => 'Umum',
            'email' => $this->doctorUser->email,
            'user_id' => $this->doctorUser->id,
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
    }

    public function test_admin_doctor_page_links_to_doctor_login_with_admin_marker(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/doctors/' . $this->doctor->id)
            ->assertOk()
            ->assertSee(route('doctor.login', ['from_admin' => 1]), false)
            ->assertSee('Buka portal dokter');
    }

    public function test_admin_marker_shows_login_page_and_session_notice(): void
    {
        $this->actingAs($this->admin)
            ->get('/doctor/login?from_admin=1')
            ->assertOk()
            ->assertSee('Sesi admin masih aktif')
            ->assertSee('Keluar dari akun admin')
            ->assertSee($this->admin->name);
    }

    public function test_normal_doctor_login_still_redirects_admin_to_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->get('/doctor/login')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_logout_can_return_to_doctor_login(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/logout', ['redirect' => 'doctor.login'])
            ->assertRedirect(route('doctor.login'));

        $this->assertGuest();
    }

    public function test_doctor_login_after_admin_logout_enters_doctor_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/logout', ['redirect' => 'doctor.login']);

        $this->app['auth']->forgetGuards();

        $this->post('/doctor/login', [
            'email' => $this->doctorUser->email,
            'password' => 'password123',
        ])->assertRedirect(route('doctor.dashboard'));

        $this->assertAuthenticatedAs($this->doctorUser);
    }
}
