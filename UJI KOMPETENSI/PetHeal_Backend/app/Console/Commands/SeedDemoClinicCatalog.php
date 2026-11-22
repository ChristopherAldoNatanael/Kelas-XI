<?php

namespace App\Console\Commands;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Isi setiap klinik dengan layanan & dokter contoh yang berbeda-beda.
 *
 * Aturan yang dipegang perintah ini:
 *  1. Nama layanan dan nama dokter **tidak boleh sama** di klinik mana pun
 *     (dicek terhadap seluruh isi database, bukan hanya klinik yang diproses).
 *  2. Semua nama harus masuk akal untuk klinik hewan — tidak ada nama manusia
 *     untuk layanan atau nama layanan untuk dokter.
 *  3. Data yang sudah ada TIDAK dihapus/diubah. Perintah hanya menambah sampai
 *     jumlah minimum per klinik terpenuhi, jadi aman dijalankan berulang kali.
 *
 * Pemakaian:
 *   php artisan petheal:demo-clinic-catalog --dry-run
 *   php artisan petheal:demo-clinic-catalog
 *   php artisan petheal:demo-clinic-catalog --clinic=happy-paws --min-services=10
 */
class SeedDemoClinicCatalog extends Command
{
    protected $signature = 'petheal:demo-clinic-catalog
        {--clinic= : Hanya proses satu klinik (id atau slug)}
        {--min-services=8 : Jumlah minimum layanan per klinik}
        {--min-doctors=4 : Jumlah minimum dokter per klinik}
        {--dry-run : Tampilkan rencana tanpa menulis ke database}';

    protected $description = 'Tambahkan layanan & dokter contoh yang unik untuk setiap klinik.';

    /** Spesies hewan yang dipakai sebagai variasi nama layanan. */
    private const ANIMALS = [
        'Kucing', 'Anjing', 'Kelinci', 'Hamster', 'Marmut', 'Burung',
        'Iguana', 'Sugar Glider', 'Kura-kura', 'Musang',
    ];

