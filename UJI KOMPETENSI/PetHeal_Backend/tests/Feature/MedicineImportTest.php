<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Medicine;
use App\Models\MedicineStockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Import obat dari CSV & Excel.
 *
 * Yang dikunci:
 *  - file CSV dan XLSX benar-benar terbaca (bukan hanya mock),
 *  - import tidak bisa menulis ke klinik lain,
 *  - stok obat baru tercatat sebagai mutasi, bukan angka liar,
 *  - stok obat lama TIDAK berubah karena import (hanya lewat buku mutasi),
 *  - sel kosong tidak menghapus data yang sudah ada,
 *  - strategi skip/update berjalan,
 *  - template & ekspor bisa diunduh.
 */
class MedicineImportTest extends TestCase
{
    use DatabaseTransactions;

    private Clinic $clinicA;
    private Clinic $clinicB;
    private User $adminA;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = Str::lower(Str::random(6));

        $this->clinicA = Clinic::create([
            'name' => 'Import A ' . $suffix,
            'slug' => 'import-a-' . $suffix,
            'address' => 'Jl. A',
            'is_active' => true,
        ]);
        $this->clinicB = Clinic::create([
            'name' => 'Import B ' . $suffix,
            'slug' => 'import-b-' . $suffix,
            'address' => 'Jl. B',
            'is_active' => true,
        ]);

