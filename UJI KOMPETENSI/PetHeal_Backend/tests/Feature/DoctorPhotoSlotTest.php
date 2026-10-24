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
 * Slot foto (avatar) pada form dokter.
 *
 * Ikon placeholder lama memakai satu elemen sekaligus untuk ikon dan
 * container-nya (`flex h-full w-full items-center justify-center`). CSS
 * Material Symbols dimuat lewat <link> tanpa @layer dan SESUDAH app.css,
 * sehingga `display: inline-block` miliknya mengalahkan utility `flex`:
 * ikon tidak pernah dipusatkan (glif nangkring di pojok lingkaran) dan
 * utilitas `hidden` juga kalah — di form edit ikon tetap menumpuk di atas
 * foto yang baru diunggah.
 *
 * Test ini mengunci struktur yang mencegah masalah itu muncul lagi:
 * - elemen yang di-toggle JavaScript (#photo-placeholder) BUKAN elemen ikon,
 * - ikon berada di dalam wrapper terpisah yang memakai kelas positioning,
 * - aturan CSS hasil build untuk slot foto benar-benar berada di luar
 *   @layer (hanya begitu ia mengalahkan CSS Material Symbols).
 */
class DoctorPhotoSlotTest extends TestCase
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
            'name' => 'PhotoSlot ' . $suffix,
            'slug' => 'photo-slot-' . $suffix,
            'address' => 'Jl. Photo Slot',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'photoslotadmin' . $suffix,
            'email' => 'photoslotadmin' . $suffix . '@photoslot.test',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinic->id,
        ]);

        $this->doctor = Doctor::create([
            'name' => 'Dr Photo ' . $suffix,
            'specialization' => 'Umum',
            'is_active' => true,
            'clinic_id' => $this->clinic->id,
            'available_days' => ['monday', 'wednesday'],
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
    }

    /**
     * Ambil tag pembuka elemen dengan id tertentu.
     */
    private function tagWithId(string $html, string $id): string
    {
        preg_match('/<[a-z]+[^>]*id="' . preg_quote($id, '/') . '"[^>]*>/', $html, $matches);

        return $matches[0] ?? '';
    }

    /**
     * Isi file CSS hasil build (null kalau belum ada build).
     */
    private function builtCss(): ?string
    {
        $manifestPath = public_path('build/manifest.json');

        if (! file_exists($manifestPath)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $file = $manifest['resources/css/app.css']['file'] ?? null;

        if (! $file) {
            return null;
        }

        $cssPath = public_path('build/' . $file);

        return file_exists($cssPath) ? (string) file_get_contents($cssPath) : null;
    }

    /**
     * Offset akhir blok @layer terakhir pada CSS (layer utilities, components, ...).
     */
    private function lastLayerBlockEnd(string $css): int
    {
        $end = -1;

        if (preg_match_all('/@layer ([a-z-]+)\{/', $css, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $depth = 0;
                $length = strlen($css);

                for ($i = $match[1]; $i < $length; $i++) {
                    if ($css[$i] === '{') {
                        $depth++;
                    } elseif ($css[$i] === '}') {
                        $depth--;

                        if ($depth === 0) {
                            $end = max($end, $i);
                            break;
                        }
                    }
                }
            }
        }

        return $end;
    }

    public function test_create_form_renders_a_separate_placeholder_wrapper(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/doctors/create');

        $response->assertOk();

        $html = $response->getContent();

        // Slot foto memakai kelas positioning, bukan utility sebaris.
        $this->assertStringContainsString('admin-photo-slot', $html);
        $this->assertStringContainsString('admin-photo-slot--dashed', $html);

        // Elemen yang di-toggle `hidden` oleh JavaScript tidak boleh sekaligus
        // menjadi elemen ikon: CSS Material Symbols menimpa `display` sehingga
        // utilitas `hidden` tidak bisa menyembunyikannya.
        $placeholderTag = $this->tagWithId($html, 'photo-placeholder');

        $this->assertNotSame('', $placeholderTag);
        $this->assertStringNotContainsString('material-symbols-outlined', $placeholderTag);
        $this->assertStringContainsString('admin-photo-slot__placeholder', $placeholderTag);

        // Ikon berada di dalam wrapper, bukan di luar/selainnya.
        $this->assertSame(
            1,
            preg_match(
                '/id="photo-placeholder"[^>]*class="admin-photo-slot__placeholder"\s*>\s*<span class="material-symbols-outlined"/',
                $html
            ),
            'Ikon placeholder harus berada di dalam wrapper #photo-placeholder.'
        );
    }

    public function test_edit_form_without_photo_still_renders_the_placeholder(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/doctors/' . $this->doctor->id . '/edit');

        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('admin-photo-slot', $html);
        $this->assertStringContainsString('id="photo-placeholder"', $html);
        $this->assertStringContainsString('admin-photo-slot__placeholder', $html);
    }

    public function test_edit_form_shows_the_stored_photo_instead_of_the_placeholder(): void
    {
        $this->doctor->forceFill([
            'photo' => 'https://cdn.petheal.test/photos/' . $this->doctor->id . '.jpg',
        ])->save();

        $response = $this->actingAs($this->admin)
            ->get('/admin/doctors/' . $this->doctor->id . '/edit');

        $response->assertOk();

        $html = $response->getContent();

        // Foto tampil, placeholder tidak dirender sama sekali.
        $this->assertStringContainsString('id="photo-preview"', $html);
        $this->assertStringNotContainsString('id="photo-placeholder"', $html);
        $this->assertStringContainsString('cdn.petheal.test/photos/', $html);
    }

    public function test_built_css_keeps_the_photo_slot_rules_outside_any_layer(): void
    {
        $css = $this->builtCss();

        if ($css === null) {
            $this->markTestSkipped('CSS belum di-build (jalankan npm run build).');
        }

        // Aturan ikon harus ada...
        $iconRule = strpos($css, '.admin-photo-slot .material-symbols-outlined');

        $this->assertNotFalse($iconRule, 'Aturan ikon slot foto tidak ada di CSS hasil build.');
        $this->assertStringContainsString('font-size:36px', $css);

        // ...dan harus berada DI LUAR @layer. CSS Material Symbols dimuat tanpa
        // layer, sehingga hanya aturan unlayered yang bisa mengalahkannya.
        $lastLayerEnd = $this->lastLayerBlockEnd($css);

        $this->assertGreaterThan(
            $lastLayerEnd,
            $iconRule,
            'Aturan ikon slot foto harus berada di luar @layer agar mengalahkan CSS Material Symbols.'
        );

        // Wrapper ikon boleh tetap di @layer components: utilitas `hidden`
        // berada di layer utilities dan harus tetap bisa menyembunyikannya.
        $wrapperRule = strpos($css, '.admin-photo-slot__placeholder');

        $this->assertNotFalse($wrapperRule);
        $this->assertLessThan(
            $lastLayerEnd,
            $wrapperRule,
            'Wrapper ikon sebaiknya tetap di @layer components agar `hidden` tetap menang.'
        );
    }
}
