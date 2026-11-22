<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Service;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `petheal:demo-clinic-catalog` mengisi layanan & dokter contoh per klinik.
 *
 * Yang dijaga test ini:
 *  - setiap klinik mencapai jumlah minimum layanan/dokter,
 *  - NAMA layanan dan NAMA dokter unik di seluruh database (bukan hanya per
 *    klinik), sesuai permintaan "jangan ada yang sama tiap klinik",
 *  - semua data masuk akal untuk klinik hewan (dokter berawalan "Dr." dengan
 *    spesialisasi veteriner; layanan punya kategori resmi + harga + durasi),
 *  - perintah aman dijalankan berulang (idempoten, tidak menambah terus).
 */
class DemoClinicCatalogCommandTest extends TestCase
{
    use DatabaseTransactions;

    private function makeClinic(string $suffix, string $label): Clinic
    {
        return Clinic::create([
            'name' => 'Demo ' . $label . ' ' . $suffix,
            'slug' => 'demo-' . $label . '-' . $suffix,
            'address' => 'Jl. Demo',
            'is_active' => true,
        ]);
    }

    public function test_it_fills_each_clinic_with_unique_animal_services_and_doctors(): void
    {
        $suffix = strtolower(Str::random(6));
        $clinicA = $this->makeClinic($suffix, 'a');
        $clinicB = $this->makeClinic($suffix, 'b');

        foreach ([$clinicA, $clinicB] as $clinic) {
            $this->artisan('petheal:demo-clinic-catalog', [
                '--clinic' => $clinic->slug,
                '--min-services' => 6,
                '--min-doctors' => 3,
            ])->assertExitCode(0);
        }

        // Jumlah minimum terpenuhi.
        foreach ([$clinicA, $clinicB] as $clinic) {
            $this->assertGreaterThanOrEqual(6, Service::where('clinic_id', $clinic->id)->count());
            $this->assertGreaterThanOrEqual(3, Doctor::where('clinic_id', $clinic->id)->count());
        }

        $this->assertSame(0, Service::where('clinic_id', $clinicA->id)->where('is_active', false)->count());
        $this->assertSame(0, Doctor::where('clinic_id', $clinicA->id)->where('is_active', false)->count());

        // Nama layanan & dokter unik di SELURUH database (bukan hanya per klinik).
        $serviceNames = Service::pluck('name')->map(fn ($n) => Str::lower(trim($n)));
        $this->assertSame($serviceNames->count(), $serviceNames->unique()->count(), 'Ada nama layanan yang sama di dua klinik.');

        $doctorNames = Doctor::pluck('name')->map(fn ($n) => Str::lower(trim($n)));
        $this->assertSame($doctorNames->count(), $doctorNames->unique()->count(), 'Ada nama dokter yang sama di dua klinik.');

        // Layanan: kategori resmi terisi, harga wajar, durasi positif.
        $categorySlugs = ['vaksinasi', 'pemeriksaan-umum', 'operasi-bedah', 'grooming', 'rawat-inap', 'pemeriksaan-gigi', 'laboratorium', 'radiologi', 'lainnya'];
        foreach (Service::whereIn('clinic_id', [$clinicA->id, $clinicB->id])->get() as $service) {
            $this->assertContains($service->category, $categorySlugs, "Kategori layanan \"{$service->name}\" tidak dikenal.");
            $this->assertNotNull($service->service_category_id, "Layanan \"{$service->name}\" tidak punya service_category_id.");
            $this->assertGreaterThan(0, (float) $service->price);
            $this->assertGreaterThan(0, (int) $service->duration);
            $this->assertNotSame('', trim((string) $service->description));
        }

        // Dokter: nama berspesialisasi veteriner + jadwal praktik terisi.
        foreach (Doctor::whereIn('clinic_id', [$clinicA->id, $clinicB->id])->get() as $doctor) {
            $this->assertStringStartsWith('Dr. ', $doctor->name, "Nama dokter \"{$doctor->name}\" tidak berformat dokter.");
            $this->assertNotEmpty($doctor->specialization);
            $this->assertNotEmpty($doctor->available_days);
            $this->assertNotEmpty($doctor->start_time);
            $this->assertNotEmpty($doctor->end_time);
            $this->assertStringEndsWith('@demo.petheal.test', (string) $doctor->email);
        }
    }

    public function test_running_twice_does_not_duplicate_data(): void
    {
        $suffix = strtolower(Str::random(6));
        $clinic = $this->makeClinic($suffix, 'idem');

        $options = [
            '--clinic' => $clinic->slug,
            '--min-services' => 4,
            '--min-doctors' => 2,
        ];

        $this->artisan('petheal:demo-clinic-catalog', $options)->assertExitCode(0);
        $servicesAfterFirst = Service::where('clinic_id', $clinic->id)->count();
        $doctorsAfterFirst = Doctor::where('clinic_id', $clinic->id)->count();

        $this->artisan('petheal:demo-clinic-catalog', $options)->assertExitCode(0);

        $this->assertSame($servicesAfterFirst, Service::where('clinic_id', $clinic->id)->count());
        $this->assertSame($doctorsAfterFirst, Doctor::where('clinic_id', $clinic->id)->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $suffix = strtolower(Str::random(6));
        $clinic = $this->makeClinic($suffix, 'dry');

        $this->artisan('petheal:demo-clinic-catalog', [
            '--clinic' => $clinic->slug,
            '--min-services' => 5,
            '--min-doctors' => 3,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, Service::where('clinic_id', $clinic->id)->count());
        $this->assertSame(0, Doctor::where('clinic_id', $clinic->id)->count());
    }
}
