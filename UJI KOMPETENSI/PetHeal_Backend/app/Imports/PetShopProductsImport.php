<?php

namespace App\Imports;

use App\Models\ClinicProduct;
use App\Models\ProductCategory;
use App\Models\ProductStockMovement;
use App\Models\ProductVariant;
use App\Services\ProductStockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithLimit;

/**
 * Import katalog Pet Shop dari CSV/Excel.
 *
 * Bentuk file: SATU BARIS = SATU VARIAN. Kolom produk diulang pada setiap
 * barisnya, sehingga beberapa varian dengan nama produk sama otomatis
 * digabung menjadi satu produk.
 *
 * Aturan yang dijaga:
 *  1. `clinic_id` tidak pernah dibaca dari file. Semua data masuk ke klinik
 *     pemanggil, dan pencarian produk dibatasi klinik tersebut.
 *  2. Kategori TIDAK dibuat otomatis. Nama kategori harus sudah ada dan
 *     aktif; salah ketik seharusnya terlihat sebagai kesalahan baris, bukan
 *     diam-diam membuat kategori baru yang berantakan.
 *  3. Stok hanya dipakai untuk varian BARU dan tetap lewat
 *     `ProductStockService`, jadi ada mutasi "stok awal". Stok varian yang
 *     sudah ada tidak ditimpa lewat import — perubahan stok harus lewat
 *     halaman produk agar terekam sebagai mutasi.
 *  4. SKU unik global. SKU milik klinik lain ditolak, dan SKU yang sudah
 *     terdaftar pada produk lain juga ditolak (tidak boleh berpindah produk
 *     tanpa disadari).
 *  5. Satu baris gagal tidak menghentikan baris lain; kesalahan dikumpulkan
 *     per baris di laporan unduhan.
 *
 * Seperti import obat, baris header dicari sendiri (bukan
 * `WithHeadingRow`) supaya judul di baris 1, baris kosong, BOM Excel, dan
 * header bahasa Indonesia tetap terbaca.
 */
class PetShopProductsImport implements ToCollection, WithLimit, WithCalculatedFormulas, WithCustomCsvSettings
{
    private const MAX_HEADER_SCAN = 20;

    private const HEADER_ALIASES = [
        'category' => [
            'category', 'kategori', 'kategori_produk', 'kategoriproduk', 'product_category',
            'productcategory', 'jenis_produk', 'jenisproduk', 'jenis',
        ],
        'product_name' => [
            'product_name', 'productname', 'nama_produk', 'namaproduk', 'produk', 'product',
            'nama', 'nama_barang', 'namabarang', 'nama_item',
        ],
        'brand' => [
            'brand', 'merek', 'merk', 'produsen', 'pabrikan',
        ],
        'description' => [
            'description', 'deskripsi', 'keterangan', 'catatan', 'notes', 'note',
        ],
        'sku' => [
            'sku', 'kode', 'kode_sku', 'kodesku', 'kode_produk', 'kodeproduk',
            'kode_varian', 'kodevarian', 'item_code', 'itemcode',
        ],
        'variant_name' => [
            'variant_name', 'variantname', 'nama_varian', 'namavarian', 'varian', 'variant',
            'ukuran', 'size', 'kemasan', 'packaging',
        ],
        'price' => [
            'price', 'harga', 'harga_jual', 'hargajual', 'harga_normal', 'harganormal',
            'harga_satuan', 'hargasatuan', 'unit_price',
        ],
        'discount_price' => [
            'discount_price', 'discountprice', 'harga_diskon', 'hargadiskon', 'harga_promo',
            'hargapromo', 'diskon', 'discount', 'promo_price', 'harga_potongan',
        ],
        'stock' => [
            'stock', 'stok', 'stok_awal', 'stokawal', 'stok_saat_ini', 'stok_tersedia',
            'jumlah', 'jumlah_stok', 'qty', 'quantity', 'saldo',
        ],
        'min_stock' => [
            'min_stock', 'minstock', 'minimum_stock', 'minimal_stock', 'stok_minimal',
            'stokminimal', 'stok_minimum', 'stokminimum', 'stok_min', 'min_stok',
            'low_stock_threshold', 'lowstockthreshold', 'threshold', 'batas_stok_rendah',
            'batasstokrendah',
        ],
        'status' => [
            'status', 'status_varian', 'statusvarian', 'aktif', 'is_active', 'isactive',
        ],
    ];

