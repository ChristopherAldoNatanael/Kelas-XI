<?php

namespace App\Imports;

use App\Models\Medicine;
use App\Services\MedicineStockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithLimit;

/**
 * Import master obat dari CSV/Excel.
 *
 * Aturan yang dijaga:
 *  1. Semua baris masuk ke klinik pemanggil. `clinic_id` tidak pernah dibaca
 *     dari file — lookup dibatasi `clinic_id`, jadi file yang menyebut obat
 *     klinik lain tidak bisa menimpanya.
 *  2. Stok TIDAK ditulis langsung. Kolom `stock` hanya berarti "stok awal"
 *     untuk obat BARU dan tetap lewat `MedicineStockService`, sehingga buku
 *     mutasi punya baris pembuka. Untuk obat yang sudah ada, kolom `stock`
 *     diabaikan: menimpanya akan membuat saldo melompat tanpa mutasi, persis
 *     yang dicegah pada form manual.
 *  3. Satu baris gagal tidak menghentikan baris lain. Kesalahan dikumpulkan
 *     per baris agar admin bisa memperbaiki file, bukan menebak.
 *  4. Tanpa konteks klinik import ditolak (fail-closed), sama seperti layanan.
 *  5. Baris header dicari sendiri, tidak diasumsikan di baris 1.
 *
 * Kenapa header dicari sendiri (bukan `WithHeadingRow`):
 * `WithHeadingRow` hanya membaca baris pertama dan mencocokkan nama kolom
 * persis setelah di-slug. File nyata sering punya judul di baris 1, baris
 * kosong di atas header, header bahasa Indonesia ("Nama Obat"), header
 * berspasi, atau BOM dari Excel. Semua varian itu dulu berakhir sebagai
 * "0 baris terbaca" padahal isinya benar. Di sini header dipindai sampai
 * 20 baris pertama, dinormalkan (BOM/NBSP/spasi/kapital → snake_case) dan
 * dicocokkan ke alias Indonesia + Inggris.
 */
class MedicinesImport implements ToCollection, WithLimit, WithCalculatedFormulas, WithCustomCsvSettings
{
    /** Batas baris yang dipindai untuk mencari baris header. */
    private const MAX_HEADER_SCAN = 20;

    /**
     * Alias header yang diterima per kolom kanonik.
     *
     * Urutan alias tidak penting; pencocokan persis didahulukan, lalu
     * pencocokan awalan dengan alias terpanjang (supaya `min_stock` tidak
     * tertangkap `stock`, dan `harga_rp` tetap terbaca sebagai `price`).
     */
    private const HEADER_ALIASES = [
        'name' => [
            'name', 'nama', 'nama_obat', 'namaobat', 'nama_barang', 'namabarang',
            'nama_item', 'nama_produk', 'obat', 'medicine', 'medicine_name',
        ],
        'unit' => [
            'unit', 'satuan', 'satuan_obat', 'satuanobat', 'unit_satuan', 'uom',
            'kemasan', 'packaging',
        ],
        'form' => [
            'form', 'bentuk', 'bentuk_obat', 'bentukobat', 'bentuk_sediaan', 'sediaan',
            'jenis', 'jenis_obat', 'jenisobat', 'tipe', 'type', 'dosage_form',
        ],
        'stock' => [
            'stock', 'stok', 'stok_awal', 'stokawal', 'stok_saat_ini', 'stok_tersedia',
            'jumlah', 'jumlah_stok', 'qty', 'quantity', 'saldo',
        ],
        'min_stock' => [
            'min_stock', 'minimum_stock', 'minimal_stock', 'stok_minimal', 'stokminimal',
            'stok_minimum', 'stokminimum', 'stok_min', 'min_stok', 'batas_minimum',
            'batasminimal', 'minimum', 'reorder_point',
        ],
        'price' => [
            'price', 'harga', 'harga_satuan', 'hargasatuan', 'harga_jual', 'hargajual',
            'harga_obat', 'hargaobat', 'unit_price', 'hrg',
        ],
        'expiry_date' => [
            'expiry_date', 'expiry', 'exp_date', 'expired', 'expired_date', 'kadaluwarsa',
            'kedaluwarsa', 'tanggal_kadaluwarsa', 'tanggalkadaluwarsa',
            'tanggal_kedaluwarsa', 'tanggalkedaluwarsa', 'tgl_kadaluwarsa',
            'tglkedaluwarsa', 'tgl_kedaluwarsa', 'tglkedaluwarsa', 'tanggal_expired',
            'tgl_expired', 'tanggal_exp', 'tgl_exp',
        ],
        'manufacturer' => [
            'manufacturer', 'produsen', 'pabrik', 'manufaktur', 'merek', 'brand',
            'distributor', 'principal',
        ],
        'status' => [
            'status', 'status_obat', 'aktif', 'is_active', 'keadaan',
        ],
        'notes' => [
            'notes', 'note', 'catatan', 'keterangan', 'remark', 'remarks', 'deskripsi',
            'description',
        ],
    ];

