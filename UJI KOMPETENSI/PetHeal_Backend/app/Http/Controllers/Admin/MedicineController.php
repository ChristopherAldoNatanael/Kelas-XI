<?php

namespace App\Http\Controllers\Admin;

use App\Exports\MedicinesImportErrorReportExport;
use App\Exports\MedicinesTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\MedicinesImport;
use App\Models\AuditLog;
use App\Models\Medicine;
use App\Models\MedicineStockMovement;
use App\Services\MedicineStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

/**
 * Stok obat per klinik.
 *
 * Isolasi tenant: setiap query dan validasi keunikan nama dibatasi
 * `clinic_id` konteks aktif, jadi id tebakan tidak bisa menyentuh obat
 * klinik lain. `clinic_id` tidak pernah diambil dari input user.
 *
 * Stok TIDAK diedit langsung dari form. Semua perubahan stok lewat
 * `MedicineStockService` agar selalu ada baris mutasi yang bisa diaudit.
 */
class MedicineController extends Controller
{
    public function __construct(private readonly MedicineStockService $stock)
    {
    }

    private function scopedQuery()
    {
        $clinicId = currentClinicId();

        return Medicine::query()->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId));
    }

    private function findScoped(int $id): Medicine
    {
        return $this->scopedQuery()->whereKey($id)->firstOrFail();
    }

    public function index(Request $request)
    {
        $query = $this->scopedQuery();

        // Filter yang memang dipakai saat operasional.
        if ($request->boolean('low_stock')) {
            $query->lowStock();
        }
        if ($request->boolean('expiring')) {
            $query->expiringSoon(60);
        }
        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where('name', 'like', '%' . $term . '%');
        }

        $medicines = $query->orderBy('name')->paginate(20)->withQueryString();

        // Ringkasan kecil di atas tabel: jumlah obat menipis dan kedaluwarsa
        // dekat. Dihitung terpisah dari query tabel agar filter tidak
        // mengubah arti angka ringkasan.
        $summaryBase = fn () => Medicine::query()
            ->when(currentClinicId(), fn ($q) => $q->where('clinic_id', currentClinicId()));

        return view('admin.medicines.index', [
            'medicines' => $medicines,
            'lowStockCount' => $summaryBase()->lowStock()->count(),
            'expiringCount' => $summaryBase()->expiringSoon(60)->count(),
        ]);
    }

    public function create()
    {
        return view('admin.medicines.create');
    }

    public function store(Request $request)
    {
        $clinicId = currentClinicId();
        if (! $clinicId) {
            return redirect()->route('admin.medicines.index')
                ->with('error', __('admin.medicine_select_clinic'));
        }

        $validated = $this->validatePayload($request, $clinicId);

        $medicine = Medicine::create([
            ...$validated,
            'clinic_id' => $clinicId,
        ]);

        // Stok awal lewat service: tercatat sebagai mutasi masuk, dan
        // tidak pernah berasal dari mass-assignment.
        $this->stock->setOpeningStock(
            $medicine,
            (float) ($request->input('initial_stock') ?? 0)
        );

        AuditLog::log('medicine.create', "Created medicine {$medicine->name}", $medicine);

        return redirect()->route('admin.medicines.index')
            ->with('success', __('admin.medicine_created'));
    }

    public function edit(int $id)
    {
        $medicine = $this->findScoped($id);

        return view('admin.medicines.edit', [
            'medicine' => $medicine,
            'movements' => $medicine->movements()->latest()->limit(30)->get(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $medicine = $this->findScoped($id);
        $validated = $this->validatePayload($request, $medicine->clinic_id, $medicine->id);

        $medicine->fill($validated)->save();

        AuditLog::log('medicine.update', "Updated medicine {$medicine->name}", $medicine);

        return redirect()->route('admin.medicines.index')
            ->with('success', __('admin.medicine_updated'));
    }

    /**
     * Tambah stok masuk (pembelian/restock).
     */
    public function stockIn(Request $request, int $id)
    {
        $medicine = $this->findScoped($id);

        $request->validate([
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
        ], [
            'quantity.min' => __('admin.medicine_quantity_min'),
        ]);

        try {
            $movement = $this->stock->stockIn(
                $medicine,
                (float) $request->input('quantity'),
                $request->input('reason')
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::log(
            'medicine.stock_in',
            "Stock in {$medicine->name}: +{$movement->quantity} {$medicine->unit} (saldo {$movement->stock_after})",
            $medicine
        );

        return back()->with('success', __('admin.medicine_stock_in_saved'));
    }

    /**
     * Koreksi stok hasil hitung fisik.
     *
     * Alasan wajib: koreksi tanpa alasan mudah dipakai untuk menyembunyikan
     * selisih pencatatan, dan pertanyaan penguji "kenapa stok berubah tanpa
     * pemakaian?" jadi tidak terjawab.
     */
    public function adjust(Request $request, int $id)
    {
        $medicine = $this->findScoped($id);

        $request->validate([
            'target_stock' => 'required|numeric|min:0',
            'reason' => 'required|string|max:255',
        ], [
            'reason.required' => __('admin.medicine_adjust_reason_required'),
        ]);

        $before = (float) $medicine->stock;
        $target = (float) $request->input('target_stock');

        if (abs($target - $before) < 0.005) {
            return back()->with('error', __('admin.medicine_adjust_same'));
        }

        try {
            $movement = $this->stock->adjustTo($medicine, $target, (string) $request->input('reason'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::log(
            'medicine.stock_adjust',
            "Adjusted {$medicine->name}: {$before} → {$movement->stock_after} {$medicine->unit}. Reason: {$request->input('reason')}",
            $medicine
        );

        return back()->with('success', __('admin.medicine_stock_adjusted'));
    }

    /**
     * Riwayat mutasi satu obat.
     */
    public function movements(int $id)
    {
        $medicine = $this->findScoped($id);

        return view('admin.medicines.movements', [
            'medicine' => $medicine,
            'movements' => $medicine->movements()->latest()->paginate(50),
        ]);
    }

    /**
     * Hapus obat.
     *
     * Obat yang pernah dipakai resep tidak dihapus, hanya dinonaktifkan.
     * Alasannya sama dengan guard hapus dokter: resep dan biaya obat pada
     * rekam medis lama harus tetap bisa dijelaskan.
     */
    public function destroy(int $id)
    {
        $medicine = $this->findScoped($id);

        // Resep memakai mutasi `out`: itu bukti obat benar-benar diberikan.
        if ($medicine->movements()->where('type', MedicineStockMovement::TYPE_OUT)->exists()) {
            $medicine->update(['is_active' => false]);

            AuditLog::log('medicine.deactivate', "Deactivated medicine {$medicine->name}", $medicine);

            return redirect()->route('admin.medicines.index')
                ->with('warning', __('admin.medicine_in_use'));
        }

        // Riwayat stok lain (stok awal, pembelian, opname) bukan pemakaian
        // resep, tetapi tetap harus dipertahankan agar saldo bisa diaudit.
        if ($medicine->movements()->exists()) {
            $medicine->update(['is_active' => false]);

            AuditLog::log('medicine.deactivate', "Deactivated medicine {$medicine->name}", $medicine);

            return redirect()->route('admin.medicines.index')
                ->with('warning', __('admin.medicine_has_history'));
        }

        AuditLog::log('medicine.delete', "Deleted medicine {$medicine->name}", $medicine);
        $medicine->delete();

        return redirect()->route('admin.medicines.index')
            ->with('success', __('admin.medicine_deleted'));
    }

    /**
     * Import obat dari CSV/Excel.
     *
     * Sama seperti import layanan: tanpa konteks klinik import ditolak, karena
     * menulis baris tanpa klinik akan membuat obat "milik siapa-siapa" yang
     * tidak pernah muncul di daftar mana pun.
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
                ->route('admin.medicines.index')
                ->with('error', __('admin.medicine_select_clinic'));
        }

        $file = $request->file('file');
        $extension = strtolower((string) $file?->getClientOriginalExtension());
        $readerType = match ($extension) {
            'csv', 'txt' => ExcelWriter::CSV,
            'xlsx' => ExcelWriter::XLSX,
            'xls' => ExcelWriter::XLS,
            default => null,
        };

        // CSV: deteksi pemisah dari baris header lebih dulu. Tanpa ini
        // PhpSpreadsheet menebak dari beberapa baris teratas dan bisa memilih
        // spasi saat ada judul seperti "Daftar Obat Klinik" di baris 1.
        $csvDelimiter = null;
        if ($readerType === ExcelWriter::CSV && $file) {
            $realPath = $file->getRealPath();
            $contents = $realPath ? @file_get_contents($realPath) : false;
            if (is_string($contents) && $contents !== '') {
                $csvDelimiter = MedicinesImport::guessCsvDelimiter($contents);
            }
        }

        $import = new MedicinesImport(
            $request->input('duplicate_strategy', 'update'),
            $clinicId,
            $csvDelimiter
        );

        try {
            if ($readerType === null) {
                throw new \InvalidArgumentException('Jenis berkas import obat tidak dikenal.');
            }

            Excel::import($import, $file, null, $readerType);
        } catch (\Throwable $e) {
            Log::warning('Medicine import failed', [
                'file_name' => $file?->getClientOriginalName(),
                'extension' => $extension,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.medicines.index')
                ->with('error', __('admin.medicine_import_failed'));
        }

        $summary = $import->summary;
        $errorRows = $import->errorRows;

        session()->put('medicines_import_error_report', $errorRows);
        if (empty($errorRows)) {
            session()->forget('medicines_import_error_report');
        }

        // Header tidak dikenali berbeda dari "header benar tapi belum diisi".
        // Pesannya dipisah supaya admin tahu yang harus diperbaiki: nama kolom
        // atau isi barisnya.
        if (!$import->headerFound) {
            Log::warning('Medicine import header not recognized', [
                'file_name' => $file?->getClientOriginalName(),
                'extension' => $extension,
                'scanned_rows' => $import->scannedRows,
            ]);

            return redirect()
                ->route('admin.medicines.index')
                ->with('error', __('admin.medicine_import_header_missing'));
        }

        AuditLog::log(
            'medicine.import',
            "Imported medicines: {$summary['success']} new, {$summary['updated']} updated, {$summary['failed']} failed"
        );

        if ($summary['total'] === 0) {
            return redirect()
                ->route('admin.medicines.index')
                ->with('error', __('admin.medicine_import_empty'));
        }

        return redirect()
            ->route('admin.medicines.index')
            ->with('success', __('admin.medicine_import_done'))
            ->with('medicine_import_summary', $summary)
            ->with('medicine_import_has_errors', !empty($errorRows));
    }

    /** Unduh template contoh (CSV). */
    public function downloadTemplateCsv()
    {
        return Excel::download(
            new MedicinesTemplateExport(),
            'template-obat.csv',
            ExcelWriter::CSV
        );
    }

    /** Unduh template contoh (Excel). */
    public function downloadTemplateXlsx()
    {
        return Excel::download(new MedicinesTemplateExport(), 'template-obat.xlsx');
    }

    /**
     * Unduh isi stok saat ini dalam format yang sama dengan template, supaya
     * harga/minimum stok bisa diubah massal lalu diunggah kembali.
     */
    public function exportCsv()
    {
        return Excel::download(
            new MedicinesTemplateExport(true, currentClinicId()),
            'daftar-obat.csv',
            ExcelWriter::CSV
        );
    }

    public function exportXlsx()
    {
        return Excel::download(
            new MedicinesTemplateExport(true, currentClinicId()),
            'daftar-obat.xlsx'
        );
    }

    /** Laporan baris gagal dari import terakhir di sesi ini. */
    public function downloadImportErrorReport(Request $request)
    {
        $rows = session('medicines_import_error_report', []);
        if (empty($rows)) {
            return redirect()
                ->route('admin.medicines.index')
                ->with('error', __('admin.medicine_import_no_report'));
        }

        $filename = 'obat-import-gagal-' . now()->format('Y-m-d_His');

        if (strtolower((string) $request->query('format', 'csv')) === 'xlsx') {
            return Excel::download(new MedicinesImportErrorReportExport($rows), $filename . '.xlsx');
        }

        return Excel::download(
            new MedicinesImportErrorReportExport($rows),
            $filename . '.csv',
            ExcelWriter::CSV
        );
    }

    /**
     * Validasi bersama store & update. Stok sengaja TIDAK ada di sini.
     */
    private function validatePayload(Request $request, int $clinicId, ?int $ignoreId = null): array
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
        ]);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                // Unik per klinik, bukan global: dua klinik boleh punya
                // obat bernama sama.
                Rule::unique('medicines', 'name')
                    ->where(fn ($q) => $q->where('clinic_id', $clinicId))
                    ->ignore($ignoreId),
            ],
            'unit' => 'required|string|max:30',
            'form' => 'nullable|string|max:50',
            'min_stock' => 'nullable|numeric|min:0',
            'price' => 'nullable|numeric|min:0',
            'expiry_date' => 'nullable|date',
            'manufacturer' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['min_stock'] = (float) ($validated['min_stock'] ?? 0);
        $validated['price'] = (float) ($validated['price'] ?? 0);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        return $validated;
    }
}