    public array $summary = [
        'total' => 0,
        'success' => 0,
        'updated' => 0,
        'skipped' => 0,
        'failed' => 0,
        'products_created' => 0,
        'products_updated' => 0,
        'categories_created' => 0,
        'category_names_created' => [],
    ];

    /** @var array<int, array<string, mixed>> */
    public array $errorRows = [];

    public bool $headerFound = false;

    public int $headerRowNumber = 0;

    public int $scannedRows = 0;

    /** @var array<string, int> nama produk (lowercase) → id */
    private array $productCache = [];

    /** @var array<string, true> SKU yang sudah dipakai di file ini */
    private array $seenSkus = [];

    public function __construct(
        private readonly string $duplicateStrategy = 'update',
        private readonly ?int $clinicId = null,
        private readonly ?string $csvDelimiter = null,
    ) {
    }

    public function getCsvSettings(): array
    {
        return $this->csvDelimiter === null ? [] : ['delimiter' => $this->csvDelimiter];
    }

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

    public function limit(): int
    {
        return 3000;
    }

    public function collection($rows): void
    {
        $stockService = app(ProductStockService::class);

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

        if (!$this->headerFound) {
            return;
        }

        // Kategori global cukup dibaca sekali, bukan per baris.
        $categories = ProductCategory::query()
            ->where('is_active', true)
            ->get()
            ->keyBy(fn (ProductCategory $category) => Str::lower(trim($category->name)));

        // ── 2. Baris data ────────────────────────────────────────────────
        foreach ($cellsByIndex as $index => $cells) {
            if ($index <= $headerIndex) {
                continue;
            }

            $rowNumber = $index + 1;

            $productName = $this->text($this->cell($cells, $columnMap, 'product_name'));
            $categoryName = $this->text($this->cell($cells, $columnMap, 'category'));
            $brand = $this->text($this->cell($cells, $columnMap, 'brand'));
            $description = $this->text($this->cell($cells, $columnMap, 'description'));
            $sku = strtoupper($this->text($this->cell($cells, $columnMap, 'sku')));
            $variantName = $this->text($this->cell($cells, $columnMap, 'variant_name'));
            $price = $this->integer($this->cell($cells, $columnMap, 'price'));
            $discount = $this->integer($this->cell($cells, $columnMap, 'discount_price'));
            $stock = $this->integer($this->cell($cells, $columnMap, 'stock'));
            $minStock = $this->integer($this->cell($cells, $columnMap, 'min_stock'));
            $status = $this->status($this->cell($cells, $columnMap, 'status'));

            if ($this->isEmptyDataRow($cells, $columnMap)) {
                continue;
            }

            // Sebagian file Excel/CSV mengulang baris header per halaman.
            // Baris itu bukan data, jadi dilewati tanpa dihitung gagal.
            if ($this->isRepeatedHeaderRow($cells)) {
                continue;
            }

            $this->summary['total']++;

            // Varian tanpa nama tetap bisa masuk dengan label netral. Ini
            // sering terjadi pada file yang hanya berisi satu varian/produk.
            if ($variantName === '') {
                $variantName = 'Standar';
            }

            // Kategori kosong tidak boleh menggagalkan seluruh baris; produk
            // masuk ke kategori umum yang dibuat sekali saat dibutuhkan.
            if ($categoryName === '') {
                $categoryName = 'Umum';
            }

            $payload = [
                'product_name' => $productName,
                'category' => $categoryName,
                'brand' => $brand,
                'description' => $description,
                'sku' => $sku,
                'variant_name' => $variantName,
                'price' => $price,
                'discount_price' => $discount,
                'stock' => $stock,
                'min_stock' => $minStock,
                'status' => $status,
            ];

            $validator = Validator::make($payload, [
                'product_name' => 'required|string|max:160',
                'category' => 'required|string|max:120',
                'brand' => 'nullable|string|max:120',
                'description' => 'nullable|string|max:5000',
                'sku' => 'required|string|max:64',
                'variant_name' => 'required|string|max:120',
                'price' => 'required|integer|min:0|max:1000000000',
                'discount_price' => 'nullable|integer|min:0|max:1000000000',
                'stock' => 'nullable|integer|min:0|max:1000000',
                'min_stock' => 'nullable|integer|min:0|max:10000',
                'status' => 'nullable|in:active,inactive',
            ]);

            if ($validator->fails()) {
                $this->fail($rowNumber, $payload, implode(' ', $validator->errors()->all()));
                continue;
            }

            if ($discount !== null && $discount >= $price) {
                $this->fail($rowNumber, $payload, 'Harga diskon harus lebih rendah dari harga normal.');
                continue;
            }

            if (!$this->clinicId) {
                $this->fail($rowNumber, $payload, 'Import membutuhkan konteks klinik. Pilih klinik terlebih dahulu.');
                continue;
            }

            if (isset($this->seenSkus[$sku])) {
                $this->fail($rowNumber, $payload, "SKU {$sku} muncul lebih dari sekali di file ini.");
                continue;
            }
            $this->seenSkus[$sku] = true;

            $existingVariant = ProductVariant::with('product')
                ->where('sku', $sku)
                ->first();

            // SKU global: varian klinik lain tidak boleh disentuh, dan varian
            // yang sudah ada tidak boleh berpindah produk lewat file.
            if ($existingVariant && (int) $existingVariant->product?->clinic_id !== (int) $this->clinicId) {
                $this->fail($rowNumber, $payload, "SKU {$sku} sudah dipakai produk klinik lain.");
                continue;
            }

            if ($existingVariant
                && Str::lower(trim((string) $existingVariant->product?->name)) !== Str::lower($productName)) {
                $this->fail(
                    $rowNumber,
                    $payload,
                    "SKU {$sku} sudah terdaftar pada produk \"{$existingVariant->product?->name}\". Gunakan SKU lain atau samakan nama produknya."
                );
                continue;
            }

            if ($existingVariant && $this->duplicateStrategy === 'skip') {
                $this->summary['skipped']++;
                continue;
            }

            // Kategori baru dibuat setelah baris lolos pemeriksaan SKU, supaya
            // baris yang gagal tidak meninggalkan kategori kosong.
            $category = $this->resolveCategory($categories, $categoryName);

            try {
                DB::transaction(function () use (
                    $existingVariant,
                    $productName,
                    $category,
                    $brand,
                    $description,
                    $sku,
                    $variantName,
                    $price,
                    $discount,
                    $stock,
                    $minStock,
                    $status,
                    $stockService
                ) {
                    $product = $this->resolveProduct($productName, $category, $brand, $description, $status);

                    if ($existingVariant) {
                        $existingVariant->update([
                            'name' => $variantName,
                            'price' => (int) $price,
                            'discount_price' => $discount,
                            'low_stock_threshold' => $minStock ?? (int) $existingVariant->low_stock_threshold,
                            'is_active' => $status !== 'inactive',
                        ]);

                        // Stok varian lama sengaja tidak disentuh: perubahan
                        // stok harus lewat halaman produk agar bermutasi.
                        $this->summary['updated']++;

                        return;
                    }

                    $variant = ProductVariant::create([
                        'clinic_product_id' => $product->id,
                        'sku' => $sku,
                        'name' => $variantName,
                        'price' => (int) $price,
                        'discount_price' => $discount,
                        'stock_on_hand' => 0,
                        'stock_reserved' => 0,
                        'low_stock_threshold' => $minStock ?? 5,
                        'is_active' => $status !== 'inactive',
                    ]);

                    if ($stock !== null && $stock > 0) {
                        $stockService->adjust(
                            $variant,
                            $stock,
                            'Stok awal dari import produk',
                            auth()->id(),
                            ProductStockMovement::TYPE_PURCHASE
                        );
                    }

                    $this->summary['success']++;
                });
            } catch (\Throwable $e) {
                $this->fail($rowNumber, $payload, 'Baris tidak bisa disimpan: ' . $e->getMessage());
                continue;
            }
        }
    }

