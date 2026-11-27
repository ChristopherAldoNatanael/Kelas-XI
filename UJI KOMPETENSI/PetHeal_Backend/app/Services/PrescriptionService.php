<?php

namespace App\Services;

use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Models\Prescription;
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
    public function __construct(
        private readonly MedicineStockService $stock,
        private readonly DoseCheckService $doseCheck
    ) {
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

            $record->loadMissing('pet');

            $prescription = Prescription::where('medical_record_id', $record->id)
                ->where('clinic_id', $record->clinic_id)
                ->lockForUpdate()
                ->first();

            if (! $prescription) {
                $prescription = Prescription::create([
                    'clinic_id' => $record->clinic_id,
                    'medical_record_id' => $record->id,
                    'prescription_number' => $this->prescriptionNumber($record->clinic_id),
                    'prescribed_by' => auth()->id(),
                    'prescribed_at' => now(),
                    'status' => Prescription::STATUS_ISSUED,
                    'notes' => 'Header dibuat oleh alur resep kompatibilitas.',
                ]);
            } else {
                $prescription->forceFill([
                    'status' => Prescription::STATUS_ISSUED,
                    'prescribed_by' => auth()->id() ?: $prescription->prescribed_by,
                    'prescribed_at' => now(),
                ])->save();
            }

            $record->prescriptionItems()->delete();

            $total = 0.0;
            $createdItems = 0;

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

                if (!$medicine->is_active) {
                    throw new RuntimeException(
                        "Obat {$medicine->name} sudah dinonaktifkan (dimusnahkan atau dihentikan) "
                        . "dan tidak bisa diresepkan."
                    );
                }

                $quantity = (float) ($row['quantity'] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }

                // Batch kedaluwarsa TIDAK boleh dipakai. Cek stok layak pakai
                // di sini agar pesan errornya jelas sebelum item disimpan;
                // `consumePrescription` tetap memvalidasi ulang saat memotong.
                $dispensable = $medicine->dispensableStock();
                if ($dispensable + 0.0001 < $quantity) {
                    throw new RuntimeException(
                        "Stok layak pakai {$medicine->name} tidak cukup. Tersedia "
                        . rtrim(rtrim(number_format($dispensable, 2, ',', '.'), '0'), ',')
                        . " {$medicine->unit}, diminta "
                        . rtrim(rtrim(number_format($quantity, 2, ',', '.'), '0'), ',')
                        . " {$medicine->unit}. Batch kedaluwarsa tidak boleh dipakai; "
                        . 'karantina lalu musnahkan batch tersebut.'
                    );
                }

                $unitPrice = (float) $medicine->price;
                $doseCheck = $this->doseCheck->check($row, $record->pet, $medicine);

                if ($doseCheck['dose_check_status'] === 'warning'
                    && trim((string) ($row['dose_check_notes'] ?? '')) !== '') {
                    $doseCheck['dose_check_status'] = 'overridden';
                    $doseCheck['dose_check_notes'] = trim((string) $row['dose_check_notes']);
                }

                PrescriptionItem::create([
                    'medical_record_id' => $record->id,
                    'prescription_id' => $prescription->id,
                    'medicine_id' => $medicine->id,
                    'medicine_name' => $medicine->name,
                    'dose' => $row['dose'] ?? null,
                    'dose_value' => $row['dose_value'] ?? null,
                    'dose_unit' => $row['dose_unit'] ?? null,
                    'dose_basis' => $row['dose_basis'] ?? null,
                    'weight_at_prescription' => $row['weight_at_prescription'] ?? $record->pet?->weight,
                    'concentration' => $row['concentration'] ?? null,
                    'calculated_dose' => $doseCheck['calculated_dose'],
                    'dose_check_status' => $doseCheck['dose_check_status'],
                    'dose_check_notes' => $doseCheck['dose_check_notes'],
                    'dosage_form' => $row['dosage_form'] ?? $medicine->form,
                    'strength' => $row['strength'] ?? null,
                    'route' => $row['route'] ?? null,
                    'frequency' => $row['frequency'] ?? null,
                    'duration_days' => isset($row['duration_days']) && $row['duration_days'] !== ''
                        ? (int) $row['duration_days']
                        : null,
                    'instruction' => $row['instruction'] ?? null,
                    'quantity' => $quantity,
                    'total_quantity' => $row['total_quantity'] ?? $quantity,
                    'withdrawal_period' => $row['withdrawal_period'] ?? $medicine->withdrawal_period,
                    'dose_reference_id' => $doseCheck['dose_reference_id'],
                    'dose_reference_source' => $doseCheck['dose_reference_source'],
                    'unit' => $medicine->unit,
                    'unit_price' => $unitPrice,
                    'drug_class' => $medicine->drug_class,
                    'is_antibiotic' => (bool) $medicine->is_antibiotic,
                ]);

                $total += $quantity * $unitPrice;
                $createdItems++;
            }

            // Simpan relasi baru lalu potong stok sekali.
            $record->unsetRelation('prescriptionItems');
            $this->stock->consumePrescription($record);

            if ($createdItems > 0) {
                $prescription->forceFill([
                    'status' => Prescription::STATUS_DISPENSED,
                    'notes' => 'Diserahkan otomatis oleh alur resep kompatibilitas; '
                        . 'dispensasi baru memakai DispensationService.',
                ])->save();
            }

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
    private function prescriptionNumber(int $clinicId): string
    {
        do {
            $number = sprintf('RX-%d-%s-%s', $clinicId, now()->format('Ymd'), strtoupper(\Illuminate\Support\Str::random(6)));
        } while (Prescription::where('clinic_id', $clinicId)->where('prescription_number', $number)->exists());

        return $number;
    }

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
