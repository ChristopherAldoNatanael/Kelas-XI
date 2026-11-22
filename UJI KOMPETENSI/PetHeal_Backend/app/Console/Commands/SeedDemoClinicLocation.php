<?php

namespace App\Console\Commands;

use App\Models\Clinic;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Isi koordinat contoh untuk klinik yang belum punya `latitude`/`longitude`.
 *
 * Kenapa perlu: peta & pencarian "klinik terdekat" memakai Haversine
 * (`App\Services\ClinicDiscoveryService`). Klinik tanpa koordinat tidak pernah
 * muncul di sana dan posisinya tidak bisa ditampilkan di peta.
 *
 * Aturan:
 *  1. Titik diambil dari pusat kota yang disebut di `clinics.address`, lalu
 *     digeser acak ±1,3 km supaya tidak menumpuk tepat di satu titik.
 *  2. Klinik yang SUDAH punya koordinat tidak disentuh (kecuali `--force`).
 *  3. Alamat yang kotanya tidak dikenali DILEWATI dan dilaporkan — lebih baik
 *     dibiarkan kosong daripada ditaruh di kota yang salah.
 *
 * Pemakaian:
 *   php artisan petheal:demo-clinic-location --dry-run
 *   php artisan petheal:demo-clinic-location
 *   php artisan petheal:demo-clinic-location --clinic=happy-paws --force
 */
class SeedDemoClinicLocation extends Command
{
    protected $signature = 'petheal:demo-clinic-location
        {--clinic= : Hanya satu klinik (id atau slug)}
        {--force : Timpa koordinat yang sudah ada}
        {--dry-run : Tampilkan rencana tanpa menulis ke database}';

    protected $description = 'Isi koordinat contoh (perkiraan pusat kota dari alamat) untuk klinik yang belum punya lokasi.';

    /**
     * Pusat kota + kata kunci untuk mengenalinya dari alamat klinik.
     *
     * Koordinat ini titik tengah kota (bukan alamat presisi): tujuannya supaya
     * klinik muncul di peta & pencarian "terdekat" pada kota yang benar.
     *
     * @var array<string, array{label: string, lat: float, lng: float, keys: array<int, string>}>
     */
    private const CITY_CENTERS = [
        'jakarta' => ['label' => 'Jakarta', 'lat' => -6.2000, 'lng' => 106.8166, 'keys' => ['jakarta', 'jakarta pusat', 'jakarta selatan', 'jakarta barat', 'jakarta timur', 'jakarta utara']],
        'bandung' => ['label' => 'Bandung', 'lat' => -6.9175, 'lng' => 107.6191, 'keys' => ['bandung', 'coblong', 'dago']],
        'surabaya' => ['label' => 'Surabaya', 'lat' => -7.2575, 'lng' => 112.7521, 'keys' => ['surabaya', 'gubeng', 'darmo', 'rungkut', 'wonokromo']],
        'yogyakarta' => ['label' => 'Yogyakarta', 'lat' => -7.7956, 'lng' => 110.3695, 'keys' => ['yogyakarta', 'jogja', 'sleman', 'bantul']],
        'semarang' => ['label' => 'Semarang', 'lat' => -6.9932, 'lng' => 110.4203, 'keys' => ['semarang']],
        'malang' => ['label' => 'Malang', 'lat' => -7.9666, 'lng' => 112.6326, 'keys' => ['malang']],
        'solo' => ['label' => 'Surakarta', 'lat' => -7.5755, 'lng' => 110.8243, 'keys' => ['surakarta', 'solo']],
        'medan' => ['label' => 'Medan', 'lat' => 3.5952, 'lng' => 98.6722, 'keys' => ['medan']],
        'makassar' => ['label' => 'Makassar', 'lat' => -5.1477, 'lng' => 119.4327, 'keys' => ['makassar', 'ujung pandang']],
        'denpasar' => ['label' => 'Denpasar', 'lat' => -8.6705, 'lng' => 115.2126, 'keys' => ['denpasar', 'badung', 'kuta']],
        'bekasi' => ['label' => 'Bekasi', 'lat' => -6.2383, 'lng' => 106.9756, 'keys' => ['bekasi']],
        'depok' => ['label' => 'Depok', 'lat' => -6.4025, 'lng' => 106.7942, 'keys' => ['depok']],
        'tangerang' => ['label' => 'Tangerang', 'lat' => -6.1781, 'lng' => 106.6319, 'keys' => ['tangerang', 'serpong', 'bsd']],
        'bogor' => ['label' => 'Bogor', 'lat' => -6.5950, 'lng' => 106.8166, 'keys' => ['bogor']],
        'palembang' => ['label' => 'Palembang', 'lat' => -2.9761, 'lng' => 104.7754, 'keys' => ['palembang']],
        'balikpapan' => ['label' => 'Balikpapan', 'lat' => -1.2379, 'lng' => 116.8529, 'keys' => ['balikpapan']],
        'samarinda' => ['label' => 'Samarinda', 'lat' => -0.4948, 'lng' => 117.1436, 'keys' => ['samarinda']],
        'banjarmasin' => ['label' => 'Banjarmasin', 'lat' => -3.3186, 'lng' => 114.5944, 'keys' => ['banjarmasin']],
        'manado' => ['label' => 'Manado', 'lat' => 1.4748, 'lng' => 124.8421, 'keys' => ['manado']],
        'padang' => ['label' => 'Padang', 'lat' => -0.9471, 'lng' => 100.4172, 'keys' => ['padang']],
    ];

