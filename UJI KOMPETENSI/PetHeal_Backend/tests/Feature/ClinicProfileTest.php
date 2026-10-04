<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Clinic profile (own clinic) — clinic_admin may update name, address,
 * phone, email, logo, color, description. Slug & is_active stay
 * super_admin-only and must be ignored even when submitted.
 * Fixtures roll back (DatabaseTransactions) — dev DB stays clean.
 */
class ClinicProfileTest extends TestCase
{
    use DatabaseTransactions;

    private string $suffix;
    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $adminA;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->suffix = strtolower(Str::random(6));

        $this->clinicA = Clinic::create([
            'name' => 'CP A ' . $this->suffix,
            'slug' => 'cp-a-' . $this->suffix,
            'address' => 'Jl. CP A',
            'phone' => '021-111',
            'email' => 'a@cp.test',
            'primary_color' => '#18C964',
            'description' => 'Desc A',
            'is_active' => true,
        ]);
        $this->clinicB = Clinic::create([
            'name' => 'CP B ' . $this->suffix,
            'slug' => 'cp-b-' . $this->suffix,
            'address' => 'Jl. CP B',
            'is_active' => true,
        ]);

        $this->adminA = User::create([
            'name' => 'cpadmina', 'email' => 'cpadmina' . $this->suffix . '@cp.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin', 'clinic_id' => $this->clinicA->id,
        ]);
        $this->superAdmin = User::create([
            'name' => 'cpsuper', 'email' => 'cpsuper' . $this->suffix . '@cp.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin', 'clinic_id' => null,
        ]);
    }

    public function test_guest_redirected_to_login(): void
    {
        $this->get('/admin/clinic-profile')->assertRedirect(route('admin.login'));
        $this->put('/admin/clinic-profile', [])->assertRedirect(route('admin.login'));
    }

    public function test_clinic_admin_can_view_own_profile(): void
    {
        $this->actingAs($this->adminA)
            ->get(route('admin.clinic-profile'))
            ->assertOk()
            ->assertSee($this->clinicA->name)
            ->assertSee($this->clinicA->slug)
            ->assertSee(__('menu.clinic_profile'))
            ->assertSee(__('clinics.slug_locked'), false);
    }

    public function test_clinic_admin_can_update_own_profile(): void
    {
        $this->actingAs($this->adminA)
            ->put(route('admin.clinic-profile.update'), [
                'name' => 'CP A Renamed',
                'address' => 'Jl. Baru No. 9',
                'phone' => '021-999',
                'email' => 'baru@cp.test',
                'primary_color' => '#0EA5A5',
                'description' => 'Deskripsi baru',
            ])
            ->assertRedirect(route('admin.clinic-profile'));

        $this->assertDatabaseHas('clinics', [
            'id' => $this->clinicA->id,
            'name' => 'CP A Renamed',
            'address' => 'Jl. Baru No. 9',
            'phone' => '021-999',
            'email' => 'baru@cp.test',
            'primary_color' => '#0EA5A5',
            'description' => 'Deskripsi baru',
        ]);
    }

    public function test_clinic_admin_cannot_change_slug_or_status(): void
    {
        $this->actingAs($this->adminA)
            ->put(route('admin.clinic-profile.update'), [
                'name' => 'CP A Renamed',
                'address' => 'Jl. Baru',
                'primary_color' => '#0EA5A5',
                // hostile extras — must be silently ignored
                'slug' => 'hacked-slug',
                'is_active' => false,
            ])
            ->assertRedirect(route('admin.clinic-profile'));

        $fresh = $this->clinicA->fresh();
        $this->assertSame('cp-a-' . $this->suffix, $fresh->slug);
        $this->assertTrue((bool) $fresh->is_active);
        $this->assertSame('CP A Renamed', $fresh->name);
    }

    public function test_clinic_admin_cannot_affect_other_clinic(): void
    {
        $this->actingAs($this->adminA)
            ->put(route('admin.clinic-profile.update'), [
                'name' => 'Trying To Hit B',
                'address' => 'Jl. Baru',
                'primary_color' => '#0EA5A5',
            ])
            ->assertRedirect(route('admin.clinic-profile'));

        $this->assertSame('CP B ' . $this->suffix, $this->clinicB->fresh()->name);
    }

    public function test_clinic_admin_can_upload_logo(): void
    {
        $this->actingAs($this->adminA)
            ->put(route('admin.clinic-profile.update'), [
                'name' => $this->clinicA->name,
                'address' => $this->clinicA->address,
                'primary_color' => '#0EA5A5',
                'logo' => UploadedFile::fake()->image('logo.png', 800, 400),
            ])
            ->assertRedirect(route('admin.clinic-profile'));

        $logoPath = $this->clinicA->fresh()->logo_path;
        $this->assertNotNull($logoPath);
        $fullPath = storage_path('app/public/' . $logoPath);
        $this->assertFileExists($fullPath);
        @unlink($fullPath);
    }

    public function test_validation_rejects_bad_color(): void
    {
        $this->actingAs($this->adminA)
            ->put(route('admin.clinic-profile.update'), [
                'name' => 'CP A',
                'address' => 'Jl. Baru',
                'primary_color' => 'red',
            ])
            ->assertSessionHasErrors('primary_color');

        $this->assertSame('#18C964', $this->clinicA->fresh()->primary_color);
    }

    public function test_superadmin_without_clinic_redirects(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.clinic-profile'))
            ->assertRedirect(route('admin.clinics.index'));
    }

    public function test_superadmin_with_selected_clinic_can_view(): void
    {
        $this->actingAs($this->superAdmin)
            ->withSession(['current_clinic_id' => $this->clinicA->id])
            ->get(route('admin.clinic-profile'))
            ->assertOk()
            ->assertSee($this->clinicA->name);
    }
}