    /**
     * Cetak biru layanan per kategori.
     *
     * `variants` memetakan pola nama ke hewan yang MASUK AKAL untuk layanan itu;
     * `animals` adalah daftar cadangan bila polanya netral. Ini mencegah nama
     * layanan yang salah konteks (mis. "Vaksin FVRCP Anjing" — FVRCP khusus
     * kucing, atau grooming untuk reptil).
     *
     * @return array<int, array{category: string, variants: array<string, array<int, string>>, animals: array<int, string>, price: array{0: int, 1: int}, durations: array<int, int>}>
     */
    private function serviceBlueprints(): array
    {
        return [
            [
                'category' => 'vaksinasi',
                'variants' => [
                    'Vaksin Rabies {h}' => ['Kucing', 'Anjing', 'Kelinci', 'Musang'],
                    'Vaksin FVRCP {h}' => ['Kucing'],
                    'Vaksin FeLV {h}' => ['Kucing'],
                    'Vaksin Parvovirus {h}' => ['Anjing'],
                    'Vaksin DHPP {h}' => ['Anjing'],
                    'Vaksin Bordetella {h}' => ['Anjing'],
                    'Vaksin Myxomatosis {h}' => ['Kelinci'],
                    'Vaksin Tetelo {h}' => ['Burung'],
                ],
                'animals' => ['Kucing', 'Anjing'],
                'price' => [90_000, 260_000],
                'durations' => [20, 30, 45],
            ],
            [
                'category' => 'pemeriksaan-umum',
                'variants' => [
                    'Konsultasi Sehat {h}' => [],
                    'Pemeriksaan Umum {h}' => [],
                    'Konsultasi Pra-Vaksin {h}' => [],
                    'Konsultasi Pasca Operasi {h}' => [],
                    'Medical Check Up {h}' => [],
                    'Konsultasi Perilaku {h}' => ['Kucing', 'Anjing', 'Burung'],
                    'Konsultasi Pertumbuhan {h}' => [],
                ],
                'animals' => self::ANIMALS,
                'price' => [80_000, 200_000],
                'durations' => [15, 20, 30, 45],
            ],
            [
                'category' => 'operasi-bedah',
                'variants' => [
                    'Sterilisasi {h} Jantan' => ['Kucing', 'Anjing', 'Kelinci', 'Hamster', 'Marmut'],
                    'Sterilisasi {h} Betina' => ['Kucing', 'Anjing', 'Kelinci', 'Hamster', 'Marmut'],
                    'Operasi Caesar {h}' => ['Kucing', 'Anjing', 'Kelinci', 'Marmut'],
                    'Bedah Fraktur {h}' => ['Kucing', 'Anjing', 'Kelinci', 'Burung', 'Musang'],
                    'Operasi Batu Saluran Kemih {h}' => ['Kucing', 'Anjing'],
                    'Bedah Abses Kulit {h}' => ['Kucing', 'Anjing', 'Kelinci', 'Hamster'],
                    'Operasi Katarak {h}' => ['Kucing', 'Anjing'],
                ],
                'animals' => ['Kucing', 'Anjing'],
                'price' => [350_000, 1_500_000],
                'durations' => [60, 90, 120],
            ],
            [
                'category' => 'grooming',
                'variants' => [
                    'Grooming Lengkap {h}' => ['Kucing', 'Anjing', 'Kelinci', 'Marmut'],
                    'Mandi Anti Kutu {h}' => ['Kucing', 'Anjing', 'Kelinci'],
                    'Potong Kuku & Bersih Telinga {h}' => ['Kucing', 'Anjing', 'Kelinci', 'Marmut', 'Hamster'],
                    'Cukur Bulu {h}' => ['Kucing', 'Anjing', 'Kelinci', 'Marmut'],
                    'Grooming Kering {h}' => ['Kucing', 'Anjing'],
                    'Spa Bulu {h}' => ['Kucing', 'Anjing', 'Kelinci'],
                ],
                'animals' => ['Kucing', 'Anjing'],
                'price' => [60_000, 250_000],
                'durations' => [30, 45, 60, 90],
            ],
            [
                'category' => 'rawat-inap',
                'variants' => [
                    'Rawat Inap {h} 1 Hari' => [],
                    'Rawat Inap {h} 3 Hari' => [],
                    'Rawat Inap Observasi {h}' => [],
                    'Rawat Inap Isolasi {h}' => [],
                ],
                'animals' => ['Kucing', 'Anjing', 'Kelinci', 'Burung', 'Musang', 'Kura-kura'],
                'price' => [200_000, 600_000],
                'durations' => [1440, 4320],
            ],
            [
                'category' => 'pemeriksaan-gigi',
                'variants' => [
                    'Scaling Gigi {h}' => ['Kucing', 'Anjing'],
                    'Cabut Gigi Susu {h}' => ['Kucing', 'Anjing', 'Kelinci'],
                    'Pembersihan Karang Gigi {h}' => ['Kucing', 'Anjing'],
                    'Perawatan Gusi {h}' => ['Kucing', 'Anjing', 'Kelinci'],
                ],
                'animals' => ['Kucing', 'Anjing'],
                'price' => [150_000, 450_000],
                'durations' => [30, 45, 60],
            ],
            [
                'category' => 'laboratorium',
                'variants' => [
                    'Cek Darah Lengkap {h}' => [],
                    'Tes Jamur Kulit {h}' => [],
                    'Tes FIV/FeLV {h}' => ['Kucing'],
                    'Pemeriksaan Tinja {h}' => [],
                    'Tes Urin {h}' => [],
                    'Cek Hormon Tiroid {h}' => ['Kucing', 'Anjing'],
                ],
                'animals' => ['Kucing', 'Anjing', 'Kelinci', 'Hamster'],
                'price' => [100_000, 400_000],
                'durations' => [30, 45, 60],
            ],
            [
                'category' => 'radiologi',
                'variants' => [
                    'Rontgen Toraks {h}' => ['Kucing', 'Anjing', 'Burung', 'Kura-kura'],
                    'Rontgen Abdomen {h}' => ['Kucing', 'Anjing', 'Kelinci'],
                    'USG Reproduksi {h}' => ['Kucing', 'Anjing', 'Kelinci', 'Marmut'],
                    'USG Abdomen {h}' => ['Kucing', 'Anjing', 'Kelinci', 'Iguana'],
                ],
                'animals' => ['Kucing', 'Anjing'],
                'price' => [200_000, 550_000],
                'durations' => [30, 45, 60],
            ],
            [
                'category' => 'lainnya',
                'variants' => [
                    'Suntik Cacing {h}' => ['Kucing', 'Anjing', 'Kelinci', 'Hamster', 'Marmut'],
                    'Pemasangan Microchip {h}' => ['Kucing', 'Anjing', 'Kelinci', 'Kura-kura', 'Iguana'],
                    'Infus Vitamin {h}' => [],
                    'Pembersihan Kotoran Telinga {h}' => ['Kucing', 'Anjing', 'Kelinci'],
                    'Konsultasi Gizi {h}' => [],
                    'Pemotongan Kuku {h}' => ['Kucing', 'Anjing', 'Kelinci', 'Marmut', 'Iguana'],
                ],
                'animals' => ['Kucing', 'Anjing', 'Kelinci'],
                'price' => [50_000, 300_000],
                'durations' => [15, 20, 30, 45],
            ],
        ];
    }

