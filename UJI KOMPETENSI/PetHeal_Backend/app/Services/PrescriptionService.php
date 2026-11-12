<?php

namespace App\Services;

use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Models\PrescriptionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Menyimpan item resep terstruktur + memotong stok.
 *
 * Dipakai bersama oleh panel admin dan portal dokter supaya kedua jalur
 * berperilaku identik — sebelumnya biaya obat hanya diisi manual dari dua
 * tempat berbeda dan bisa menghasilkan angka yang tidak cocok.
 */
class PrescriptionService
{
    public function __construct(private readonly MedicineStockService $stock)
    {
    }

    /**
     * Samakan item resep sebuah rekam medis dengan input form.
     *
     * Perilaku:
     *  - `medicine_id` kosong diabaikan (baris belum dipilih admin),
     *  - item lama dihapus, lalu dibuat ulang; mutasi stok lama untuk rekam
     *    medis ini dikembalikan lebih dulu supaya stok tidak terpotong ganda,
     *  - `medicine_cost` rekam medis dihitung ulang dari qty × harga satuan.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return float total biaya obat
     * @throws RuntimeException bila stok tidak cukup
     */
    public function syncForRecord(MedicalRecord $record, array $items): float
    {
        return DB::transaction(function () use ($record, $items) {
            // Kembalikan dulu pemotongan sebelumnya (koreksi resep), agar
            // hasil akhir = stok awal - qty resep baru, bukan berlapis.
            $this->stock->restoreForMedicalRecord($record);

            $record->prescriptionItems()->delete();

            $total = 0.0;

            foreach ($items as $row) {
                $medicineId = $row['medicine_id'] ?? null;
                if (!$medicineId) {
                    continue;
                }

                // Username obat WAJIB dari klinik rekam medis: mencegah obat
                // klinik lain dipakai untuk memotong stok yang salah.
                $medicine = Medicine::whereKey($medicineId)
                    ->where('clinic_id', $record->clinic_id)
                    ->first();

                if (!$medicine) {
                    continue;
                }

                $quantity = (float) ($row['quantity'] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }

                $unitPrice = (float) ($row['unit_price'] ?? $medicine->price);

                PrescriptionItem::create([
                    'medical_record_id' => $record->id,
                    'medicine_id' => $medicine->id,
                    'medicine_name' => $medicine->name,
                    'dose' => $row['dose'] ?? null,
                    'quantity' => $quantity,
                    'unit' => $medicine->unit,
                    'unit_price' => $unitPrice,
                ]);

                $total += $quantity * $unitPrice;
            }

            // Simpan relasi baru lalu potong stok sekali.
            $record->unsetRelation('prescriptionItems');
            $this->stock->consumePrescription($record);

            return round($total, 2);
        });
    }

    /**
     * Biaya obat otomatis bila resep diisi.
     *
     * Mengembalikan angka manual yang diberikan bila resep kosong, supaya
     * klinik yang belum memakai master obat tidak kehilangan input lamanya.
     */
    public function resolveMedicineCost(MedicalRecord $record, float $manualCost): float
    {
        $fromPrescription = (float) $record->prescriptionItems()->sum(
            DB::raw('quantity * unit_price')
        );

        return $fromPrescription > 0 ? $fromPrescription : $manualCost;
    }

    /**
     * Bila rekam medis dihapus, stok yang terlanjur dipotong dikembalikan.
     * Dipanggil sebelum `delete()` di controller.
     */
    public function restoreOnDelete(MedicalRecord $record): void
    {
        try {
            $this->stock->restoreForMedicalRecord($record);
        } catch (RuntimeException $e) {
            // Jangan gagalkan penghapusan hanya karena pengembalian stok
            // bermasalah; catat supaya bisa ditindaklanjuti manual.
            Log::warning('Gagal mengembalikan stok saat hapus rekam medis', [
                'medical_record_id' => $record->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
