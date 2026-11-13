<?php

namespace App\Services;

use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineStockMovement;
use App\Models\MedicineStockTransaction;
use App\Models\PrescriptionItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Satu-satunya pintu untuk mengubah stok obat.
 *
 * Aturan yang dijaga:
 *  1. Setiap perubahan menghasilkan baris `medicine_stock_movements`.
 *  2. Stok tidak boleh negatif — permintaan melebihi stok ditolak.
 *  3. Saldo disimpan di dua tempat yang harus konsisten:
 *       - `medicines.stock/week`  = total tersedia (kompatibilitas lama)
 *       - `medicine_batches.stock` = rincian per batch
 *     Semua penulisan melewati service ini, bukan `stock = ...` dari controller.
 *  4. Batch yang sudah kedaluwarsa tidak pernah dipakai untuk resep.
 *  5. `quarantined_stock` hanya berubah lewat perpindahan/penyelesaian
 *     transaksi, sehingga total fisik (available + quarantine) tetap utuh.
 */
class MedicineStockService
{
    public const TYPES = [
        MedicineStockMovement::TYPE_IN,
        MedicineStockMovement::TYPE_OUT,
        MedicineStockMovement::TYPE_ADJUSTMENT,
        MedicineStockMovement::TYPE_RETURN,
        MedicineStockMovement::TYPE_DISPOSAL,
        MedicineStockMovement::TYPE_RETURN_SUPPLIER,
        MedicineStockMovement::TYPE_QUARANTINE,
        MedicineStockMovement::TYPE_DAMAGED,
    ];

    /**
     * Catat satu baris mutasi stok.
     *
     * @param  float  $quantity  Positif menambah saldo, negatif mengurangi.
     * @param  string  $stockState  `available` atau `quarantine`.
     * @throws RuntimeException bila saldo akan menjadi negatif.
     */
    public function record(
        Medicine $medicine,
        float $quantity,
        string $type,
        ?string $reason = null,
        ?int $medicalRecordId = null,
        ?int $userId = null,
        ?int $batchId = null,
        string $stockState = MedicineStockMovement::STOCK_STATE_AVAILABLE,
        ?int $transactionId = null
    ): MedicineStockMovement {
        if (!in_array($type, self::TYPES, true)) {
            throw new RuntimeException("Jenis mutasi stok tidak dikenal: {$type}");
        }

        if (!in_array($stockState, [
            MedicineStockMovement::STOCK_STATE_AVAILABLE,
            MedicineStockMovement::STOCK_STATE_QUARANTINE,
        ], true)) {
            throw new RuntimeException("Jenis saldo stok tidak dikenal: {$stockState}");
        }

        return DB::transaction(function () use (
            $medicine, $quantity, $type, $reason, $medicalRecordId, $userId,
            $batchId, $stockState, $transactionId
        ) {
            // Kunci baris: dua permintaan bersamaan tidak boleh memakai saldo
            // yang sama (mis. dua rekam medis mengurangi obat sekaligus).
            $locked = Medicine::whereKey($medicine->id)->lockForUpdate()->firstOrFail();

            $batch = null;
            if ($batchId !== null) {
                $batch = MedicineBatch::whereKey($batchId)
                    ->where('medicine_id', $locked->id)
                    ->where('clinic_id', $locked->clinic_id)
                    ->lockForUpdate()
                    ->first();

                if (!$batch) {
                    throw new RuntimeException(
                        "Batch obat tidak ditemukan untuk {$locked->name} di klinik ini."
                    );
                }
            }

            $isQuarantine = $stockState === MedicineStockMovement::STOCK_STATE_QUARANTINE;
            $current = $isQuarantine
                ? (float) $locked->quarantined_stock
                : (float) $locked->stock;
            $after = $current + $quantity;

            if ($after < -0.0001) {
                throw new RuntimeException(
                    "Stok " . ($isQuarantine ? 'karantina ' : '') . "{$locked->name} tidak cukup. "
                    . "Tersedia " . $this->formatNumber($current) . " {$locked->unit}, "
                    . "diminta " . $this->formatNumber(abs($quantity)) . " {$locked->unit}."
                );
            }

            if ($batch) {
                $batchCurrent = $isQuarantine
                    ? (float) $batch->quarantined_stock
                    : (float) $batch->stock;
                $batchAfter = $batchCurrent + $quantity;

                if ($batchAfter < -0.0001) {
                    throw new RuntimeException(
                        "Stok " . ($isQuarantine ? 'karantina ' : '') . "batch {$batch->batch_number} "
                        . "{$locked->name} tidak cukup. Tersedia "
                        . $this->formatNumber($batchCurrent) . " {$locked->unit}."
                    );
                }

                $batch->forceFill([
                    $isQuarantine ? 'quarantined_stock' : 'stock' => max(0, $batchAfter),
                ])->save();
            }

            $locked->forceFill([
                $isQuarantine ? 'quarantined_stock' : 'stock' => max(0, $after),
            ])->save();

            return MedicineStockMovement::create([
                'transaction_id' => $transactionId,
                'medicine_id' => $locked->id,
                'batch_id' => $batch?->id,
                'clinic_id' => $locked->clinic_id,
                'type' => $type,
                'stock_state' => $stockState,
                'quantity' => $quantity,
                'stock_after' => max(0, $after),
                'reason' => $reason,
                'medical_record_id' => $medicalRecordId,
                'created_by' => $userId ?? auth()->id(),
            ]);
        });
    }

