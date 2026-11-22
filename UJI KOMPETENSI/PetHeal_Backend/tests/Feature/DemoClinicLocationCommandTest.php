<?php

namespace Tests\Feature;

use App\Models\Clinic;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `petheal:demo-clinic-location` mengisi koordinat klinik yang belum punya lokasi.
 *
 * Yang dijaga:
 *  - hanya klinik tanpa koordinat yang diisi (yang sudah ada tidak ditimpa),
 *  - titik berada di sekitar kota yang disebut di alamat (bukan kota lain),
 *  - alamat tanpa kota yang dikenali dilewati, tidak ditaruh sembarangan,
 *  - `--dry-run` tidak menulis, dan perintah aman diulang.
 */
class DemoClinicLocationCommandTest extends TestCase
{
    use DatabaseTransactions;

    private function makeClinic(string $suffix, string $label, string $address, ?float $lat = null, ?float $lng = null): Clinic
    {
        return Clinic::create([
            'name' => 'Lokasi ' . $label . ' ' . $suffix,
            'slug' => 'lokasi-' . $label . '-' . $suffix,
            'address' => $address,
            'latitude' => $lat,
            'longitude' => $lng,
            'is_active' => true,
        ]);
    }

    public function test_missing_coordinates_are_filled_near_the_city_in_the_address(): void
    {
        $suffix = strtolower(Str::random(6));
        $bandung = $this->makeClinic($suffix, 'bdg', 'Jl. Dago No. 45, Coblong, Bandung, Jawa Barat 40135');
        $surabaya = $this->makeClinic($suffix, 'sby', 'Jl. Pemuda No. 88, Gubeng, Surabaya, Jawa Timur 60271');

        $this->artisan('petheal:demo-clinic-location', ['--clinic' => $bandung->slug])->assertExitCode(0);
        $this->artisan('petheal:demo-clinic-location', ['--clinic' => $surabaya->slug])->assertExitCode(0);

        $bandung->refresh();
        $surabaya->refresh();

        $this->assertNotNull($bandung->latitude);
        $this->assertNotNull($bandung->longitude);
        // ±0,012 derajat geseran + toleransi pembulatan.
        $this->assertEqualsWithDelta(-6.9175, (float) $bandung->latitude, 0.05);
        $this->assertEqualsWithDelta(107.6191, (float) $bandung->longitude, 0.05);

        $this->assertEqualsWithDelta(-7.2575, (float) $surabaya->latitude, 0.05);
        $this->assertEqualsWithDelta(112.7521, (float) $surabaya->longitude, 0.05);

        // Titik setiap klinik tidak menumpuk persis di pusat kota.
        $this->assertNotSame(-6.9175, (float) $bandung->latitude);
    }

    public function test_clinic_with_existing_coordinates_is_untouched_without_force(): void
    {
        $suffix = strtolower(Str::random(6));
        $clinic = $this->makeClinic($suffix, 'punya', 'Jl. Dago No. 1, Bandung', -6.9000000, 107.6000000);

        $this->artisan('petheal:demo-clinic-location', ['--clinic' => $clinic->slug])->assertExitCode(0);

        $clinic->refresh();
        $this->assertSame('-6.9000000', (string) $clinic->latitude);
        $this->assertSame('107.6000000', (string) $clinic->longitude);
    }

    public function test_force_overwrites_existing_coordinates(): void
    {
        $suffix = strtolower(Str::random(6));
        $clinic = $this->makeClinic($suffix, 'force', 'Jl. Pemuda No. 2, Surabaya', -1.0, 100.0);

        $this->artisan('petheal:demo-clinic-location', [
            '--clinic' => $clinic->slug,
            '--force' => true,
        ])->assertExitCode(0);

        $clinic->refresh();
        $this->assertEqualsWithDelta(-7.2575, (float) $clinic->latitude, 0.05);
        $this->assertEqualsWithDelta(112.7521, (float) $clinic->longitude, 0.05);
    }

    public function test_unknown_city_is_skipped_instead_of_guessed(): void
    {
        $suffix = strtolower(Str::random(6));
        $clinic = $this->makeClinic($suffix, 'asing', 'Jl. Tanpa Kota Yang Dikenal KM 5');

        $this->artisan('petheal:demo-clinic-location', ['--clinic' => $clinic->slug])->assertExitCode(0);

        $clinic->refresh();
        $this->assertNull($clinic->latitude);
        $this->assertNull($clinic->longitude);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $suffix = strtolower(Str::random(6));
        $clinic = $this->makeClinic($suffix, 'dry', 'Jl. Dago No. 3, Bandung');

        $this->artisan('petheal:demo-clinic-location', [
            '--clinic' => $clinic->slug,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $clinic->refresh();
        $this->assertNull($clinic->latitude);
        $this->assertNull($clinic->longitude);
    }
}