        $this->adminA = User::create([
            'name' => 'Admin Import A',
            'email' => 'admin-import-' . $suffix . '@test.local',
            'password' => Hash::make('password123'),
            'role' => 'clinic_admin',
            'clinic_id' => $this->clinicA->id,
        ]);
    }

    private function csv(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'obat') . '.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, 'obat.csv', 'text/csv', null, true);
    }

    private function xlsx(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'obat') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'obat.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_csv_import_creates_medicines_and_records_opening_stock(): void
    {
        $csv = implode("\n", [
            'name,unit,form,stock,min_stock,price,expiry_date,manufacturer,status',
            'Amoxicillin 500 mg,tablet,Tablet,120,20,"Rp 3.500",2027-06-30,Kimia Farma,active',
            'Ringer Laktat,botol,Infus,40,10,18000,,Otsuka,active',
        ]);

        $response = $this->actingAs($this->adminA)
            ->post(route('admin.medicines.import'), ['file' => $this->csv($csv)]);

        $response->assertRedirect(route('admin.medicines.index'));
        $response->assertSessionHas('medicine_import_summary');

        $amox = Medicine::where('clinic_id', $this->clinicA->id)
            ->where('name', 'Amoxicillin 500 mg')
            ->first();

        $this->assertNotNull($amox, 'Obat dari CSV harus tersimpan');
        $this->assertSame('tablet', $amox->unit);
        $this->assertSame(120.0, (float) $amox->stock, 'Stok awal harus terbaca 120');
        $this->assertSame(20.0, (float) $amox->min_stock);
        $this->assertSame(3500.0, (float) $amox->price, 'Harga "Rp 3.500" harus jadi 3500');
        $this->assertSame('2027-06-30', $amox->expiry_date?->format('Y-m-d'));

        // Stok awal bukan angka liar: ada baris mutasi masuk.
        $movement = MedicineStockMovement::where('medicine_id', $amox->id)->first();
        $this->assertNotNull($movement, 'Stok awal harus tercatat sebagai mutasi');
        $this->assertSame(120.0, (float) $movement->quantity);
        $this->assertSame(120.0, (float) $movement->stock_after);
    }

    public function test_xlsx_import_works_with_real_excel_file(): void
    {
        $file = $this->xlsx([
            ['name', 'unit', 'form', 'stock', 'min_stock', 'price', 'expiry_date', 'manufacturer', 'status'],
            ['Meloxicam Injeksi', 'ampul', 'Injeksi', 15, 5, 25000, '2026-12-31', 'Sanbe', 'active'],
            ['Vitamin B Kompleks', 'tablet', 'Tablet', 0, 10, 1500, '', '', 'active'],
        ]);

        $response = $this->actingAs($this->adminA)
            ->post(route('admin.medicines.import'), ['file' => $file]);

        $response->assertRedirect(route('admin.medicines.index'));

        $this->assertDatabaseHas('medicines', [
            'clinic_id' => $this->clinicA->id,
            'name' => 'Meloxicam Injeksi',
            'unit' => 'ampul',
            'expiry_date' => '2026-12-31',
        ]);

        // Stok 0 tidak membuat mutasi palsu.
        $vitamin = Medicine::where('clinic_id', $this->clinicA->id)
            ->where('name', 'Vitamin B Kompleks')
            ->first();
        $this->assertNotNull($vitamin);
        $this->assertSame(0.0, (float) $vitamin->stock);
        $this->assertSame(
            0,
            MedicineStockMovement::where('medicine_id', $vitamin->id)->count(),
            'Stok awal 0 tidak boleh menghasilkan mutasi'
        );
    }

    public function test_import_never_writes_to_another_clinic(): void
    {
        Medicine::create([
            'clinic_id' => $this->clinicB->id,
            'name' => 'Amoxicillin 500 mg',
            'unit' => 'tablet',
            'price' => 9999,
            'min_stock' => 1,
            'is_active' => true,
        ]);

        $csv = "name,unit,price\nAmoxicillin 500 mg,kapsul,1000\n";

        $this->actingAs($this->adminA)
            ->post(route('admin.medicines.import'), ['file' => $this->csv($csv)]);

        // Obat klinik B tidak tersentuh.
        $other = Medicine::where('clinic_id', $this->clinicB->id)->where('name', 'Amoxicillin 500 mg')->first();
        $this->assertSame('tablet', $other->unit);
        $this->assertSame(9999.0, (float) $other->price);

        // Klinik A mendapat barisnya sendiri.
        $mine = Medicine::where('clinic_id', $this->clinicA->id)->where('name', 'Amoxicillin 500 mg')->first();
        $this->assertNotNull($mine);
        $this->assertSame('kapsul', $mine->unit);
    }

    public function test_update_strategy_does_not_touch_stock_and_blank_cells_keep_old_data(): void
    {
        $medicine = Medicine::create([
            'clinic_id' => $this->clinicA->id,
            'name' => 'Amoxicillin 500 mg',
            'unit' => 'tablet',
            'form' => 'Tablet',
            'manufacturer' => 'Kimia Farma',
            'price' => 3000,
            'min_stock' => 20,
            'is_active' => true,
        ]);
        app(\App\Services\MedicineStockService::class)->setOpeningStock($medicine, 50);

        // Hanya harga & nama yang diisi; stock sengaja 999 untuk membuktikan
        // kolom stok diabaikan untuk obat yang sudah ada.
        $csv = "name,unit,price,stock,manufacturer\nAmoxicillin 500 mg,,4000,999,\n";

        $this->actingAs($this->adminA)
            ->post(route('admin.medicines.import'), ['file' => $this->csv($csv)]);

        $medicine->refresh();

        $this->assertSame(4000.0, (float) $medicine->price, 'Harga harus diperbarui');
        $this->assertSame(50.0, (float) $medicine->stock, 'Stok TIDAK boleh berubah karena import');
        $this->assertSame('tablet', $medicine->unit, 'Sel kosong tidak menghapus satuan');
        $this->assertSame('Kimia Farma', $medicine->manufacturer, 'Sel kosong tidak menghapus produsen');
    }

    public function test_skip_strategy_leaves_existing_rows_untouched(): void
    {
        Medicine::create([
            'clinic_id' => $this->clinicA->id,
            'name' => 'Amoxicillin 500 mg',
            'unit' => 'tablet',
            'price' => 3000,
            'min_stock' => 1,
            'is_active' => true,
        ]);

        $csv = "name,unit,price\nAmoxicillin 500 mg,kapsul,9000\n";

        $response = $this->actingAs($this->adminA)->post(route('admin.medicines.import'), [
            'file' => $this->csv($csv),
            'duplicate_strategy' => 'skip',
        ]);

        $summary = $response->getSession()->get('medicine_import_summary');
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(0, $summary['updated']);

        $medicine = Medicine::where('clinic_id', $this->clinicA->id)->where('name', 'Amoxicillin 500 mg')->first();
        $this->assertSame(3000.0, (float) $medicine->price);
        $this->assertSame('tablet', $medicine->unit);
    }

    public function test_invalid_rows_are_reported_without_stopping_valid_rows(): void
    {
        $csv = implode("\n", [
            'name,unit,price',
            ',tablet,1000',
            'Obat Tanpa Satuan,,5000',
            'Obat Benar,tablet,7000',
        ]);

        $response = $this->actingAs($this->adminA)
            ->post(route('admin.medicines.import'), ['file' => $this->csv($csv)]);

        $summary = $response->getSession()->get('medicine_import_summary');
        $this->assertSame(2, $summary['failed']);
        $this->assertSame(1, $summary['success']);
        $response->assertSessionHas('medicine_import_has_errors', true);

        $this->assertDatabaseHas('medicines', [
            'clinic_id' => $this->clinicA->id,
            'name' => 'Obat Benar',
        ]);
    }

    public function test_import_requires_a_clinic_context(): void
    {
        $superAdmin = User::create([
            'name' => 'Super',
            'email' => 'super-import-' . Str::lower(Str::random(6)) . '@test.local',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'clinic_id' => null,
        ]);

        $csv = "name,unit,price\nObat Lintas Klinik,tablet,1000\n";

        $response = $this->actingAs($superAdmin)
            ->post(route('admin.medicines.import'), ['file' => $this->csv($csv)]);

        $response->assertRedirect(route('admin.medicines.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('medicines', ['name' => 'Obat Lintas Klinik']);
    }

    public function test_page_offers_import_and_exports(): void
    {
        $response = $this->actingAs($this->adminA)->get(route('admin.medicines.index'));

        $response->assertOk();
        $response->assertSee(__('admin.import_medicines'), false);
        $response->assertSee(route('admin.medicines.import'), false);
        $response->assertSee(route('admin.medicines.template.csv'), false);
        $response->assertSee(route('admin.medicines.template.xlsx'), false);
    }

    public function test_templates_and_data_exports_download(): void
    {
        Medicine::create([
            'clinic_id' => $this->clinicA->id,
            'name' => 'Obat Ekspor',
            'unit' => 'botol',
            'price' => 10000,
            'min_stock' => 2,
            'is_active' => true,
        ]);

        foreach ([
            'admin.medicines.template.csv',
            'admin.medicines.template.xlsx',
            'admin.medicines.export.csv',
            'admin.medicines.export.xlsx',
        ] as $routeName) {
            $response = $this->actingAs($this->adminA)->get(route($routeName));
            $this->assertSame(200, $response->getStatusCode(), "Rute {$routeName} harus mengunduh berkas");
        }
    }

    public function test_error_report_downloads_after_failed_rows(): void
    {
        $csv = "name,unit,price\n,tablet,1000\n";

        $this->actingAs($this->adminA)
            ->post(route('admin.medicines.import'), ['file' => $this->csv($csv)]);

        $response = $this->actingAs($this->adminA)
            ->get(route('admin.medicines.import.error-report', ['format' => 'csv']));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_title_row_above_the_header_is_skipped(): void
    {
        $csv = implode("\n", [
            'Daftar Obat Klinik PetHeal',
            'name,unit,form,stock,min_stock,price,expiry_date,manufacturer,status,notes',
            'Paracetamol 500 mg,tablet,Tablet,50,10,1500,2027-01-31,Generic,active,',
            'Ibuprofen 400 mg,tablet,Tablet,30,5,2000,2027-02-28,Generic,active,',
        ]);

        $response = $this->actingAs($this->adminA)
            ->post(route('admin.medicines.import'), ['file' => $this->csv($csv)]);

        $summary = $response->getSession()->get('medicine_import_summary');
        $this->assertSame(2, $summary['total'], 'Judul di baris 1 tidak boleh dihitung sebagai obat');
        $this->assertSame(2, $summary['success']);

        $this->assertDatabaseHas('medicines', [
            'clinic_id' => $this->clinicA->id,
            'name' => 'Paracetamol 500 mg',
            'unit' => 'tablet',
            'price' => 1500,
        ]);
        $this->assertDatabaseMissing('medicines', ['clinic_id' => $this->clinicA->id, 'name' => 'name']);
        $this->assertDatabaseMissing('medicines', ['clinic_id' => $this->clinicA->id, 'name' => 'Daftar Obat Klinik PetHeal']);
    }

    public function test_indonesian_header_names_are_accepted(): void
    {
        $csv = implode("\n", [
            'Nama Obat,Satuan,Bentuk,Stok,Stok Minimal,Harga,Kedaluwarsa,Produsen,Status,Catatan',
            'Amoxicillin 500 mg,tablet,Tablet,100,20,"Rp 3.500",30/06/2027,Kimia Farma,aktif,diminum sesudah makan',
        ]);

        $response = $this->actingAs($this->adminA)
            ->post(route('admin.medicines.import'), ['file' => $this->csv($csv)]);

        $summary = $response->getSession()->get('medicine_import_summary');
        $this->assertSame(1, $summary['success'], 'Header bahasa Indonesia harus terbaca');

        $medicine = Medicine::where('clinic_id', $this->clinicA->id)
            ->where('name', 'Amoxicillin 500 mg')
            ->first();

        $this->assertNotNull($medicine);
        $this->assertSame('tablet', $medicine->unit);
        $this->assertSame('Tablet', $medicine->form);
        $this->assertSame(3500.0, (float) $medicine->price);
        $this->assertSame(20.0, (float) $medicine->min_stock);
        $this->assertSame(100.0, (float) $medicine->stock);
        $this->assertSame('2027-06-30', $medicine->expiry_date?->format('Y-m-d'));
        $this->assertSame('Kimia Farma', $medicine->manufacturer);
    }

    public function test_blank_rows_before_the_header_are_skipped(): void
    {
        $csv = "\n\nname,unit,price\nParacetamol,tablet,1500\n";

        $response = $this->actingAs($this->adminA)
            ->post(route('admin.medicines.import'), ['file' => $this->csv($csv)]);

        $summary = $response->getSession()->get('medicine_import_summary');
        $this->assertSame(1, $summary['success']);

        $this->assertDatabaseHas('medicines', [
            'clinic_id' => $this->clinicA->id,
            'name' => 'Paracetamol',
            'price' => 1500,
        ]);
    }

    public function test_semicolon_delimited_csv_is_imported(): void
    {
        // Excel dengan locale Indonesia menyimpan CSV memakai titik-koma.
        $csv = implode("\n", [
            'name;unit;price;stock',
            'Ringer Laktat;botol;18000;40',
        ]);

        $response = $this->actingAs($this->adminA)
            ->post(route('admin.medicines.import'), ['file' => $this->csv($csv)]);

        $summary = $response->getSession()->get('medicine_import_summary');
        $this->assertSame(1, $summary['success']);

        $medicine = Medicine::where('clinic_id', $this->clinicA->id)->where('name', 'Ringer Laktat')->first();
        $this->assertNotNull($medicine);
        $this->assertSame(18000.0, (float) $medicine->price);
        $this->assertSame(40.0, (float) $medicine->stock);
    }

    public function test_csv_with_utf8_bom_imports_normally(): void
    {
        // Excel "CSV UTF-8" menambahkan BOM di depan nama kolom pertama.
        $csv = "\xEF\xBB\xBFname,unit,price\nVitamin C,tablet,1200\n";

        $response = $this->actingAs($this->adminA)
            ->post(route('admin.medicines.import'), ['file' => $this->csv($csv)]);

        $summary = $response->getSession()->get('medicine_import_summary');
        $this->assertSame(1, $summary['success']);

        $this->assertDatabaseHas('medicines', [
            'clinic_id' => $this->clinicA->id,
            'name' => 'Vitamin C',
            'price' => 1200,
        ]);
    }

    public function test_header_with_units_in_parentheses_is_recognised(): void
    {
        $csv = implode("\n", [
            'Nama Obat,Harga (Rp),Stok Awal,Satuan',
            'Betadine Solution,25000,12,botol',
        ]);

        $response = $this->actingAs($this->adminA)
            ->post(route('admin.medicines.import'), ['file' => $this->csv($csv)]);

        $summary = $response->getSession()->get('medicine_import_summary');
        $this->assertSame(1, $summary['success'], 'Header "Harga (Rp)" harus dikenali sebagai price');

        $medicine = Medicine::where('clinic_id', $this->clinicA->id)->where('name', 'Betadine Solution')->first();
        $this->assertNotNull($medicine);
        $this->assertSame(25000.0, (float) $medicine->price);
        $this->assertSame(12.0, (float) $medicine->stock);
    }

    public function test_xlsx_with_title_row_above_header_is_imported(): void
    {
        $file = $this->xlsx([
            ['Laporan Stok Obat'],
            ['name', 'unit', 'price', 'stock'],
            ['Dexamethasone Injeksi', 'ampul', 15000, 8],
        ]);

        $response = $this->actingAs($this->adminA)
            ->post(route('admin.medicines.import'), ['file' => $file]);

        $summary = $response->getSession()->get('medicine_import_summary');
        $this->assertSame(1, $summary['success']);

        $this->assertDatabaseHas('medicines', [
            'clinic_id' => $this->clinicA->id,
            'name' => 'Dexamethasone Injeksi',
            'unit' => 'ampul',
        ]);
    }

    public function test_tab_separated_txt_file_is_imported(): void
    {
        // Hasil copy-paste dari Excel ke Notepad memakai tab.
        $path = tempnam(sys_get_temp_dir(), 'obat') . '.txt';
        file_put_contents($path, "name\tunit\tprice\tstock\nParacetamol\ttablet\t1500\t20\n");

        $file = new UploadedFile($path, 'obat.txt', 'text/plain', null, true);

        $response = $this->actingAs($this->adminA)
            ->post(route('admin.medicines.import'), ['file' => $file]);

        $summary = $response->getSession()->get('medicine_import_summary');
        $this->assertSame(1, $summary['success']);

        $this->assertDatabaseHas('medicines', [
            'clinic_id' => $this->clinicA->id,
            'name' => 'Paracetamol',
            'price' => 1500,
        ]);
    }

    public function test_concatenated_indonesian_headers_are_recognised(): void
    {
        $csv = implode("\n", [
            'NamaObat,Satuan,StokMinimal,HargaJual,Catatan',
            'Salep Mata, tube,4,12000,simpan di suhu ruang',
        ]);

        $response = $this->actingAs($this->adminA)
            ->post(route('admin.medicines.import'), ['file' => $this->csv($csv)]);

        $summary = $response->getSession()->get('medicine_import_summary');
        $this->assertSame(1, $summary['success']);

        $medicine = Medicine::where('clinic_id', $this->clinicA->id)->where('name', 'Salep Mata')->first();
        $this->assertNotNull($medicine);
        $this->assertSame(12000.0, (float) $medicine->price);
        $this->assertSame(4.0, (float) $medicine->min_stock);
    }

    public function test_expiry_column_spelled_tgl_kadaluarsa_is_imported(): void
    {
        // Header persis dari berkas nyata "template-obat-klinik-hewan.xlsx".
        $file = $this->xlsx([
            [
                'Nama Obat / Sediaan', 'Satuan', 'Bentuk Sediaan', 'Stok Current', 'Stok Min',
                'Harga Satuan (Rp)', 'Tgl Kadaluarsa', 'Produsen / Produsen', 'Status',
                'Catatan Klinik / Indikasi',
            ],
            [
                'Amoxicillin 500 mg', 'tablet', 'Tablet', 120, 20, 3500,
                '2027-06-30', 'Kimia Farma', 'active', 'Diberikan sesudah makan',
            ],
        ]);

        $response = $this->actingAs($this->adminA)
            ->post(route('admin.medicines.import'), ['file' => $file]);

        $summary = $response->getSession()->get('medicine_import_summary');
        $this->assertSame(1, $summary['success']);

        $medicine = Medicine::where('clinic_id', $this->clinicA->id)
            ->where('name', 'Amoxicillin 500 mg')
            ->first();

        $this->assertNotNull($medicine);
        $this->assertSame('2027-06-30', $medicine->expiry_date?->format('Y-m-d'), 'Kolom "Tgl Kadaluarsa" harus terbaca');
        $this->assertSame(120.0, (float) $medicine->stock);
        $this->assertSame(20.0, (float) $medicine->min_stock);
        $this->assertSame(3500.0, (float) $medicine->price);
        $this->assertSame('Tablet', $medicine->form);
        $this->assertSame('Kimia Farma', $medicine->manufacturer);
    }

    public function test_unrecognised_header_returns_specific_error_instead_of_empty_rows(): void
    {
        $csv = "foo,bar\n1,2\n";

        $response = $this->actingAs($this->adminA)
            ->post(route('admin.medicines.import'), ['file' => $this->csv($csv)]);

        $response->assertRedirect(route('admin.medicines.index'));
        $response->assertSessionHas('error', __('admin.medicine_import_header_missing'));
        $this->assertSame(0, Medicine::where('clinic_id', $this->clinicA->id)->count());
    }

    public function test_header_only_file_reports_empty_rows_not_missing_header(): void
    {
        $csv = "name,unit,price,stock,min_stock,form,expiry_date,manufacturer,status,notes\n";

        $response = $this->actingAs($this->adminA)
            ->post(route('admin.medicines.import'), ['file' => $this->csv($csv)]);

        $response->assertSessionHas('error', __('admin.medicine_import_empty'));
        $response->assertSessionMissing('medicine_import_summary');
        $this->assertSame(0, Medicine::where('clinic_id', $this->clinicA->id)->count());
    }
}