    /**
     * Isi stok awal untuk obat yang BARU dibuat atau reset data awal.
     */
    public function setOpeningStock(Medicine $medicine, float $quantity, ?string $reason = null): ?MedicineStockMovement
    {
        if ($quantity <= 0) {
            return null;
        }

        $medicine->forceFill(['stock' => 0, 'quarantined_stock' => 0])->save();
        MedicineBatch::where('medicine_id', $medicine->id)
            ->update(['stock' => 0, 'quarantined_stock' => 0]);

        return $this->stockInBatch(
            $medicine,
            $quantity,
            'AWAL',
            $medicine->expiry_date?->format('Y-m-d'),
            $reason ?? 'Stok awal saat obat dibuat'
        );
    }

    /**
     * Stok masuk tanpa detail batch (kompatibilitas pemanggil lama).
     * Batch baru otomatis dibuat supaya setiap penerimaan tetap dapat dilacak.
     */
    public function stockIn(Medicine $medicine, float $quantity, ?string $reason = null, ?int $userId = null): MedicineStockMovement
    {
        return $this->stockInBatch($medicine, $quantity, null, null, $reason, $userId);
    }

    /**
     * Stok masuk / pembelian dengan nomor batch dan tanggal kedaluwarsa.
     *
     * Bila nomor batch sudah ada untuk obat yang sama, stok ditambahkan ke batch
     * tersebut (penerimaan ulang); bila belum, batch baru dibuat.
     */
    public function stockInBatch(
        Medicine $medicine,
        float $quantity,
        ?string $batchNumber = null,
        ?string $expiryDate = null,
        ?string $reason = null,
        ?int $userId = null
    ): MedicineStockMovement {
        $quantity = abs($quantity);

        if ($quantity <= 0) {
            throw new RuntimeException('Jumlah stok masuk harus lebih dari 0.');
        }

        return DB::transaction(function () use ($medicine, $quantity, $batchNumber, $expiryDate, $reason, $userId) {
            $locked = Medicine::whereKey($medicine->id)->lockForUpdate()->firstOrFail();
            $batchNumber = $batchNumber !== null ? trim($batchNumber) : '';

            $batch = null;
            if ($batchNumber !== '') {
                $batch = MedicineBatch::where('medicine_id', $locked->id)
                    ->where('batch_number', $batchNumber)
                    ->lockForUpdate()
                    ->first();
            }

            if (!$batch) {
                $batch = MedicineBatch::create([
                    'clinic_id' => $locked->clinic_id,
                    'medicine_id' => $locked->id,
                    'batch_number' => $batchNumber !== '' ? $batchNumber : $this->generateBatchNumber($locked),
                    'expiry_date' => $expiryDate,
                    'received_at' => today()->format('Y-m-d'),
                    'notes' => null,
                ]);
                $batch->refresh();
            } elseif ($expiryDate !== null) {
                // Penerimaan ulang boleh memperbaiki tanggal ED batch.
                $batch->forceFill(['expiry_date' => $expiryDate])->save();
            }

            $movement = $this->record(
                $locked,
                $quantity,
                MedicineStockMovement::TYPE_IN,
                $reason,
                null,
                $userId,
                $batch->id
            );

            $this->reviveIfDisposed($locked);
            $this->syncExpiryMirror($locked);

            return $movement;
        });
    }

