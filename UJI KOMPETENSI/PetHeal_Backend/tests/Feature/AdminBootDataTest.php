<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Penjaga kontrak data JavaScript konsol admin (UIX-19).
 *
 * Layout menulis satu blok JSON `#admin-boot`. Kalau blok itu hilang, rusak,
 * atau kehilangan label/URL, laci sidebar, tema, toast, dan command palette
 * akan diam tanpa error di layar. Uji ini memastikan kontraknya tetap utuh.
 */
class AdminBootDataTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_boot_json_parses_with_flashes_labels_and_routes(): void
    {
        $suffix = strtolower(Str::random(6));

        $clinic = Clinic::create([
            'name' => 'Boot ' . $suffix,
            'slug' => 'boot-' . $suffix,
            'address' => 'Jl. Boot',
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'bootadmin' . $suffix,
            'email' => 'bootadmin' . $suffix . '@boot.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $clinic->id,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['app_locale' => 'id', 'success' => 'Data tersimpan.'])
            ->get('/admin');

        $response->assertOk();

        $html = $response->getContent();
        $this->assertSame(
            1,
            preg_match('#<script type="application/json" id="admin-boot">(.*?)</script>#s', $html, $matches),
            'Blok admin-boot tidak ditemukan di layout admin.'
        );

        $data = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
        $this->assertIsArray($data, 'admin-boot harus JSON valid');

        $this->assertSame('Data tersimpan.', $data['flashes'][0]['message']);
        $this->assertSame('success', $data['flashes'][0]['type']);

        foreach (['search', 'board', 'bookings', 'payments'] as $route) {
            $this->assertNotEmpty($data['routes'][$route] ?? null, "Route {$route} hilang dari admin-boot.");
        }

        foreach ([
            'close_notification',
            'command_quick_actions',
            'command_type_booking',
            'command_type_pet',
            'command_type_owner',
            'command_search_failed_title',
        ] as $key) {
            $this->assertNotEmpty($data['labels'][$key] ?? null, "Label {$key} hilang dari admin-boot.");
        }
    }

    /**
     * Paritas katalog portal dokter (UIX-14).
     *
     * Kunci yang hanya ada di satu bahasa akan tampil sebagai teks kosong
     * ketika pengguna berganti bahasa, dan itu tidak terlihat sebagai error.
     */
    public function test_doctor_catalog_has_identical_keys_in_both_locales(): void
    {
        $id = require resource_path('lang/id/doctor.php');
        $en = require resource_path('lang/en/doctor.php');

        $this->assertIsArray($id);
        $this->assertIsArray($en);
        $this->assertGreaterThan(150, count($id), 'Katalog dokter tampak terpotong.');

        $this->assertSame([], array_values(array_diff(array_keys($id), array_keys($en))), 'Kunci ada di id tetapi tidak di en.');
        $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($id))), 'Kunci ada di en tetapi tidak di id.');

        foreach ($id as $key => $value) {
            $this->assertNotSame('', trim((string) $value), "Nilai id kosong untuk kunci {$key}.");
            $this->assertNotSame('', trim((string) $en[$key]), "Nilai en kosong untuk kunci {$key}.");
        }
    }
}