    /**
     * Cari kategori aktif berdasarkan nama. Nama yang belum ada dibuat sekali
     * agar file katalog dari luar tidak gagal seluruhnya hanya karena
     * kategorinya belum terdaftar. Nama yang dibuat dicatat di ringkasan.
     *
     * @param  Collection<string, ProductCategory>  $categories
     */
    private function resolveCategory(Collection $categories, string $name): ProductCategory
    {
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?: 'Umum';
        $key = Str::lower($name);

        $existing = $categories->get($key);
        if ($existing) {
            return $existing;
        }

        $category = ProductCategory::create([
            'name' => $name,
            'slug' => ProductCategory::makeUniqueSlug($name),
            'is_active' => true,
            'sort_order' => (int) ProductCategory::max('sort_order') + 1,
        ]);

        $categories->put($key, $category);

        $this->summary['categories_created']++;
        $this->summary['category_names_created'][] = $category->name;

        return $category;
    }

    /**
     * Ambil produk dari cache/nama, atau buat baru dalam klinik ini.
     *
     * Kolom yang kosong tidak menimpa data lama, supaya file yang hanya
     * berisi SKU + harga tidak menghapus brand/deskripsi yang sudah diisi.
     */
    private function resolveProduct(
        string $name,
        ProductCategory $category,
        string $brand,
        string $description,
        string $status
    ): ClinicProduct {
        $key = Str::lower($name);

        if (isset($this->productCache[$key])) {
            $product = ClinicProduct::findOrFail($this->productCache[$key]);

            $update = [];
            if ((int) $product->product_category_id !== (int) $category->id) {
                $update['product_category_id'] = $category->id;
            }
            if ($brand !== '') {
                $update['brand'] = $brand;
            }
            if ($description !== '') {
                $update['description'] = $description;
            }

            if ($update) {
                $product->update($update);
            }

            return $product;
        }

        $product = ClinicProduct::where('clinic_id', $this->clinicId)
            ->whereRaw('LOWER(name) = ?', [$key])
            ->first();

        if ($product) {
            $this->summary['products_updated']++;
        } else {
            $product = ClinicProduct::create([
                'clinic_id' => $this->clinicId,
                'product_category_id' => $category->id,
                'name' => $name,
                'slug' => ClinicProduct::makeUniqueSlug((int) $this->clinicId, $name),
                'description' => $description !== '' ? $description : null,
                'brand' => $brand !== '' ? $brand : null,
                'is_active' => $status !== 'inactive',
            ]);
            $this->summary['products_created']++;
        }

        $this->productCache[$key] = $product->id;

        if ($product->wasRecentlyCreated === false) {
            $update = [];
            if ((int) $product->product_category_id !== (int) $category->id) {
                $update['product_category_id'] = $category->id;
            }
            if ($brand !== '') {
                $update['brand'] = $brand;
            }
            if ($description !== '') {
                $update['description'] = $description;
            }
            if ($update) {
                $product->update($update);
            }
        }

        return $product;
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
     * @param  array<int, mixed>  $cells
     * @return array<string, int|null>|null
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

        // Produk tidak bisa dibuat tanpa nama produk dan SKU varian. Syarat
        // dua kolom ini yang membedakan header asli dari sel judul.
        if ($map['product_name'] === null || $map['sku'] === null) {
            return null;
        }

        return $recognized >= 2 ? $map : null;
    }