    /** Nama depan dokter (fiktif, tidak dipakai akun nyata). */
    private const DOCTOR_FIRST_NAMES = [
        'Aditya', 'Bintang', 'Cahya', 'Damar', 'Eka', 'Fajar', 'Gita', 'Hendra',
        'Indah', 'Joko', 'Kartika', 'Lukman', 'Mega', 'Nanda', 'Oki', 'Putri',
        'Rizky', 'Sinta', 'Taufik', 'Umar', 'Vina', 'Wulan', 'Yoga', 'Zahra',
        'Arman', 'Bella', 'Candra', 'Dian', 'Erwin', 'Fitri', 'Galih', 'Hesti',
        'Iqbal', 'Jihan', 'Kemal', 'Laras', 'Maya', 'Nabil', 'Oscar', 'Rangga',
        'Suci', 'Tirta', 'Utami', 'Vera', 'Wahyu', 'Yusuf',
    ];

    private const DOCTOR_LAST_NAMES = [
        'Pratama', 'Wijaya', 'Saputra', 'Hidayat', 'Nugroho', 'Ramadhan', 'Kusuma',
        'Mahendra', 'Suryana', 'Anggraini', 'Halim', 'Prasetya', 'Firmansyah',
        'Adiputra', 'Natalia', 'Sihombing', 'Wibowo', 'Permata', 'Syahputra',
        'Kurniawan', 'Maharani', 'Sitorus', 'Pamungkas', 'Wardhana',
    ];

    /** Spesialisasi veteriner (bukan spesialisasi manusia). */
    private const DOCTOR_SPECIALIZATIONS = [
        'Dokter Hewan Umum', 'Bedah Hewan Kecil', 'Penyakit Dalam Hewan',
        'Dermatologi Veteriner', 'Kardiologi Veteriner', 'Dokter Hewan Eksotis',
        'Nutrisi & Gizi Hewan', 'Oftalmologi Veteriner', 'Ortopedi Veteriner',
        'Anestesiologi Veteriner', 'Reproduksi Veteriner', 'Radiologi Veteriner',
        'Gigi & Mulut Hewan', 'Perilaku Hewan',
    ];

    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    /** Jam praktik: [mulai, selesai]. */
    private const SHIFTS = [
        ['08:00:00', '15:00:00'],
        ['09:00:00', '17:00:00'],
        ['10:00:00', '18:00:00'],
        ['13:00:00', '20:00:00'],
        ['07:00:00', '14:00:00'],
    ];