    /**
     * Musnahkan sisa stok obat kedaluwarsa / rusak (alur lama).
     *
     * Dipertahankan untuk kompatibilitas halaman detail obat. Alur baru yang
     * punya dokumen + status memakai `MedicineMutationService` (karantina lalu
     * retur/pemusnahan), tetapi keduanya menulis buku besar yang sama.
     *
     * @param  float|null  $quantity  Null = seluruh stok tersedia.
     */
    public function dispose(
        Medicine $medicine,
        ?float $quantity,
        string $method,
        string $reason,
        ?int $userId = null
    ): MedicineStockMovement {
        if (!in_array($method, Medicine::DISPOSAL_METHODS, true)) {
            throw new RuntimeException("Metode pemusnahan tidak dikenal: {$method}");
        }

        $currentStock = (float) Medicine::whereKey($medicine->id)->value('stock');
        $qty = $quantity === null ? $currentStock : $quantity;

        if ($qty <= 0) {
            throw new RuntimeException("Stok {$medicine->name} kosong, tidak ada yang bisa dimusnahkan.");
        }

        if ($qty > $currentStock + 0.0001) {
            throw new RuntimeException(
                "Jumlah yang dimusnahkan ({$qty} {$medicine->unit}) melebihi stok tersedia ({$currentStock} {$medicine->unit})."
            );
        }

        return DB::transaction(function () use ($medicine, $qty, $method, $reason, $userId) {
            $remaining = $qty;
            $lastMovement = null;

            // Alur lama tetap menghasilkan dokumen mutasi yang sama supaya
            // riwayat pemusnahan tidak terpecah di dua halaman berbeda.
            $isReturn = $method === Medicine::DISPOSAL_RETURNED;
            $transaction = MedicineStockTransaction::create([
                'reference' => 'MUT-LGS-' . now()->format('Ymd') . '-' . $medicine->id . '-' . random_int(1000, 9999),
                'clinic_id' => $medicine->clinic_id,
                'medicine_id' => $medicine->id,
                'batch_id' => null,
                'type' => $isReturn
                    ? MedicineStockTransaction::TYPE_RETURN_SUPPLIER
                    : MedicineStockTransaction::TYPE_DISPOSAL,
                'status' => MedicineStockTransaction::STATUS_COMPLETED,
                'quantity' => $qty,
                'reason' => $reason,
                'return_date' => $isReturn ? now()->toDateString() : null,
                'destruction_date' => $isReturn ? null : now()->toDateString(),
                'destruction_method' => $isReturn ? null : 'other',
                'from_quarantine' => false,
                'notes' => 'Dicatat dari alur pemusnahan lama; stok keluar dari saldo tersedia.',
                'created_by' => $userId,
                'completed_by' => $userId,
                'completed_at' => now(),
            ]);

            $batches = $this->batchesWithStock($medicine, quarantine: false, expiredFirst: true);

            foreach ($batches as $batch) {
                if ($remaining <= 0.0001) {
                    break;
                }

                $take = min((float) $batch->stock, $remaining);
                if ($take <= 0) {
                    continue;
                }

                $lastMovement = $this->record(
                    $medicine,
                    -$take,
                    MedicineStockMovement::TYPE_DISPOSAL,
                    $reason,
                    null,
                    $userId,
                    $batch->id,
                    MedicineStockMovement::STOCK_STATE_AVAILABLE,
                    $transaction->id
                );
                $remaining -= $take;
            }

            // Data lama tanpa batch tetap bisa dimusnahkan.
            if ($remaining > 0.0001) {
                $lastMovement = $this->record(
                    $medicine,
                    -$remaining,
                    MedicineStockMovement::TYPE_DISPOSAL,
                    $reason,
                    null,
                    $userId,
                    null,
                    MedicineStockMovement::STOCK_STATE_AVAILABLE,
                    $transaction->id
                );
            }

            $locked = Medicine::whereKey($medicine->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'disposed_at' => now(),
                'disposal_method' => $method,
                'disposal_reason' => $reason,
                'disposed_quantity' => $qty,
                'is_active' => false,
            ])->save();

            $this->syncExpiryMirror($locked);

            return $lastMovement;
        });
    }

    /**
     * Koreksi stok hasil hitung fisik (stock opname).
     */
    public function adjustTo(Medicine $medicine, float $targetStock, string $reason, ?int $userId = null): MedicineStockMovement
    {
        $currentStock = (float) Medicine::whereKey($medicine->id)->value('stock');
        $delta = round($targetStock - $currentStock, 2);

        if (abs($delta) < 0.0001) {
            return $this->record(
                $medicine,
                0,
                MedicineStockMovement::TYPE_ADJUSTMENT,
                $reason,
                null,
                $userId
            );
        }

        return DB::transaction(function () use ($medicine, $delta, $reason, $userId) {
            $movement = null;

            if ($delta < 0) {
                $remaining = abs($delta);
                $batches = $this->batchesWithStock($medicine, quarantine: false);

                foreach ($batches as $batch) {
                    if ($remaining <= 0.0001) {
                        break;
                    }

                    $take = min((float) $batch->stock, $remaining);
                    $movement = $this->record(
                        $medicine,
                        -$take,
                        MedicineStockMovement::TYPE_ADJUSTMENT,
                        $reason,
                        null,
                        $userId,
                        $batch->id
                    );
                    $remaining -= $take;
                }

                if ($remaining > 0.0001) {
                    $movement = $this->record(
                        $medicine,
                        -$remaining,
                        MedicineStockMovement::TYPE_ADJUSTMENT,
                        $reason,
                        null,
                        $userId
                    );
                }
            } else {
                $batch = MedicineBatch::where('medicine_id', $medicine->id)
                    ->orderByRaw('expiry_date IS NULL')
                    ->orderBy('expiry_date')
                    ->lockForUpdate()
                    ->first();

                if (!$batch) {
                    $batch = MedicineBatch::create([
                        'clinic_id' => $medicine->clinic_id,
                        'medicine_id' => $medicine->id,
                        'batch_number' => $this->generateBatchNumber($medicine),
                        'expiry_date' => null,
                        'received_at' => today()->format('Y-m-d'),
                        'notes' => 'Batch dibuat dari koreksi stok (stok opname).',
                    ]);
                }

                $movement = $this->record(
                    $medicine,
                    $delta,
                    MedicineStockMovement::TYPE_ADJUSTMENT,
                    $reason,
                    null,
                    $userId,
                    $batch->id
                );
            }

            $this->syncExpiryMirror($medicine->fresh());

            return $movement;
        });
    }

    /**
     * Potong stok dari item resep sebuah rekam medis (FEFO per batch).
     *
     * Batch kedaluwarsa tidak pernah dipakai. Bila stok layak pakai kurang,
     * seluruh transaksi ditolak — bukan dipotong sebagian.
     *
     * @return array<int, MedicineStockMovement>
     * @throws RuntimeException
     */
    public function consumePrescription(MedicalRecord $record): array
    {
        $movements = [];

        foreach ($record->prescriptionItems()->with('medicine')->get() as $item) {
            /** @var PrescriptionItem $item */
            if (!$item->medicine_id || !$item->medicine) {
                continue;
            }

            $medicine = $item->medicine;
            $quantity = abs((float) $item->quantity);

            if ($quantity <= 0) {
                continue;
            }

            $reason = "Resep rekam medis #{$record->id}" . ($item->dose ? " ({$item->dose})" : '');

            $batches = DB::transaction(function () use ($medicine) {
                return $this->batchesWithStock($medicine, quarantine: false);
            });

            if ($batches->isEmpty()) {
                // Obat lama tanpa batch: tetap blokir bila level obat sudah ED.
                if ($medicine->isExpired()) {
                    throw $this->expiredMessage($medicine);
                }

                $movements[] = $this->record(
                    $medicine,
                    -$quantity,
                    MedicineStockMovement::TYPE_OUT,
                    $reason,
                    $record->id
                );
                continue;
            }

            $dispensable = $batches->filter(fn (MedicineBatch $batch) => !$batch->isExpired());
            $available = (float) $dispensable->sum('stock');

            if ($available + 0.0001 < $quantity) {
                $expiredQuantity = (float) $batches
                    ->filter(fn (MedicineBatch $batch) => $batch->isExpired())
                    ->sum('stock');

                $message = "Stok layak pakai {$medicine->name} tidak cukup. "
                    . "Tersedia " . $this->formatNumber($available) . " {$medicine->unit}, "
                    . "diminta " . $this->formatNumber($quantity) . " {$medicine->unit}.";

                if ($expiredQuantity > 0) {
                    $message .= ' Sebanyak ' . $this->formatNumber($expiredQuantity)
                        . " {$medicine->unit} berada di batch kedaluwarsa dan tidak boleh dipakai "
                        . '— karantina lalu musnahkan batch tersebut.';
                }

                throw new RuntimeException($message);
            }

            $remaining = $quantity;
            foreach ($dispensable->sortBy(fn (MedicineBatch $batch) => $batch->expiry_date?->timestamp ?? PHP_INT_MAX) as $batch) {
                if ($remaining <= 0.0001) {
                    break;
                }

                $take = min((float) $batch->stock, $remaining);
                if ($take <= 0) {
                    continue;
                }

                $movements[] = $this->record(
                    $medicine,
                    -$take,
                    MedicineStockMovement::TYPE_OUT,
                    $reason,
                    $record->id,
                    null,
                    $batch->id
                );
                $remaining -= $take;
            }

            $this->syncExpiryMirror($medicine->fresh());
        }

        return $movements;
    }

    /**
     * Kembalikan stok saat rekam medis dihapus atau resep dikoreksi.
     *
     * Hanya mengembalikan sisa NET yang pernah dipotong (out + return), dan
     * dikembalikan ke batch asalnya supaya saldo batch tetap benar.
     */
    public function restoreForMedicalRecord(MedicalRecord $record): void
    {
        $taken = MedicineStockMovement::where('medical_record_id', $record->id)
            ->whereIn('type', [
                MedicineStockMovement::TYPE_OUT,
                MedicineStockMovement::TYPE_RETURN,
            ])
            ->selectRaw('medicine_id, batch_id, SUM(quantity) as total')
            ->groupBy('medicine_id', 'batch_id')
            ->get();

        $touchedMedicines = [];

        foreach ($taken as $row) {
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
                $record->id,
                null,
                $row->batch_id ? (int) $row->batch_id : null
            );

            $touchedMedicines[$medicine->id] = $medicine;
        }

        foreach ($touchedMedicines as $medicine) {
            $this->syncExpiryMirror($medicine->fresh());
        }
    }

    /**
     * Total stok batch yang boleh dipakai resep (belum ED).
     */
    public function dispensableQuantity(Medicine $medicine): float
    {
        return (float) MedicineBatch::where('medicine_id', $medicine->id)
            ->withAvailableStock()
            ->notExpired()
            ->sum('stock');
    }

    /**
     * Samakan `medicines.expiry_date` dengan ED batch tersedia terdekat.
     *
     * Halaman lama (filter daftar obat, dashboard) masih membaca tanggal level
     * obat. Dengan mirror ini filter lama tetap masuk akal tanpa mengubah
     * query bersejarah.
     */
    public function syncExpiryMirror(Medicine $medicine): void
    {
        $batchesWithStock = MedicineBatch::where('medicine_id', $medicine->id)
            ->where('stock', '>', 0);

        if (!(clone $batchesWithStock)->exists()) {
            return;
        }

        $earliest = (clone $batchesWithStock)->whereNotNull('expiry_date')->min('expiry_date');
        $allWithoutExpiry = (clone $batchesWithStock)->whereNotNull('expiry_date')->count() === 0;

        $target = $allWithoutExpiry ? null : $earliest;

        if ($medicine->expiry_date?->format('Y-m-d') !== $target) {
            $medicine->forceFill(['expiry_date' => $target])->save();
        }
    }

    private function reviveIfDisposed(Medicine $medicine): void
    {
        if (!$medicine->isDisposed()) {
            return;
        }

        $medicine->forceFill([
            'disposed_at' => null,
            'disposal_method' => null,
            'disposal_reason' => null,
            'disposed_quantity' => null,
            'is_active' => true,
        ])->save();
    }

    /**
     * @return \Illuminate\Support\Collection<int, MedicineBatch>
     */
    private function batchesWithStock(Medicine $medicine, bool $quarantine = false, bool $expiredFirst = false)
    {
        return MedicineBatch::where('medicine_id', $medicine->id)
            ->when(
                $quarantine,
                fn ($q) => $q->withQuarantineStock(),
                fn ($q) => $q->withAvailableStock()
            )
            ->when($expiredFirst, fn ($q) => $q->orderByRaw('expiry_date IS NULL ASC'))
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->get();
    }

    private function expiredMessage(Medicine $medicine): RuntimeException
    {
        return new RuntimeException(
            "Obat {$medicine->name} sudah kedaluwarsa sejak "
            . $medicine->expiry_date?->format('d M Y')
            . " dan tidak boleh diresepkan. Karantina lalu musnahkan obat ini lebih dulu."
        );
    }

    private function generateBatchNumber(Medicine $medicine): string
    {
        do {
            $number = 'AUTO-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
        } while (MedicineBatch::where('medicine_id', $medicine->id)
            ->where('batch_number', $number)
            ->exists());

        return $number;
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