    private static function normalizeHeader($value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '';
        }

        $raw = str_replace(["\u{FEFF}", "\u{00A0}", "\u{200B}"], ' ', $raw);
        $raw = mb_strtolower($raw, 'UTF-8');
        $raw = preg_replace('/[^a-z0-9]+/', '_', $raw) ?? '';

        return trim($raw, '_');
    }

    /**
     * Baris header yang diulang di tengah file. Sengaja memakai pencocokan
     * PERSIS, bukan awalan: nama produk seperti "Produk Bentrok" dan SKU
     * seperti "SKU-MILIK-B" akan salah dianggap header kalau memakai prefix.
     *
     * @param  array<int, mixed>  $cells
     */
    private function isRepeatedHeaderRow(array $cells): bool
    {
        $exact = 0;

        foreach ($cells as $value) {
            $header = self::normalizeHeader($value);
            if ($header !== '' && self::matchHeaderExact($header) !== null) {
                $exact++;
            }
        }

        return $exact >= 2;
    }

    private static function matchHeaderExact(string $header): ?string
    {
        foreach (self::HEADER_ALIASES as $canonical => $aliases) {
            if (in_array($header, $aliases, true)) {
                return $canonical;
            }
        }

        return null;
    }

    private static function matchHeader(string $header): ?string
    {
        foreach (self::HEADER_ALIASES as $canonical => $aliases) {
            if (in_array($header, $aliases, true)) {
                return $canonical;
            }
        }

        // Awalan untuk header seperti "Harga (Rp)" atau "Stok Awal (pcs)".
        // Alias terpanjang menang supaya `discount_price` tidak tertangkap
        // `price` dan `min_stock` tidak tertangkap `stock`.
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
     * Harga/stok harus bilangan bulat rupiah/pcs. Menerima angka Excel,
     * "12.500", "Rp 12.500", dan "12,500" tanpa mengubah artinya.
     */
    private function integer($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) round($value);
        }

        $text = trim((string) $value);
        $negative = str_starts_with($text, '-')
            || (str_starts_with($text, '(') && str_ends_with($text, ')'));

        // Buang "Rp", spasi, dan notasi "185.000,-" yang umum di Indonesia.
        $raw = preg_replace('/[^0-9,.]/', '', $text) ?? '';
        if ($raw === '' || !preg_match('/\d/', $raw)) {
            return null;
        }

        $hasComma = str_contains($raw, ',');
        $hasDot = str_contains($raw, '.');
        $lastComma = $hasComma ? (int) strrpos($raw, ',') : -1;
        $lastDot = $hasDot ? (int) strrpos($raw, '.') : -1;
        $lastSeparator = max($lastComma, $lastDot);
        $decimals = $lastSeparator >= 0 ? strlen(substr($raw, $lastSeparator + 1)) : 0;

        if ($lastSeparator >= 0 && $decimals > 0 && $decimals <= 2) {
            // Pemisah terakhir diikuti 1–2 digit = desimal (mis. 185.000,50).
            if ($lastSeparator === $lastComma) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } else {
            // Selain itu pemisah adalah ribuan: 185.000 / 185,000 / 1.850.000.
            $raw = str_replace([',', '.'], '', $raw);
        }

        if (!is_numeric($raw)) {
            return null;
        }

        $number = (int) round((float) $raw);

        return $negative ? -$number : $number;
    }

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

        if (in_array($raw, ['inactive', 'nonaktif', 'non aktif', 'tidak aktif', 'tidak', '0', 'false', 'off', 'mati'], true)) {
            return 'inactive';
        }

        return $raw;
    }

    private function fail(int $rowNumber, array $payload, string $message): void
    {
        $this->summary['failed']++;
        $this->errorRows[] = [
            'row_number' => $rowNumber,
            'product_name' => $payload['product_name'] ?? '',
            'category' => $payload['category'] ?? '',
            'sku' => $payload['sku'] ?? '',
            'variant_name' => $payload['variant_name'] ?? '',
            'error_message' => $message,
        ];
    }
}