    public array $summary = [
        'total' => 0,
        'success' => 0,
        'updated' => 0,
        'skipped' => 0,
        'failed' => 0,
    ];

    /** @var array<int, array<string, mixed>> */
    public array $errorRows = [];

    /** Apakah baris header berhasil dikenali. */
    public bool $headerFound = false;

    /** Nomor baris (1-based) tempat header ditemukan; 0 bila tidak ada. */
    public int $headerRowNumber = 0;

    /** Berapa baris teratas yang diperiksa saat mencari header. */
    public int $scannedRows = 0;

    public function __construct(
        private readonly string $duplicateStrategy = 'update',
        private readonly ?int $clinicId = null,
        private readonly ?string $csvDelimiter = null,
    ) {
    }

    /**
     * CSV: pakai delimiter hasil deteksi controller.
     *
     * Tanpa ini, PhpSpreadsheet menebak sendiri dari beberapa baris pertama.
     * Kalau file punya judul berspasi di atas header ("Daftar Obat Klinik"),
     * tebakannya bisa jatuh ke spasi sehingga seluruh header terbaca sebagai
     * satu sel. Deteksi dari controller mencari baris header dulu, baru
     * menghitung pemisahnya.
     */
    public function getCsvSettings(): array
    {
        return $this->csvDelimiter === null ? [] : ['delimiter' => $this->csvDelimiter];
    }

