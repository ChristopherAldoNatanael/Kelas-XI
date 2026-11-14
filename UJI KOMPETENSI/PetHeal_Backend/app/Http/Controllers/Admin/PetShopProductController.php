<?php

namespace App\Http\Controllers\Admin;

use App\Exports\PetShopProductsImportErrorReportExport;
use App\Exports\PetShopProductsTemplateExport;
use App\Http\Controllers\Admin\Concerns\ResolvesPetShopScope;
use App\Http\Controllers\Controller;
use App\Imports\PetShopProductsImport;
use App\Models\AuditLog;
use App\Models\ClinicProduct;
use App\Models\ProductCategory;
use App\Models\ProductOrder;
use App\Models\ProductStockMovement;
use App\Models\ProductVariant;
use App\Services\ImageService;
use App\Services\ProductStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class PetShopProductController extends Controller
{
    use ResolvesPetShopScope;

    public function __construct(
        private readonly ProductStockService $stock
    ) {}

    public function index(Request $request)
    {
        $clinicId = $this->scopeClinicId();

        $products = ClinicProduct::with(['category:id,name', 'variants'])
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim((string) $request->input('search'));
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%' . $search . '%')
                        ->orWhere('brand', 'like', '%' . $search . '%');
                });
            })
            ->when($request->filled('category'), fn ($q) => $q->where('product_category_id', (int) $request->input('category')))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->input('status') === 'active'))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.pet-shop.products.index', [
            'products' => $products,
            'categories' => ProductCategory::active()->ordered()->get(),
        ]);
    }

    public function create()
    {
        $this->requireWritableClinic();

        return view('admin.pet-shop.products.form', [
            'product' => new ClinicProduct(['is_active' => true]),
            'categories' => ProductCategory::active()->ordered()->get(),
            'variants' => collect(),
        ]);
    }

    public function store(Request $request)
    {
        $clinicId = $this->requireWritableClinic();
        $validated = $this->validateProduct($request);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $this->storeImage($request);
        }

        try {
            $product = DB::transaction(function () use ($validated, $clinicId, $imagePath, $request) {
                $product = ClinicProduct::create([
                    'clinic_id' => $clinicId,
                    'product_category_id' => (int) $validated['product_category_id'],
                    'name' => $validated['name'],
                    'slug' => ClinicProduct::makeUniqueSlug($clinicId, $validated['name']),
                    'description' => $validated['description'] ?? null,
                    'brand' => $validated['brand'] ?? null,
                    'image_path' => $imagePath,
                    'is_active' => $request->boolean('is_active', true),
                ]);

                foreach ($validated['variants'] as $index => $variantData) {
                    $variant = ProductVariant::create([
                        'clinic_product_id' => $product->id,
                        'sku' => strtoupper(trim($variantData['sku'])),
                        'name' => $variantData['name'],
                        'price' => (int) $variantData['price'],
                        'discount_price' => isset($variantData['discount_price']) && $variantData['discount_price'] !== null
                            ? (int) $variantData['discount_price'] : null,
                        'stock_on_hand' => 0,
                        'stock_reserved' => 0,
                        'low_stock_threshold' => (int) ($variantData['low_stock_threshold'] ?? 5),
                        'is_active' => filter_var($variantData['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    ]);

                    $stock = (int) ($variantData['stock_on_hand'] ?? 0);
                    if ($stock > 0) {
                        $this->stock->adjust($variant, $stock, 'Stok awal produk', auth()->id(), ProductStockMovement::TYPE_PURCHASE);
                    }
                }

                return $product;
            });
        } catch (Throwable $e) {
            if ($imagePath) {
                Storage::disk(config('petshop.image.disk', 'public'))->delete(
                    preg_replace('#^(storage|public)/#', '', $imagePath)
                );
            }
            throw $e;
        }

        AuditLog::log('pet_shop.product.created', 'Produk Pet Shop dibuat: ' . $product->name, $product);

        return redirect()->route('admin.pet-shop.products.index')
            ->with('success', __('petshop.product_created'));
    }

    public function show(ClinicProduct $product)
    {
        $this->authorizeProduct($product);

        $product->load(['category', 'clinic', 'variants' => fn ($q) => $q->orderBy('name')]);

        $movements = ProductStockMovement::with('variant')
            ->where('clinic_product_id', $product->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.pet-shop.products.show', compact('product', 'movements'));
    }

    public function edit(ClinicProduct $product)
    {
        $this->authorizeProduct($product);
        $this->requireWritableClinic();

        $product->load(['variants' => fn ($q) => $q->orderBy('name')]);

        return view('admin.pet-shop.products.form', [
            'product' => $product,
            'categories' => ProductCategory::active()->ordered()->get(),
            'variants' => $product->variants,
        ]);
    }

    public function update(Request $request, ClinicProduct $product)
    {
        $this->authorizeProduct($product);
        $clinicId = $this->requireWritableClinic();
        $validated = $this->validateProduct($request, $product);

        $oldImage = $product->image_path;
        $newImage = $oldImage;

        if ($request->hasFile('image')) {
            $newImage = $this->storeImage($request);
        }

        try {
            DB::transaction(function () use ($validated, $product, $request, $clinicId, $newImage) {
                $product->update([
                    'product_category_id' => (int) $validated['product_category_id'],
                    'name' => $validated['name'],
                    'slug' => ClinicProduct::makeUniqueSlug($clinicId, $validated['name'], $product->id),
                    'description' => $validated['description'] ?? null,
                    'brand' => $validated['brand'] ?? null,
                    'image_path' => $newImage,
                    'is_active' => $request->boolean('is_active', true),
                ]);

                $existing = $product->variants()->get()->keyBy('id');
                $keptIds = [];

                foreach ($validated['variants'] as $variantData) {
                    $variantId = isset($variantData['id']) ? (int) $variantData['id'] : null;

                    if ($variantId && $existing->has($variantId)) {
                        $variant = $existing->get($variantId);
                        $keptIds[] = $variant->id;
                        $newStock = (int) ($variantData['stock_on_hand'] ?? $variant->stock_on_hand);
                        $delta = $newStock - (int) $variant->stock_on_hand;

                        $variant->update([
                            'sku' => strtoupper(trim($variantData['sku'])),
                            'name' => $variantData['name'],
                            'price' => (int) $variantData['price'],
                            'discount_price' => isset($variantData['discount_price']) && $variantData['discount_price'] !== null
                                ? (int) $variantData['discount_price'] : null,
                            'low_stock_threshold' => (int) ($variantData['low_stock_threshold'] ?? 5),
                            'is_active' => filter_var($variantData['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                        ]);

                        if ($delta !== 0) {
                            $this->stock->adjust($variant, $delta, 'Penyesuaian stok dari edit produk', auth()->id());
                        }

                        continue;
                    }

                    $variant = ProductVariant::create([
                        'clinic_product_id' => $product->id,
                        'sku' => strtoupper(trim($variantData['sku'])),
                        'name' => $variantData['name'],
                        'price' => (int) $variantData['price'],
                        'discount_price' => isset($variantData['discount_price']) && $variantData['discount_price'] !== null
                            ? (int) $variantData['discount_price'] : null,
                        'stock_on_hand' => 0,
                        'stock_reserved' => 0,
                        'low_stock_threshold' => (int) ($variantData['low_stock_threshold'] ?? 5),
                        'is_active' => filter_var($variantData['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    ]);

                    $keptIds[] = $variant->id;

                    $stock = (int) ($variantData['stock_on_hand'] ?? 0);
                    if ($stock > 0) {
                        $this->stock->adjust($variant, $stock, 'Stok awal varian baru', auth()->id(), ProductStockMovement::TYPE_PURCHASE);
                    }
                }

                // Varian yang dihapus dari form TIDAK dihapus dari database:
                // dinonaktifkan agar histori pesanan & movement tetap utuh.
                $product->variants()
                    ->whereNotIn('id', $keptIds)
                    ->update(['is_active' => false]);
            });
        } catch (Throwable $e) {
            if ($newImage && $newImage !== $oldImage) {
                Storage::disk(config('petshop.image.disk', 'public'))->delete($newImage);
            }
            throw $e;
        }

        // Gambar lama hanya dihapus bila produk sudah tidak memakainya.
        if ($oldImage && $newImage !== $oldImage) {
            Storage::disk(config('petshop.image.disk', 'public'))->delete($oldImage);
        }

        AuditLog::log('pet_shop.product.updated', 'Produk Pet Shop diperbarui: ' . $product->name, $product);

        return redirect()->route('admin.pet-shop.products.index')
            ->with('success', __('petshop.product_updated'));
    }

    public function destroy(ClinicProduct $product)
    {
        $this->authorizeProduct($product);

        $hasOpenOrder = $product->orderItems()
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', ProductOrder::FINAL_STATUSES))
            ->exists();

        if ($hasOpenOrder) {
            return back()->with('error', __('petshop.product_has_open_orders'));
        }

        DB::transaction(function () use ($product) {
            $product->variants()->update(['is_active' => false]);
            $product->update(['is_active' => false]);
            $product->delete();
        });

        AuditLog::log('pet_shop.product.archived', 'Produk Pet Shop diarsipkan: ' . $product->name, $product);

        return redirect()->route('admin.pet-shop.products.index')
            ->with('success', __('petshop.product_archived'));
    }

    public function toggle(ClinicProduct $product)
    {
        $this->authorizeProduct($product);

        $product->update(['is_active' => !$product->is_active]);

        AuditLog::log(
            'pet_shop.product.toggled',
            sprintf('Produk %s %s', $product->name, $product->is_active ? 'diaktifkan' : 'dinonaktifkan'),
            $product
        );

        return back()->with('success', __('petshop.product_status_updated'));
    }

    /**
     * Import katalog dari CSV/Excel.
     *
     * Wajib ada klinik aktif: produk tanpa klinik tidak akan pernah muncul
     * di katalog mana pun. Header tidak dikenali dibedakan dari file yang
     * header-nya benar tetapi belum diisi, supaya admin tahu apa yang harus
     * diperbaiki.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:4096',
            'duplicate_strategy' => 'nullable|in:update,skip',
        ]);

        $clinicId = currentClinicId();
        if (!$clinicId) {
            return redirect()
                ->route('admin.pet-shop.products.index')
                ->with('error', __('petshop.select_clinic_first'));
        }

        $file = $request->file('file');
        $extension = strtolower((string) $file?->getClientOriginalExtension());
        $readerType = match ($extension) {
            'csv', 'txt' => ExcelWriter::CSV,
            'xlsx' => ExcelWriter::XLSX,
            'xls' => ExcelWriter::XLS,
            default => null,
        };

        // CSV: pemisah dideteksi dari baris header, bukan dari baris pertama,
        // karena file nyata sering punya judul di atas header.
        $csvDelimiter = null;
        if ($readerType === ExcelWriter::CSV && $file) {
            $realPath = $file->getRealPath();
            $contents = $realPath ? @file_get_contents($realPath) : false;
            if (is_string($contents) && $contents !== '') {
                $csvDelimiter = PetShopProductsImport::guessCsvDelimiter($contents);
            }
        }

        $import = new PetShopProductsImport(
            (string) $request->input('duplicate_strategy', 'update'),
            (int) $clinicId,
            $csvDelimiter
        );

        try {
            if ($readerType === null) {
                throw new \InvalidArgumentException('Jenis berkas import produk tidak dikenal.');
            }

            Excel::import($import, $file, null, $readerType);
        } catch (Throwable $e) {
            Log::warning('Pet Shop product import failed', [
                'file_name' => $file?->getClientOriginalName(),
                'extension' => $extension,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.pet-shop.products.index')
                ->with('error', __('petshop.import_failed'));
        }

        $summary = $import->summary;
        $errorRows = $import->errorRows;

        session()->put('petshop_products_import_error_report', $errorRows);
        if (empty($errorRows)) {
            session()->forget('petshop_products_import_error_report');
        }

        if (!$import->headerFound) {
            Log::warning('Pet Shop product import header not recognized', [
                'file_name' => $file?->getClientOriginalName(),
                'extension' => $extension,
                'scanned_rows' => $import->scannedRows,
            ]);

            return redirect()
                ->route('admin.pet-shop.products.index')
                ->with('error', __('petshop.import_header_missing'));
        }

        AuditLog::log(
            'pet_shop.product.imported',
            sprintf(
                'Import produk Pet Shop: %d produk baru, %d varian baru, %d varian diperbarui, %d gagal',
                $summary['products_created'],
                $summary['success'],
                $summary['updated'],
                $summary['failed']
            )
        );

        if ($summary['total'] === 0) {
            return redirect()
                ->route('admin.pet-shop.products.index')
                ->with('error', __('petshop.import_empty'));
        }

        return redirect()
            ->route('admin.pet-shop.products.index')
            ->with('success', __('petshop.import_done'))
            ->with('petshop_product_import_summary', $summary)
            ->with('petshop_product_import_has_errors', !empty($errorRows));
    }

    /** Unduh template contoh (CSV). */
    public function downloadTemplateCsv()
    {
        return Excel::download(
            new PetShopProductsTemplateExport(),
            'template-produk-pet-shop.csv',
            ExcelWriter::CSV
        );
    }

    /** Unduh template contoh (Excel). */
    public function downloadTemplateXlsx()
    {
        return Excel::download(new PetShopProductsTemplateExport(), 'template-produk-pet-shop.xlsx');
    }

    /** Ekspor katalog klinik aktif dalam format template. */
    public function exportCsv()
    {
        return Excel::download(
            new PetShopProductsTemplateExport(true, $this->scopeClinicId()),
            'katalog-produk-pet-shop.csv',
            ExcelWriter::CSV
        );
    }

    public function exportXlsx()
    {
        return Excel::download(
            new PetShopProductsTemplateExport(true, $this->scopeClinicId()),
            'katalog-produk-pet-shop.xlsx'
        );
    }

    /** Laporan baris gagal dari import terakhir pada sesi ini. */
    public function downloadImportErrorReport(Request $request)
    {
        $rows = session('petshop_products_import_error_report', []);
        if (empty($rows)) {
            return redirect()
                ->route('admin.pet-shop.products.index')
                ->with('error', __('petshop.import_no_report'));
        }

        $filename = 'produk-pet-shop-import-gagal-' . now()->format('Y-m-d_His');

        if (strtolower((string) $request->query('format', 'csv')) === 'xlsx') {
            return Excel::download(new PetShopProductsImportErrorReportExport($rows), $filename . '.xlsx');
        }

        return Excel::download(
            new PetShopProductsImportErrorReportExport($rows),
            $filename . '.csv',
            ExcelWriter::CSV
        );
    }

    public function adjustStock(Request $request, ProductVariant $variant)
    {
        $product = $variant->product;
        $this->authorizeProduct($product);
        $this->requireWritableClinic();

        $validated = $request->validate([
            'quantity' => 'required|integer|not_in:0',
            'note' => 'required|string|max:200',
        ]);

        try {
            $this->stock->adjust(
                $variant,
                (int) $validated['quantity'],
                $validated['note'],
                auth()->id()
            );
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::log(
            'pet_shop.stock.adjusted',
            sprintf('Stok %s — %s disesuaikan %+d', $product->name, $variant->name, (int) $validated['quantity']),
            $variant
        );

        return back()->with('success', __('petshop.stock_updated'));
    }

    private function validateProduct(Request $request, ?ClinicProduct $product = null): array
    {
        $imageConfig = config('petshop.image');

        $validated = $request->validate([
            'name' => 'required|string|max:160',
            'product_category_id' => 'required|integer|exists:product_categories,id',
            'brand' => 'nullable|string|max:120',
            'description' => 'nullable|string|max:5000',
            'is_active' => 'nullable|boolean',
            'image' => [
                'nullable',
                'image',
                'mimes:' . implode(',', $imageConfig['mimes']),
                'max:' . (int) $imageConfig['max_kb'],
            ],
            'variants' => 'required|array|min:1|max:20',
            'variants.*.id' => 'nullable|integer',
            'variants.*.sku' => 'required|string|max:64|distinct',
            'variants.*.name' => 'required|string|max:120',
            'variants.*.price' => 'required|integer|min:0|max:1000000000',
            'variants.*.discount_price' => 'nullable|integer|min:0|max:1000000000',
            'variants.*.stock_on_hand' => 'required|integer|min:0|max:1000000',
            'variants.*.low_stock_threshold' => 'nullable|integer|min:0|max:10000',
            'variants.*.is_active' => 'nullable|boolean',
        ], [
            'variants.*.sku.distinct' => __('petshop.sku_duplicate_in_form'),
        ]);

        $errors = [];

        foreach ($validated['variants'] as $index => $variant) {
            $sku = strtoupper(trim((string) $variant['sku']));
            $discount = $variant['discount_price'] ?? null;

            if ($discount !== null && (int) $discount >= (int) $variant['price']) {
                $errors["variants.$index.discount_price"] = __('petshop.discount_must_be_lower');
            }

            $query = ProductVariant::query()->where('sku', $sku);

            if (!empty($variant['id'])) {
                $query->whereKeyNot((int) $variant['id']);

                $owned = $product?->variants()->whereKey((int) $variant['id'])->exists();
                if (!$owned) {
                    $errors["variants.$index.id"] = __('petshop.variant_not_owned');
                }
            }

            if ($query->exists()) {
                $errors["variants.$index.sku"] = __('petshop.sku_taken', ['sku' => $sku]);
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        $validated['variants'] = array_map(function (array $variant) {
            $variant['sku'] = strtoupper(trim((string) $variant['sku']));

            return $variant;
        }, $validated['variants']);

        return $validated;
    }

    private function storeImage(Request $request): string
    {
        $directory = trim((string) config('petshop.image.directory', 'pet-shop/products'), '/');
        $filename = $directory . '/' . uniqid('product-', true) . '.jpg';

        ImageService::process($request->file('image'), $filename, 1200, 82);

        return $filename;
    }
}