    public function handle(): int
    {
        $minServices = max(0, (int) $this->option('min-services'));
        $minDoctors = max(0, (int) $this->option('min-doctors'));
        $dryRun = (bool) $this->option('dry-run');

        $clinics = $this->clinicsToProcess();
        if ($clinics->isEmpty()) {
            $this->error('Tidak ada klinik yang cocok dengan --clinic.');

            return self::FAILURE;
        }

        // Kunci keunikan diambil dari SELURUH isi database, bukan per klinik,
        // supaya tidak ada layanan/dokter bernama sama di klinik berbeda.
        $usedServiceNames = Service::query()->pluck('name')
            ->map(fn ($name) => $this->normalize($name))->flip()->all();
        $usedDoctorNames = Doctor::query()->pluck('name')
            ->map(fn ($name) => $this->normalize($name))->flip()->all();

        $categoryIds = ServiceCategory::query()->pluck('id', 'slug')->all();
        $rows = [];
        $summary = [];

        foreach ($clinics as $clinic) {
            $existingServices = Service::where('clinic_id', $clinic->id)->count();
            $existingDoctors = Doctor::where('clinic_id', $clinic->id)->count();

            $services = [];
            for ($i = $existingServices; $i < $minServices; $i++) {
                $service = $this->makeService($usedServiceNames, $categoryIds);
                if ($service === null) {
                    break;
                }
                $services[] = $service;
                $usedServiceNames[$this->normalize($service['name'])] = true;
            }

            $doctors = [];
            $clinicSpecializations = Doctor::where('clinic_id', $clinic->id)
                ->pluck('specialization')
                ->map(fn ($spec) => $this->normalize($spec))
                ->flip()->all();
            for ($i = $existingDoctors; $i < $minDoctors; $i++) {
                $doctor = $this->makeDoctor($usedDoctorNames, $clinicSpecializations);
                if ($doctor === null) {
                    break;
                }
                $doctors[] = $doctor;
                $usedDoctorNames[$this->normalize($doctor['name'])] = true;
                $clinicSpecializations[$this->normalize($doctor['specialization'])] = true;
            }

            $rows[] = [$clinic->id, $clinic->name, $existingServices . ' → ' . count($services) . ' baru', $existingDoctors . ' → ' . count($doctors) . ' baru'];
            $summary[] = [$clinic, $services, $doctors];
        }

        $this->table(['ID', 'Klinik', 'Layanan', 'Dokter'], $rows);

        if ($dryRun) {
            foreach ($summary as [$clinic, $services, $doctors]) {
                $this->line("<comment>{$clinic->name}</comment> (dry-run, tidak disimpan)");
                foreach ($services as $service) {
                    $this->line('  + layanan: ' . $service['name'] . ' — ' . $service['category']
                        . ' — Rp ' . number_format($service['price'], 0, ',', '.'));
                }
                foreach ($doctors as $doctor) {
                    $this->line('  + dokter : ' . $doctor['name'] . ' — ' . $doctor['specialization']
                        . ' — ' . implode(', ', $doctor['available_days']));
                }
            }
            $this->info('Dry-run selesai. Tidak ada data yang ditulis.');

            return self::SUCCESS;
        }

        foreach ($summary as [$clinic, $services, $doctors]) {
            if ($services === [] && $doctors === []) {
                continue;
            }

            DB::transaction(function () use ($clinic, $services, $doctors) {
                foreach ($services as $service) {
                    Service::create($service + ['clinic_id' => $clinic->id]);
                }
                foreach ($doctors as $doctor) {
                    Doctor::create($doctor + ['clinic_id' => $clinic->id]);
                }
            });
        }

        $addedServices = array_sum(array_map(fn ($row) => count($row[1]), $summary));
        $addedDoctors = array_sum(array_map(fn ($row) => count($row[2]), $summary));
        $this->info("Selesai: {$addedServices} layanan dan {$addedDoctors} dokter baru ditambahkan.");

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
     * Susun satu layanan acak yang namanya belum terpakai.
     *
     * @param  array<string, bool>  $used  nama ternormalisasi yang sudah dipakai
     * @param  array<string, int>   $categoryIds  slug kategori → id
     * @return array<string, mixed>|null
     */
    private function makeService(array $used, array $categoryIds): ?array
    {
        $blueprints = $this->serviceBlueprints();

        // Coba beberapa kali; kombinasi kategori × nama × hewan sangat banyak,
        // jadi kegagalan praktis hanya terjadi bila seluruhnya sudah terpakai.
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $blueprint = $blueprints[array_rand($blueprints)];
            $pattern = array_rand($blueprint['variants']);
            $allowedAnimals = $blueprint['variants'][$pattern] ?: $blueprint['animals'];
            $animal = $allowedAnimals[array_rand($allowedAnimals)];
            $name = str_replace('{h}', $animal, $pattern);

            if (isset($used[$this->normalize($name)])) {
                continue;
            }

            $min = $blueprint['price'][0];
            $max = $blueprint['price'][1];
            $price = random_int(intdiv($min, 5_000), intdiv($max, 5_000)) * 5_000;

            return [
                'name' => $name,
                'description' => $this->serviceDescription($name, $blueprint['category']),
                'price' => $price,
                'duration' => $blueprint['durations'][array_rand($blueprint['durations'])],
                'category' => $blueprint['category'],
                'service_category_id' => $categoryIds[$blueprint['category']] ?? null,
                'is_active' => true,
            ];
        }

        return null;
    }