    /**
     * Tebak delimiter CSV dengan melihat baris header, bukan baris pertama.
     *
     * @return string|null null bila tidak ada baris yang menyerupai header
     *                     (biar pembaca memakai deteksi bawaannya).
     */
    public static function guessCsvDelimiter(string $contents): ?string
    {
        if ($contents === '') {
            return null;
        }

        $lines = preg_split('/\r\n|\n|\r/', $contents) ?: [];
        $lines = array_slice($lines, 0, self::MAX_HEADER_SCAN);

        $best = null;
        $bestScore = 0;

        foreach ([',', ';', "\t", '|'] as $delimiter) {
            $score = 0;

            foreach ($lines as $line) {
                if ($line === '') {
                    continue;
                }

                $recognized = 0;
                foreach (str_getcsv($line, $delimiter, '"', '\\') as $cell) {
                    if (self::matchHeader(self::normalizeHeader($cell)) !== null) {
                        $recognized++;
                    }
                }

                // Minimal dua kolom dikenal: satu sel judul seperti
                // "Daftar Obat" tidak boleh memenangkan pemisah spasi.
                if ($recognized >= 2) {
                    $score += $recognized;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $delimiter;
            }
        }

        return $bestScore >= 2 ? $best : null;
    }

    /** Batas baris per unggahan: melindungi request dari file raksasa. */
    public function limit(): int
    {
        return 2000;
    }

    public function collection(Collection $rows): void
    {
        $stockService = app(MedicineStockService::class);

        // Salinan baris dalam bentuk array 0-based. `toArray()` dipakai karena
        // tanpa `WithHeadingRow` tiap baris masih berupa Row object.
        $cellsByIndex = [];
        foreach ($rows as $index => $row) {
            $cellsByIndex[$index] = $this->rowCells($row);
        }

        // ── 1. Cari baris header ─────────────────────────────────────────
        $columnMap = [];
        $headerIndex = -1;
        foreach ($cellsByIndex as $index => $cells) {
            if ($index >= self::MAX_HEADER_SCAN) {
                break;
            }
            $this->scannedRows = $index + 1;

            $map = $this->mapHeader($cells);
            if ($map !== null) {
                $columnMap = $map;
                $headerIndex = $index;
                $this->headerFound = true;
                $this->headerRowNumber = $index + 1;
                break;
            }
        }

        // Header tidak dikenali: berhenti tanpa menormalkan baris data apa pun.
        // Controller membedakan "header tidak ada" dan "header ada tapi kosong".
        if (!$this->headerFound) {
            return;
        }

        // ── 2. Proses baris data di bawah header ─────────────────────────
        foreach ($cellsByIndex as $index => $cells) {
            if ($index <= $headerIndex) {
                continue;
            }

            $rowNumber = $index + 1;

            $name = $this->text($this->cell($cells, $columnMap, 'name'));
            $unit = $this->text($this->cell($cells, $columnMap, 'unit'));
            $form = $this->text($this->cell($cells, $columnMap, 'form'));
            $manufacturer = $this->text($this->cell($cells, $columnMap, 'manufacturer'));
            $notes = $this->text($this->cell($cells, $columnMap, 'notes'));
            $price = $this->number($this->cell($cells, $columnMap, 'price'));
            $minStock = $this->number($this->cell($cells, $columnMap, 'min_stock'));
            $openingStock = $this->number($this->cell($cells, $columnMap, 'stock'));
            $expiry = $this->date($this->cell($cells, $columnMap, 'expiry_date'));
            $status = $this->status($this->cell($cells, $columnMap, 'status'));

            // Baris kosong (sisa format Excel) dilewati tanpa mengotori
            // ringkasan maupun laporan kesalahan. Yang diperiksa hanya sel
            // yang benar-benar dipetakan, supaya catatan liar di kolom lain
            // tidak membuat baris kosong terhitung gagal.
            if ($this->isEmptyDataRow($cells, $columnMap)) {
                continue;
            }

            $this->summary['total']++;

            $payload = [
                'name' => $name,
                'unit' => $unit,
                'form' => $form,
                'price' => $price,
                'min_stock' => $minStock,
                'expiry_date' => $expiry,
                'manufacturer' => $manufacturer,
                'notes' => $notes,
                'status' => $status,
            ];

            $validator = Validator::make($payload, [
                'name' => 'required|string|max:255',
                // `unit` hanya wajib untuk obat BARU. Untuk obat yang sudah
                // ada, sel kosong berarti "jangan ubah", dan mewajibkannya di
                // sini membuat baris update harga selalu gagal.
                'unit' => 'nullable|string|max:30',
                'form' => 'nullable|string|max:50',
                'price' => 'nullable|numeric|min:0',
                'min_stock' => 'nullable|numeric|min:0',
                'expiry_date' => 'nullable|date',
                'manufacturer' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:2000',
                'status' => 'nullable|in:active,inactive',
            ]);

            if ($validator->fails()) {
                $this->summary['failed']++;
                $this->pushErrorRow($rowNumber, $payload, implode(' ', $validator->errors()->all()));
                continue;
            }

            if (!$this->clinicId) {
                $this->summary['failed']++;
                $this->pushErrorRow($rowNumber, $payload, 'Import membutuhkan konteks klinik. Pilih klinik terlebih dahulu.');
                continue;
            }

            $existing = Medicine::where('clinic_id', $this->clinicId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();

            if ($existing && $this->duplicateStrategy === 'skip') {
                $this->summary['skipped']++;
                continue;
            }

            // Obat baru butuh satuan; tanpa satuan, stok tidak bisa dibaca
            // ("120" tanpa "tablet"). Diperiksa di sini, bukan di validasi
            // umum, supaya baris update tetap boleh mengosongkan sel unit.
            if (!$existing && $unit === '') {
                $this->summary['failed']++;
                $this->pushErrorRow($rowNumber, $payload, 'Kolom unit wajib diisi untuk obat baru.');
                continue;
            }

            if ($existing) {
                // Hanya kolom yang benar-benar terisi yang menimpa data lama,
                // supaya sel kosong di file tidak menghapus isi yang sudah ada.
                $update = [
                    'name' => $name,
                    'is_active' => $status !== 'inactive',
                ];
                foreach ([
                    'unit' => $unit,
                    'form' => $form,
                    'manufacturer' => $manufacturer,
                    'notes' => $notes,
                ] as $field => $value) {
                    if ($value !== '') {
                        $update[$field] = $value;
                    }
                }
                if ($price !== null) {
                    $update['price'] = $price;
                }
                if ($minStock !== null) {
                    $update['min_stock'] = $minStock;
                }
                if ($expiry !== null) {
                    $update['expiry_date'] = $expiry;
                }

                $existing->update($update);
                $this->summary['updated']++;
                continue;
            }

            try {
                $medicine = Medicine::create([
                    'clinic_id' => $this->clinicId,
                    'name' => $name,
                    'unit' => $unit,
                    'form' => $form ?: null,
                    'min_stock' => $minStock ?? 0,
                    'price' => $price ?? 0,
                    'expiry_date' => $expiry,
                    'manufacturer' => $manufacturer ?: null,
                    'notes' => $notes ?: null,
                    'is_active' => $status !== 'inactive',
                ]);

                // Stok awal tetap lewat service: tercatat sebagai mutasi masuk.
                if ($openingStock !== null && $openingStock > 0) {
                    $stockService->setOpeningStock(
                        $medicine,
                        $openingStock,
                        'Stok awal dari import obat'
                    );
                }
            } catch (\Throwable $e) {
                $this->summary['failed']++;
                $this->pushErrorRow(
                    $rowNumber,
                    $payload,
                    'Baris tidak bisa disimpan: ' . $e->getMessage()
                );
                continue;
            }

            $this->summary['success']++;
        }
    }

    /** @return array<int, mixed> */
    private function rowCells($row): array
    {
        if (is_array($row)) {
            return array_values($row);
        }

        if (is_object($row) && method_exists($row, 'toArray')) {
            return array_values((array) $row->toArray());
        }

        return array_values((array) $row);
    }

    /**
     * Cocokkan satu baris dengan header yang dikenal.
     *
     * @param  array<int, mixed>  $cells
     * @return array<string, int>|null  Peta kolom kanonik → indeks sel, atau
     *                                  null bila baris ini bukan header.
     */
    private function mapHeader(array $cells): ?array
    {
        $map = array_fill_keys(array_keys(self::HEADER_ALIASES), null);
        $recognized = 0;

        foreach ($cells as $cellIndex => $value) {
            $header = self::normalizeHeader($value);
            if ($header === '') {
                continue;
            }

            $canonical = self::matchHeader($header);
            if ($canonical === null || $map[$canonical] !== null) {
                continue;
            }

            $map[$canonical] = $cellIndex;
            $recognized++;
        }

        // Tanpa kolom `name`, baris ini bukan header tabel obat. Ini yang
        // mencegah judul seperti "Daftar Obat Klinik" dianggap header.
        if ($map['name'] === null) {
            return null;
        }

        // Minimal dua kolom dikenal, bukan satu. Tanpa syarat ini, sel judul
        // seperti "Nama Obat" di baris 1 akan dianggap header, lalu baris
        // header asli di bawahnya ikut dibaca sebagai data dan membuat obat
        // bernama "name". Kolom selain `name` semuanya opsional.
        return $recognized >= 2 ? $map : null;
    }

    /** Normalisasi teks header: buang BOM/NBSP, kapital, dan tanda baca. */
    private static function normalizeHeader($value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '';
        }

        $raw = str_replace(["\u{FEFF}", "\u{00A0}", "\u{200B}"], ' ', $raw);
        $raw = mb_strtolower($raw, 'UTF-8');

        // "Kadaluarsa" adalah ejaan yang sangat umum di file klinik (termasuk
        // template lama). Samakan dulu supaya satu alias cukup.
        $raw = str_replace('kadaluarsa', 'kedaluwarsa', $raw);

        $raw = preg_replace('/[^a-z0-9]+/', '_', $raw) ?? '';

        return trim($raw, '_');
    }

    /** @return string|null Kolom kanonik yang cocok, atau null. */
    private static function matchHeader(string $header): ?string
    {
        // 1. Cocok persis: kasus paling umum dan paling aman.
        foreach (self::HEADER_ALIASES as $canonical => $aliases) {
            if (in_array($header, $aliases, true)) {
                return $canonical;
            }
        }

        // 2. Cocok awalan untuk header seperti "Harga (Rp)" → harga_rp,
        //    "Stok Awal (tablet)" → stok_awal_tablet, atau "Nama Obat *".
        //    Alias terpanjang menang supaya "min_stock" tidak dibaca "stock".
        $best = null;
        $bestLength = 0;

        foreach (self::HEADER_ALIASES as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                $length = strlen($alias);
                if ($length > $bestLength && str_starts_with($header, $alias)) {
                    $best = $canonical;
                    $bestLength = $length;
                }
            }
        }

        return $best;
    }