    /** Geseran maksimum dari pusat kota (±0,012 derajat ≈ ±1,3 km). */
    private const JITTER = 0.012;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $clinics = $this->clinicsToProcess();
        if ($clinics->isEmpty()) {
            $this->error('Tidak ada klinik yang cocok dengan --clinic.');

            return self::FAILURE;
        }

        $rows = [];
        $updates = [];
        $skipped = [];

        foreach ($clinics as $clinic) {
            $hasCoordinates = $clinic->latitude !== null && $clinic->longitude !== null;

            if ($hasCoordinates && ! $force) {
                $rows[] = [$clinic->id, $clinic->name, 'sudah ada', $clinic->latitude . ', ' . $clinic->longitude];

                continue;
            }

            $city = $this->detectCity($clinic->address . ' ' . $clinic->city . ' ' . $clinic->district);
            if ($city === null) {
                $skipped[] = $clinic;
                $rows[] = [$clinic->id, $clinic->name, 'dilewati', 'kota tidak dikenali dari alamat'];

                continue;
            }

            $lat = $this->jitter($city['lat']);
            $lng = $this->jitter($city['lng']);

            $updates[] = [$clinic, $lat, $lng, $city['label']];
            $rows[] = [$clinic->id, $clinic->name, $city['label'], number_format($lat, 6, '.', '') . ', ' . number_format($lng, 6, '.', '')];
        }

        $this->table(['ID', 'Klinik', 'Kota', 'Koordinat'], $rows);

        if ($skipped !== []) {
            $this->warn('Dilewati (tambahkan nama kota ke kolom address, lalu jalankan ulang):');
            foreach ($skipped as $clinic) {
                $this->line('  - #' . $clinic->id . ' ' . $clinic->name . ' — alamat: ' . ($clinic->address ?: '(kosong)'));
            }
        }

        if ($updates === []) {
            $this->info('Tidak ada klinik yang perlu diisi.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('Dry-run: ' . count($updates) . ' klinik akan diisi. Tidak ada data yang ditulis.');

            return self::SUCCESS;
        }

        foreach ($updates as [$clinic, $lat, $lng, $cityLabel]) {
            $clinic->forceFill([
                'latitude' => round($lat, 7),
                'longitude' => round($lng, 7),
            ])->save();

            $this->line("Ditulis: #{$clinic->id} {$clinic->name} → {$cityLabel} (" . round($lat, 6) . ', ' . round($lng, 6) . ')');
        }

        $this->info('Selesai: ' . count($updates) . ' klinik diberi koordinat contoh.');

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, Clinic> */
    private function clinicsToProcess()
    {
        $filter = trim((string) $this->option('clinic'));

        return Clinic::query()
            ->when($filter !== '', function ($query) use ($filter) {
                $query->where(function ($q) use ($filter) {
                    $q->where('slug', $filter);
                    if (ctype_digit($filter)) {
                        $q->orWhere('id', (int) $filter);
                    }
                });
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Tebak kota dari teks alamat.
     *
     * @return array{label: string, lat: float, lng: float, keys: array<int, string>}|null
     */
    private function detectCity(string $address): ?array
    {
        $haystack = ' ' . Str::lower(Str::squish($address)) . ' ';

        foreach (self::CITY_CENTERS as $city) {
            foreach ($city['keys'] as $key) {
                if (str_contains($haystack, ' ' . $key . ' ') || str_contains($haystack, ' ' . $key . ',') || str_contains($haystack, ',' . $key . ' ')) {
                    return $city;
                }
            }
        }

        return null;
    }

    /** Geser koordinat acak di sekitar pusat kota. */
    private function jitter(float $center): float
    {
        return $center + (random_int(-1000, 1000) / 1000) * self::JITTER;
    }
}
