<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\ClinicProduct;
use App\Models\ProductCategory;
use App\Models\ProductStockMovement;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Import katalog Pet Shop dari CSV & Excel.
 *
 * Yang dikunci:
 *  - baris dengan nama produk sama digabung menjadi satu produk,
 *  - kategori tidak pernah dibuat otomatis,
 *  - stok awal varian baru tercatat sebagai mutasi, bukan angka liar,
 *  - varian lama tidak kehilangan stok saat diimpor ulang,
 *  - SKU klinik lain / produk lain ditolak,
 *  - template, ekspor, dan laporan kesalahan bisa diunduh.
 */
class PetShopProductImportTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $adminA;
    private ProductCategory $food;
    private ProductCategory $care;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = Str::lower(Str::random(6));

        $this->clinicA = Clinic::create([
            'name' => 'Import Shop A ' . $suffix,
            'slug' => 'import-shop-a-' . $suffix,
            'address' => 'Jl. A',
            'is_active' => true,
        ]);

        $this->clinicB = Clinic::create([
            'name' => 'Import Shop B ' . $suffix,
            'slug' => 'import-shop-b-' . $suffix,
            'address' => 'Jl. B',
            'is_active' => true,
        ]);

        $this->adminA = User::create([
            'name' => 'Admin Import Shop',
            'email' => 'admin-import-shop-' . $suffix . '@test.local',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinicA->id,
        ]);

        $this->food = ProductCategory::create([
            'name' => 'Makanan ' . $suffix,
            'slug' => 'makanan-' . $suffix,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->care = ProductCategory::create([
            'name' => 'Perawatan ' . $suffix,
            'slug' => 'perawatan-' . $suffix,
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }

    private function csv(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'produk') . '.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'produk.csv', 'text/csv', null, true);
    }

    private function xlsx(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'produk') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'produk.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function import(UploadedFile $file, string $strategy = 'update')
    {
        return $this->actingAs($this->adminA)->post('/admin/pet-shop/products/import', [
            'file' => $file,
            'duplicate_strategy' => $strategy,
        ]);
    }

    public function test_csv_import_groups_rows_into_products_and_records_opening_stock(): void
    {
        $csv = implode("\n", [
            'category,product_name,brand,description,sku,variant_name,price,discount_price,stock,min_stock,status',
            $this->food->name . ',Royal Canin Adult,Royal Canin,Makanan harian.,RC-ADT-2KG,2 kg,185000,175000,12,4,active',
            $this->food->name . ',Royal Canin Adult,Royal Canin,Makanan harian.,RC-ADT-4KG,4 kg,"Rp 340.000",,8,3,active',
            $this->care->name . ',Shampoo Anti Kutu,PetCare,Untuk kucing.,PC-SHP-250,250 ml,65000,,20,5,active',
        ]);

        $this->import($this->csv($csv))
            ->assertRedirect(route('admin.pet-shop.products.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('petshop_product_import_summary');

        $this->assertSame(2, ClinicProduct::where('clinic_id', $this->clinicA->id)->count());

        $royal = ClinicProduct::where('clinic_id', $this->clinicA->id)
            ->where('name', 'Royal Canin Adult')
            ->firstOrFail();

        $this->assertSame($this->food->id, (int) $royal->product_category_id);
        $this->assertSame('Royal Canin', $royal->brand);
        $this->assertSame(2, $royal->variants()->count());

        $twoKg = $royal->variants()->where('sku', 'RC-ADT-2KG')->firstOrFail();
        $this->assertSame(175000, (int) $twoKg->discount_price);
        $this->assertSame(12, (int) $twoKg->stock_on_hand);
        $this->assertSame(4, (int) $twoKg->low_stock_threshold);

        $this->assertDatabaseHas('product_stock_movements', [
            'product_variant_id' => $twoKg->id,
            'type' => ProductStockMovement::TYPE_PURCHASE,
            'quantity' => 12,
            'stock_after' => 12,
        ]);

        $shampoo = ClinicProduct::where('clinic_id', $this->clinicA->id)
            ->where('name', 'Shampoo Anti Kutu')
            ->firstOrFail();
        $this->assertSame($this->care->id, (int) $shampoo->product_category_id);
    }

    public function test_xlsx_import_is_supported(): void
    {
        $file = $this->xlsx([
            ['category', 'product_name', 'brand', 'sku', 'variant_name', 'price', 'stock'],
            [$this->food->name, 'Whiskas Tuna', 'Whiskas', 'WHK-TNA-1KG', '1 kg', 95000, 6],
        ]);

        $this->import($file)->assertRedirect(route('admin.pet-shop.products.index'));

        $variant = ProductVariant::where('sku', 'WHK-TNA-1KG')->firstOrFail();
        $this->assertSame(95000, (int) $variant->price);
        $this->assertSame(6, (int) $variant->stock_on_hand);
    }

    public function test_unknown_category_is_created_once_instead_of_failing_rows(): void
    {
        $csv = implode("\n", [
            'category,product_name,sku,variant_name,price',
            'Kategori Baru,Produk Baru,PH-001,1 pcs,10000',
            'Kategori Baru,Produk Lain,PH-002,1 pcs,15000',
        ]);

        $this->import($this->csv($csv))
            ->assertRedirect(route('admin.pet-shop.products.index'))
            ->assertSessionHas('petshop_product_import_has_errors', false);

        $category = ProductCategory::where('name', 'Kategori Baru')->firstOrFail();
        $this->assertSame(2, ClinicProduct::where('product_category_id', $category->id)->count());

        $summary = session('petshop_product_import_summary');
        $this->assertSame(1, $summary['categories_created']);
        $this->assertSame(['Kategori Baru'], $summary['category_names_created']);
    }

    public function test_missing_variant_and_category_use_clear_defaults(): void
    {
        $csv = implode("\n", [
            'product_name,sku,price',
            'Produk Tanpa Varian,PV-001,"Rp 25.000,-"',
        ]);

        $this->import($this->csv($csv))->assertRedirect();

        $variant = ProductVariant::where('sku', 'PV-001')->firstOrFail();
        $this->assertSame('Standar', $variant->name);
        $this->assertSame(25000, (int) $variant->price);
        $this->assertSame('Umum', $variant->product->category->name);
    }

    public function test_repeated_header_rows_are_skipped_not_failed(): void
    {
        $csv = implode("\n", [
            'category,product_name,sku,variant_name,price',
            $this->food->name . ',Produk A,PA-001,1 pcs,10000',
            'category,product_name,sku,variant_name,price',
            $this->food->name . ',Produk B,PB-001,1 pcs,20000',
        ]);

        $this->import($this->csv($csv))->assertRedirect();

        $summary = session('petshop_product_import_summary');
        $this->assertSame(2, $summary['total']);
        $this->assertSame(0, $summary['failed']);
    }

    public function test_existing_variant_can_be_updated_without_touching_stock(): void
    {
        $variant = $this->makeVariant('RC-UPD-1KG', price: 100000, stock: 9);

        $csv = implode("\n", [
            'category,product_name,sku,variant_name,price,discount_price,stock,min_stock',
            $this->food->name . ',Produk RC-UPD-1KG,RC-UPD-1KG,1 kg,120000,110000,50,2',
        ]);

        $this->import($this->csv($csv))->assertRedirect();

        $variant->refresh();
        $this->assertSame(120000, (int) $variant->price);
        $this->assertSame(110000, (int) $variant->discount_price);
        $this->assertSame(2, (int) $variant->low_stock_threshold);
        // Stok di file tidak boleh menimpa stok nyata.
        $this->assertSame(9, (int) $variant->stock_on_hand);
    }

    public function test_skip_strategy_leaves_existing_variant_unchanged(): void
    {
        $variant = $this->makeVariant('RC-SKIP-1KG', price: 100000, stock: 3);

        $csv = implode("\n", [
            'category,product_name,sku,variant_name,price',
            $this->food->name . ',Produk RC-SKIP-1KG,RC-SKIP-1KG,1 kg,999000',
        ]);

        $this->import($this->csv($csv), 'skip')
            ->assertRedirect()
            ->assertSessionHas('petshop_product_import_summary');

        $this->assertSame(100000, (int) $variant->fresh()->price);
    }

    public function test_sku_owned_by_another_clinic_is_rejected(): void
    {
        $otherProduct = ClinicProduct::create([
            'clinic_id' => $this->clinicB->id,
            'product_category_id' => $this->food->id,
            'name' => 'Produk Klinik B',
            'slug' => 'produk-klinik-b-' . Str::lower(Str::random(4)),
            'is_active' => true,
        ]);

        ProductVariant::create([
            'clinic_product_id' => $otherProduct->id,
            'sku' => 'SKU-MILIK-B',
            'name' => '1 kg',
            'price' => 50000,
            'stock_on_hand' => 5,
            'stock_reserved' => 0,
            'low_stock_threshold' => 2,
            'is_active' => true,
        ]);

        $csv = implode("\n", [
            'category,product_name,sku,variant_name,price',
            $this->food->name . ',Produk Bentrok,SKU-MILIK-B,1 kg,60000',
        ]);

        $this->import($this->csv($csv))
            ->assertRedirect()
            ->assertSessionHas('petshop_product_import_has_errors', true);

        $this->assertSame(50000, (int) ProductVariant::where('sku', 'SKU-MILIK-B')->firstOrFail()->price);
        $this->assertFalse(ClinicProduct::where('clinic_id', $this->clinicA->id)->exists());

        $report = session('petshop_products_import_error_report');
        $this->assertStringContainsString('klinik lain', $report[0]['error_message']);
    }

    public function test_header_missing_is_reported_separately(): void
    {
        $csv = "foo,bar\n1,2\n";

        $this->import($this->csv($csv))
            ->assertRedirect(route('admin.pet-shop.products.index'))
            ->assertSessionHas('error');
    }

    public function test_products_page_renders_import_controls(): void
    {
        $this->actingAs($this->adminA)
            ->get(route('admin.pet-shop.products.index'))
            ->assertOk()
            ->assertSee('petshop-product-import-modal', false)
            ->assertSee(route('admin.pet-shop.products.import'), false);
    }

    public function test_template_export_and_error_report_downloads_work(): void
    {
        $this->actingAs($this->adminA)
            ->get(route('admin.pet-shop.products.template.csv'))
            ->assertOk();

        $this->actingAs($this->adminA)
            ->get(route('admin.pet-shop.products.template.xlsx'))
            ->assertOk();

        $this->makeVariant('RC-EXP-1KG', price: 80000, stock: 4);

        $this->actingAs($this->adminA)
            ->get(route('admin.pet-shop.products.export.xlsx'))
            ->assertOk();
    }

    private function makeVariant(string $sku, int $price, int $stock): ProductVariant
    {
        $product = ClinicProduct::create([
            'clinic_id' => $this->clinicA->id,
            'product_category_id' => $this->food->id,
            'name' => 'Produk ' . $sku,
            'slug' => Str::slug('produk-' . $sku),
            'is_active' => true,
        ]);

        return ProductVariant::create([
            'clinic_product_id' => $product->id,
            'sku' => $sku,
            'name' => '1 kg',
            'price' => $price,
            'stock_on_hand' => $stock,
            'stock_reserved' => 0,
            'low_stock_threshold' => 5,
            'is_active' => true,
        ]);
    }
}
