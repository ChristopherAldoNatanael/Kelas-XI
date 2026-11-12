<?php

namespace App\Services;

use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Models\MedicineStockMovement;
use App\Models\PrescriptionItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Satu-satunya pintu untuk mengubah stok obat.
 *
 * Aturan yang dijaga:
 *  1. Setiap perubahan menghasilkan baris `medicine_stock_movements`.
 *  2. Stok tidak boleh menjadi negatif — permintaan yang melebihi stok
 *     ditolak, bukan dipotong diam-diam menjadi 0.
 *  3. Saldo dihitung ulang dari mutasi, bukan ditambah manual, sehingga
 *     angka di daftar obat selalu bisa direkonsiliasi.
 *  4. Baris obat harus milik klinik yang sama dengan mutasinya.
 */
class MedicineStockService
{
    public const TYPES = [
        MedicineStockMovement::TYPE_IN,
        MedicineStockMovement::TYPE_OUT,
        MedicineStockMovement::TYPE_ADJUSTMENT,
        MedicineStockMovement::TYPE_RETURN,
    ];

    /**
     * Catat mutasi stok.
     *
     * @param  float  $quantity  Positif menambah stok, negatif mengurangi.
     * @throws RuntimeException bila hasilnya negatif.
     */
    public function record(
        Medicine $medicine,
        float $quantity,
        string $type,
        ?string $reason = null,
        ?int $medicalRecordId = null,
        ?int $userId = null
    ): MedicineStockMovement {
        if (!in_array($type, self::TYPES, true)) {
            throw new RuntimeException("Jenis mutasi stok tidak dikenal: {$type}");
        }

        return DB::transaction(function () use ($medicine, $quantity, $type, $reason, $medicalRecordId, $userId) {
            // Kunci baris: dua permintaan bersamaan tidak boleh memakai saldo
            // yang sama (mis. dua rekam medis mengurangi obat sekaligus).
            $locked = Medicine::whereKey($medicine->id)->lockForUpdate()->firstOrFail();

            $stockAfter = (float) $locked->stock + $quantity;

            if ($stockAfter < 0) {
                throw new RuntimeException(
                    "Stok {$locked->name} tidak cukup. Tersedia "
                    . rtrim(rtrim(number_format((float) $locked->stock, 2, ',', '.'), '0'), ',')
                    . " {$locked->unit}, diminta " . abs($quantity) . " {$locked->unit}."
                );
            }

            $locked->stock = $stockAfter;
            $locked->save();

            return MedicineStockMovement::create([
                'medicine_id' => $locked->id,
                'clinic_id' => $locked->clinic_id,
                'type' => $type,
                'quantity' => $quantity,
                'stock_after' => $stockAfter,
                'reason' => $reason,
                'medical_record_id' => $medicalRecordId,
                'created_by' => $userId ?? auth()->id(),
            ]);
        });
    }

    /**
     * Isi stok awal untuk obat yang BARU dibuat.
     *
     * Memakai `forceFill` karena `stock` sengaja tidak mass-assignable — nilai
     * stok tidak boleh datang dari input form. Tetap dicatat sebagai mutasi
     * masuk supaya buku besar dimulai dari nol yang bisa dilacak.
     */
    public function setOpeningStock(Medicine $medicine, float $quantity, ?string $reason = null): ?MedicineStockMovement
    {
        if ($quantity <= 0) {
            return null;
        }

        $medicine->forceFill(['stock' => 0])->save();

        return $this->stockIn($medicine, $quantity, $reason ?? 'Stok awal saat obat dibuat');
    }

    /**
     * Stok masuk / pembelian.
     */
    public function stockIn(Medicine $medicine, float $quantity, ?string $reason = null, ?int $userId = null): MedicineStockMovement
    {
        return $this->record($medicine, abs($quantity), MedicineStockMovement::TYPE_IN, $reason, null, $userId);
    }

    /**
     * Koreksi stok hasil hitung fisik (stock opname).
     *
     * Berbeda dengan `stockIn`: yang dicatat adalah SELISIH, sehingga saldo
     * akhir sama dengan angka hasil hitung. Alasan wajib agar koreksi tidak
     * dipakai sebagai jalan pintas menutupi kesalahan pencatatan.
     */
    public function adjustTo(Medicine $medicine, float $targetStock, string $reason, ?int $userId = null): MedicineStockMovement
    {
        // Selisih dihitung dari stok TERKINI di database, bukan dari objek di
        // memori: objek itu bisa sudah usang (mis. setelah pemotongan resep),
        // dan selisih yang salah akan membuat saldo akhir meleset.
        $currentStock = (float) Medicine::whereKey($medicine->id)->value('stock');
        $delta = $targetStock - $currentStock;

        return $this->record(
            $medicine,
            $delta,
            MedicineStockMovement::TYPE_ADJUSTMENT,
            $reason,
            null,
            $userId
        );
    }

    /**
     * Potong stok dari item resep sebuah rekam medis.
     *
     * Dipanggil SAAT rekam medis disimpan, di dalam transaksi yang sama.
     * Mengembalikan daftar mutasi yang dibuat.
     *
     * @return array<int, MedicineStockMovement>
     * @throws RuntimeException bila stok salah satu obat tidak cukup.
     */
    public function consumePrescription(MedicalRecord $record): array
    {
        $movements = [];

        foreach ($record->prescriptionItems()->with('medicine')->get() as $item) {
            /** @var PrescriptionItem $item */
            if (!$item->medicine_id || !$item->medicine) {
                // Obat di luar master: tetap tercatat di resep, stok tidak
                // bisa dipotong karena tidak ada saldo yang bisa dihitung.
                continue;
            }

            $movements[] = $this->record(
                $item->medicine,
                -abs((float) $item->quantity),
                MedicineStockMovement::TYPE_OUT,
                "Resep rekam medis #{$record->id}" . ($item->dose ? " ({$item->dose})" : ''),
                $record->id
            );
        }

        return $movements;
    }

    /**
     * Kembalikan stok saat rekam medis dihapus atau resep dikoreksi.
     *
     * Hanya mengembalikan jumlah yang PERNAH dipotong oleh rekam medis itu —
     * bukan seluruh quantity resep. Ini mencegah stok bertambah melebihi yang
     * sebenarnya keluar.
     */
    public function restoreForMedicalRecord(MedicalRecord $record): void
    {
        // Net mutasi rekam medis ini: keluar (negatif) + pengembalian
        // (positif). Hanya sisa yang MASIH terpakai yang dikembalikan.
        //
        // Dulu hanya baris `out` yang dijumlahkan, sehingga setiap koreksi
        // resep menambah lagi seluruh pemotongan lama: edit pertama benar,
        // edit kedua mengembalikan 2× qty, ketiga 3×, dan stok membengkak.
        $taken = MedicineStockMovement::where('medical_record_id', $record->id)
            ->whereIn('type', [
                MedicineStockMovement::TYPE_OUT,
                MedicineStockMovement::TYPE_RETURN,
            ])
            ->selectRaw('medicine_id, SUM(quantity) as total')
            ->groupBy('medicine_id')
            ->get();

        foreach ($taken as $row) {
            // SUM() masih negatif bila ada qty yang belum dikembalikan.
            $net = (float) $row->total;
            if ($net >= 0) {
                continue;
            }

            $returnQty = abs($net);

            $medicine = Medicine::find($row->medicine_id);
            if (!$medicine) {
                continue;
            }

            $this->record(
                $medicine,
                $returnQty,
                MedicineStockMovement::TYPE_RETURN,
                "Pembatalan rekam medis #{$record->id}",
                $record->id
            );
        }
    }
}
