<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pemilih lokasi klinik untuk admin non-teknis (2026-11-09).
 *
 * Yang dibuktikan:
 *  1. form menampilkan pemilih peta + tombol "Buka peta" (bukan dua kolom
 *     angka yang wajib diisi),
 *  2. koordinat tetap tersimpan dari form (sumber data nyata, tanpa dummy),
 *  3. koordinat setengah ditolak dengan pesan yang bisa ditindaklanjuti,
 *  4. koordinat bisa dikosongkan lagi.
 */
class ClinicLocationPickerTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = strtolower(Str::random(6));
        $admin = User::create([
            'name' => 'Admin Lokasi',
            'email' => "admin-loc-$suffix@petheal.test",
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => null,
        ]);

        $this->clinic = Clinic::create([
            'name' => 'Klinik Lokasi ' . $suffix,
            'slug' => 'klinik-lokasi-' . $suffix,
            'address' => 'Jl. Uji No. 1',
            'primary_color' => '#18C964',
            'is_active' => true,
        ]);

        $admin->update(['clinic_id' => $this->clinic->id]);
        $this->actingAs($admin->fresh());
    }

    public function test_clinic_profile_shows_map_picker_instead_of_raw_coordinates()
    {
        $response = $this->get('/admin/settings?tab=klinik')->assertOk();

        // Kontrol utama untuk admin non-teknis.
        $response->assertSee('data-pin-location', false);
        $response->assertSee('data-open-map', false);
        $response->assertSee('data-map', false);
        // Koordinat tetap ada sebagai opsi lanjutan, bukan langkah wajib.
        $response->assertSee('data-lat', false);
        $response->assertSee('data-lng', false);
    }

    public function test_admin_can_save_coordinates_from_the_picker()
    {
        $this->put('/admin/settings/clinic', [
            'name' => $this->clinic->name,
            'address' => 'Jl. Uji No. 1',
            'primary_color' => '#18C964',
            'latitude' => '-6.2088000',
            'longitude' => '106.8456000',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->clinic->refresh();
        $this->assertEquals(-6.2088, (float) $this->clinic->latitude);
        $this->assertEquals(106.8456, (float) $this->clinic->longitude);
    }

    public function test_half_coordinates_are_rejected_with_actionable_message()
    {
        $response = $this->put('/admin/settings/clinic', [
            'name' => $this->clinic->name,
            'address' => 'Jl. Uji No. 1',
            'primary_color' => '#18C964',
            'latitude' => '-6.2088000',
        ]);

        $response->assertSessionHasErrors(['longitude']);
        $this->assertNull($this->clinic->fresh()->latitude);

        // Pesannya menyuruh admin melakukan sesuatu, bukan istilah teknis.
        $message = session('errors')->first('longitude');
        $this->assertStringContainsString('keduanya', $message);
    }

    public function test_coordinates_can_be_cleared_again()
    {
        $this->clinic->update(['latitude' => -6.2, 'longitude' => 106.8]);

        $this->put('/admin/settings/clinic', [
            'name' => $this->clinic->name,
            'address' => 'Jl. Uji No. 1',
            'primary_color' => '#18C964',
            'latitude' => '',
            'longitude' => '',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->clinic->refresh();
        $this->assertNull($this->clinic->latitude);
        $this->assertNull($this->clinic->longitude);
    }
}