    /**
     * Susun satu dokter acak yang namanya belum terpakai.
     *
     * @param  array<string, bool>  $used  nama dokter ternormalisasi yang sudah dipakai (seluruh database)
     * @param  array<string, bool>  $usedSpecializations  spesialisasi yang sudah ada di klinik ini
     * @return array<string, mixed>|null
     */
    private function makeDoctor(array $used, array $usedSpecializations): ?array
    {
        // Utamakan spesialisasi yang belum ada di klinik ini supaya satu klinik
        // tidak berisi empat dokter dengan keahlian identik.
        $freeSpecializations = array_values(array_filter(
            self::DOCTOR_SPECIALIZATIONS,
            fn ($spec) => ! isset($usedSpecializations[$this->normalize($spec)])
        ));
        $specializationPool = $freeSpecializations ?: self::DOCTOR_SPECIALIZATIONS;

        for ($attempt = 0; $attempt < 200; $attempt++) {
            $first = self::DOCTOR_FIRST_NAMES[array_rand(self::DOCTOR_FIRST_NAMES)];
            $last = self::DOCTOR_LAST_NAMES[array_rand(self::DOCTOR_LAST_NAMES)];
            $name = "Dr. {$first} {$last}";

            if (isset($used[$this->normalize($name)])) {
                continue;
            }

            $days = self::DAYS;
            shuffle($days);
            $days = array_slice($days, 0, random_int(2, 4));
            // Urutkan sesuai urutan hari kerja, bukan alfabet.
            usort($days, fn ($a, $b) => array_search($a, self::DAYS) <=> array_search($b, self::DAYS));

            $shift = self::SHIFTS[array_rand(self::SHIFTS)];
            $slug = Str::slug($name, '.');

            return [
                'name' => $name,
                'specialization' => $specializationPool[array_rand($specializationPool)],
                'phone' => '0812' . str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT),
                'email' => $this->uniqueDoctorEmail($slug),
                'available_days' => $days,
                'start_time' => $shift[0],
                'end_time' => $shift[1],
                'is_active' => true,
            ];
        }

        return null;
    }

    /** Email contoh yang tidak bentrok dengan akun mana pun. */
    private function uniqueDoctorEmail(string $slug): string
    {
        $base = $slug . '@demo.petheal.test';
        if (! Doctor::where('email', $base)->exists()) {
            return $base;
        }

        return $slug . '+' . random_int(10, 99_999) . '@demo.petheal.test';
    }

    private function serviceDescription(string $name, string $category): string
    {
        return match ($category) {
            'vaksinasi' => "{$name} untuk menjaga kekebalan hewan terhadap penyakit menular. Termasuk pemeriksaan singkat sebelum penyuntikan.",
            'pemeriksaan-umum' => "{$name}: pemeriksaan fisik menyeluruh oleh dokter hewan, termasuk penimbangan dan catatan kondisi hewan.",
            'operasi-bedah' => "{$name} dikerjakan dokter hewan dengan tindakan aseptik, termasuk monitoring pasca tindakan.",
            'grooming' => "{$name} untuk menjaga kebersihan bulu, kulit, dan kuku hewan.",
            'rawat-inap' => "{$name} dengan pengawasan dokter dan perawat, termasuk pemberian obat sesuai jadwal.",
            'pemeriksaan-gigi' => "{$name} untuk menjaga kesehatan gigi dan gusi hewan.",
            'laboratorium' => "{$name} dengan sampel diperiksa di laboratorium klinik; hasil dikirim ke pemilik hewan.",
            'radiologi' => "{$name} menggunakan alat pencitraan untuk melihat kondisi organ dalam hewan.",
            default => "{$name} ditangani langsung oleh dokter hewan klinik.",
        };
    }

    private function normalize(?string $value): string
    {
        return Str::lower(Str::squish((string) $value));
    }
}