    /**
     * @param  array<int, mixed>  $cells
     * @param  array<string, int|null>  $columnMap
     */
    private function isEmptyDataRow(array $cells, array $columnMap): bool
    {
        foreach ($columnMap as $cellIndex) {
            if ($cellIndex === null) {
                continue;
            }
            if ($this->text($cells[$cellIndex] ?? null) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $cells
     * @param  array<string, int|null>  $columnMap
     */
    private function cell(array $cells, array $columnMap, string $column)
    {
        $cellIndex = $columnMap[$column] ?? null;

        return $cellIndex === null ? null : ($cells[$cellIndex] ?? null);
    }

    private function text($value): string
    {
        return trim((string) ($value ?? ''));
    }

    /**
     * Angka boleh datang sebagai angka asli Excel, "12.500" (ribuan
     * Indonesia), "12,5", atau "Rp 12.500". Semua dinormalkan ke float.
     */
    private function number($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $raw = preg_replace('/[^0-9,.\-]/', '', trim((string) $value)) ?? '';
        if ($raw === '' || $raw === '-') {
            return null;
        }

        $hasComma = str_contains($raw, ',');
        $hasDot = str_contains($raw, '.');
        $lastSeparator = max(
            $hasComma ? strrpos($raw, ',') : -1,
            $hasDot ? strrpos($raw, '.') : -1
        );
        $decimals = $lastSeparator >= 0 ? strlen(substr($raw, $lastSeparator + 1)) : 0;

        if ($hasComma && $hasDot) {
            // Keduanya muncul: pemisah TERAKHIR adalah desimal.
            if ($lastSeparator === strrpos($raw, ',')) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($decimals === 3) {
            // "12.500" / "12,500" berarti dua belas ribu lima ratus.
            $raw = str_replace([',', '.'], '', $raw);
        } elseif ($hasComma) {
            $raw = str_replace(',', '.', $raw);
        }

        return is_numeric($raw) ? (float) $raw : null;
    }

    /**
     * Tanggal kedaluwarsa: menerima 2026-12-31, 31/12/2026, 31-12-2026,
     * dan serial number Excel.
     */
    private function date($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value > 20000) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)
                    ->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y', 'd-m-y', 'Y/m/d'] as $format) {
            try {
                $parsed = Carbon::createFromFormat('!' . $format, $raw);
                if ($parsed && $parsed->format('Y-m-d') === $parsed->toDateString()) {
                    return $parsed->toDateString();
                }
            } catch (\Throwable) {
                // coba format berikutnya
            }
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Status dari file boleh bahasa Indonesia. Nilai tak dikenal tetap
     * dikembalikan apa adanya supaya validator memberi pesan yang jelas,
     * bukan diam-diam dianggap aktif.
     */
    private function status($value): string
    {
        $raw = strtolower($this->text($value));
        $raw = trim(preg_replace('/[^a-z0-9]+/', ' ', $raw) ?? '');

        if ($raw === '') {
            return 'active';
        }

        if (in_array($raw, ['active', 'aktif', 'aktip', 'ya', 'yes', '1', 'true', 'on'], true)) {
            return 'active';
        }

        if (in_array($raw, ['inactive', 'nonaktif', 'non aktif', 'tidak aktif', 'tidak', 'tidak ada', '0', 'false', 'off', 'mati'], true)) {
            return 'inactive';
        }

        return $raw;
    }

    private function pushErrorRow(int $rowNumber, array $payload, string $errorMessage): void
    {
        $this->errorRows[] = [
            'row_number' => $rowNumber,
            'name' => $payload['name'] ?? '',
            'unit' => $payload['unit'] ?? '',
            'stock' => '',
            'min_stock' => $payload['min_stock'] ?? '',
            'price' => $payload['price'] ?? '',
            'expiry_date' => $payload['expiry_date'] ?? '',
            'error_message' => $errorMessage,
        ];
    }
}
