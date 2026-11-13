<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineStockMovement;
use App\Models\MedicineStockTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Alur kerja mutasi stok obat.
 *
 * Transaksi dibuat `pending` → `processing` → `completed`.
 * Saldo stok HANYA berubah saat `completed`, tepat satu kali. Ini yang
 * mencegah double deduction ketika tombol diproses dua kali atau halaman
 * di-refresh.
 *
 * Aturan saldo:
 *  - karantina: available −q, quarantine +q (total fisik tetap).
 *  - retur supplier langsung: available −q.
 *  - retur/pemusnahan dari karantina: quarantine −q.
 *  - obat rusak: available −q (keluar permanen, wajib alasan).
 *  - penyesuaian: available ±q sesuai arah.
 */
class MedicineMutationService
{
    public function __construct(private readonly MedicineStockService $stock)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $clinicId, ?int $userId = null): MedicineStockTransaction
    {
        return DB::transaction(function () use ($data, $clinicId, $userId) {
            $type = (string) ($data['type'] ?? '');

            if (!in_array($type, MedicineStockTransaction::TYPES, true)) {
                throw new RuntimeException('Jenis mutasi tidak dikenal.');
            }

            $medicine = Medicine::whereKey($data['medicine_id'] ?? null)
                ->where('clinic_id', $clinicId)
                ->first();

            if (!$medicine) {
                throw new RuntimeException('Obat tidak ditemukan di klinik ini.');
            }

            $batch = MedicineBatch::whereKey($data['batch_id'] ?? null)
                ->where('medicine_id', $medicine->id)
                ->where('clinic_id', $clinicId)
                ->first();

            if (!$batch) {
                throw new RuntimeException('Batch obat tidak ditemukan di klinik ini.');
            }

            $quantity = (float) ($data['quantity'] ?? 0);

            if ($quantity <= 0) {
                throw new RuntimeException('Jumlah mutasi harus lebih dari 0.');
            }

            $sourceTransactionId = null;
            $fromQuarantine = (bool) ($data['from_quarantine'] ?? false);

            if (!empty($data['source_transaction_id'])) {
                $source = MedicineStockTransaction::whereKey($data['source_transaction_id'])
                    ->where('clinic_id', $clinicId)
                    ->where('batch_id', $batch->id)
                    ->first();

                if (!$source || !$source->needsFollowUp()) {
                    throw new RuntimeException('Transaksi karantina sumber tidak valid atau sudah ditindaklanjuti.');
                }

                if ($quantity > $source->remainingFollowUpQuantity() + 0.0001) {
                    throw new RuntimeException('Jumlah melebihi sisa karantina yang belum ditindaklanjuti.');
                }

                $sourceTransactionId = $source->id;
                $fromQuarantine = true;
            }

            // Guard tambahan di server (bukan hanya form): jumlah tidak boleh
            // melebihi saldo yang akan dipotong.
            if ($type === MedicineStockTransaction::TYPE_QUARANTINE && (float) $batch->stock + 0.0001 < $quantity) {
                throw new RuntimeException('Jumlah karantina melebihi stok tersedia batch ini.');
            }

            if (in_array($type, [
                MedicineStockTransaction::TYPE_RETURN_SUPPLIER,
                MedicineStockTransaction::TYPE_DISPOSAL,
            ], true) && $fromQuarantine && (float) $batch->quarantined_stock + 0.0001 < $quantity) {
                throw new RuntimeException('Jumlah melebihi stok karantina batch ini.');
            }

            if ($type === MedicineStockTransaction::TYPE_RETURN_SUPPLIER && !$fromQuarantine
                && (float) $batch->stock + 0.0001 < $quantity) {
                throw new RuntimeException('Jumlah retur melebihi stok tersedia batch ini.');
            }

            if ($type === MedicineStockTransaction::TYPE_DISPOSAL && !$fromQuarantine) {
                throw new RuntimeException(
                    'Pemusnahan harus dilakukan dari stok karantina. Karantina batch ini terlebih dahulu.'
                );
            }

            if ($type === MedicineStockTransaction::TYPE_DAMAGED && (float) $batch->stock + 0.0001 < $quantity) {
                throw new RuntimeException('Jumlah obat rusak melebihi stok tersedia batch ini.');
            }

            if ($type === MedicineStockTransaction::TYPE_ADJUSTMENT
                && !in_array($data['direction'] ?? null, ['in', 'out'], true)) {
                throw new RuntimeException('Arah penyesuaian stok wajib dipilih.');
            }

            if ($type === MedicineStockTransaction::TYPE_ADJUSTMENT
                && ($data['direction'] ?? null) === 'out'
                && (float) $batch->stock + 0.0001 < $quantity) {
                throw new RuntimeException('Jumlah penyesuaian keluar melebihi stok tersedia batch ini.');
            }

            $payload = [
                'reference' => $this->generateReference($clinicId),
                'clinic_id' => $clinicId,
                'medicine_id' => $medicine->id,
                'batch_id' => $batch->id,
                'type' => $type,
                'status' => MedicineStockTransaction::STATUS_PENDING,
                'quantity' => $quantity,
                'reason' => trim((string) ($data['reason'] ?? '')),
                'destination' => $data['destination'] ?? null,
                'notes' => $data['notes'] ?? null,
                'supplier_name' => $data['supplier_name'] ?? null,
                'return_date' => $data['return_date'] ?? null,
                'document_path' => $data['document_path'] ?? null,
                'destruction_date' => $data['destruction_date'] ?? null,
                'destruction_method' => $data['destruction_method'] ?? null,
                'witness_name' => $data['witness_name'] ?? null,
                'minutes_path' => $data['minutes_path'] ?? null,
                'quarantine_location' => $data['quarantine_location'] ?? null,
                'from_quarantine' => $fromQuarantine,
                'source_transaction_id' => $sourceTransactionId,
                'direction' => $type === MedicineStockTransaction::TYPE_ADJUSTMENT
                    ? ($data['direction'] ?? null)
                    : null,
                'created_by' => $userId,
            ];

            return MedicineStockTransaction::create($payload);
        });
    }

    /** pending → processing. Tidak mengubah stok. */
    public function process(MedicineStockTransaction $transaction, User $actor): MedicineStockTransaction
    {
        return DB::transaction(function () use ($transaction, $actor) {
            $locked = MedicineStockTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if (!$locked->canProcess()) {
                throw new RuntimeException(
                    'Transaksi tidak bisa diproses dari status ' . $locked->statusLabel() . '.'
                );
            }

            $locked->forceFill([
                'status' => MedicineStockTransaction::STATUS_PROCESSING,
                'processed_by' => $actor->id,
                'processed_at' => now(),
            ])->save();

            return $locked;
        });
    }

    /**
     * processing → completed. Di sinilah stok berubah, tepat satu kali.
     */
    public function complete(MedicineStockTransaction $transaction, User $actor): MedicineStockTransaction
    {
        return DB::transaction(function () use ($transaction, $actor) {
            $locked = MedicineStockTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if (!$locked->canComplete()) {
                throw new RuntimeException(
                    'Transaksi tidak bisa diselesaikan dari status ' . $locked->statusLabel() . '.'
                );
            }

            $medicine = Medicine::whereKey($locked->medicine_id)->lockForUpdate()->firstOrFail();
            $batch = MedicineBatch::whereKey($locked->batch_id)
                ->where('medicine_id', $medicine->id)
                ->where('clinic_id', $locked->clinic_id)
                ->lockForUpdate()
                ->first();

            if (!$batch) {
                throw new RuntimeException('Batch transaksi sudah tidak tersedia.');
            }

            $quantity = (float) $locked->quantity;

            if ($quantity <= 0) {
                throw new RuntimeException('Jumlah transaksi tidak valid.');
            }

            match ($locked->type) {
                MedicineStockTransaction::TYPE_QUARANTINE => $this->completeQuarantine($locked, $medicine, $batch, $quantity, $actor),
                MedicineStockTransaction::TYPE_RETURN_SUPPLIER => $this->completeReturn($locked, $medicine, $batch, $quantity, $actor),
                MedicineStockTransaction::TYPE_DISPOSAL => $this->completeDisposal($locked, $medicine, $batch, $quantity, $actor),
                MedicineStockTransaction::TYPE_DAMAGED => $this->completeDamaged($locked, $medicine, $batch, $quantity, $actor),
                MedicineStockTransaction::TYPE_ADJUSTMENT => $this->completeAdjustment($locked, $medicine, $batch, $quantity, $actor),
                default => throw new RuntimeException('Jenis mutasi tidak dikenal.'),
            };

            $locked->forceFill([
                'status' => MedicineStockTransaction::STATUS_COMPLETED,
                'completed_by' => $actor->id,
                'completed_at' => now(),
            ])->save();

            $this->stock->syncExpiryMirror($medicine->fresh());

            return $locked;
        });
    }

    /** pending/processing → cancelled. Stok belum berubah, jadi aman. */
    public function cancel(
        MedicineStockTransaction $transaction,
        User $actor,
        string $reason
    ): MedicineStockTransaction {
        return DB::transaction(function () use ($transaction, $actor, $reason) {
            $locked = MedicineStockTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if (!$locked->canCancel()) {
                throw new RuntimeException(
                    'Transaksi berstatus ' . $locked->statusLabel()
                    . ' tidak bisa dibatalkan. Buat transaksi pembalik bila stok sudah berubah.'
                );
            }

            $locked->forceFill([
                'status' => MedicineStockTransaction::STATUS_CANCELLED,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            return $locked;
        });
    }

    /**
     * Pembalik transaksi karantina yang sudah selesai.
     *
     * Retur/pemusnahan yang sudah selesai tidak bisa dibalik karena barang
     * sudah keluar dari klinik; yang bisa dibalik hanyalah pemisahan fisik
     * (karantina) yang ternyata tidak jadi dilakukan. Pembalik tetap tercatat
     * sebagai transaksi baru, bukan dengan mengedit transaksi lama.
     */
    public function reverse(MedicineStockTransaction $transaction, User $actor, string $reason): MedicineStockTransaction
    {
        return DB::transaction(function () use ($transaction, $actor, $reason) {
            $locked = MedicineStockTransaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if (!$locked->canReverse()) {
                throw new RuntimeException('Transaksi ini tidak bisa dibalik.');
            }

            $medicine = Medicine::whereKey($locked->medicine_id)->lockForUpdate()->firstOrFail();
            $batch = MedicineBatch::whereKey($locked->batch_id)->lockForUpdate()->firstOrFail();
            $quantity = (float) $locked->quantity;

            if ((float) $batch->quarantined_stock + 0.0001 < $quantity) {
                throw new RuntimeException(
                    'Karantina sudah sebagian ditindaklanjuti sehingga tidak bisa dibalik seluruhnya.'
                );
            }

            $reversal = MedicineStockTransaction::create([
                'reference' => $this->generateReference($locked->clinic_id),
                'clinic_id' => $locked->clinic_id,
                'medicine_id' => $locked->medicine_id,
                'batch_id' => $locked->batch_id,
                'type' => MedicineStockTransaction::TYPE_QUARANTINE,
                'status' => MedicineStockTransaction::STATUS_COMPLETED,
                'quantity' => $quantity,
                'reason' => 'Pembalik karantina ' . $locked->reference . ': ' . $reason,
                'notes' => $reason,
                'from_quarantine' => true,
                'source_transaction_id' => $locked->id,
                'direction' => 'reverse',
                'created_by' => $actor->id,
                'processed_by' => $actor->id,
                'completed_by' => $actor->id,
                'processed_at' => now(),
                'completed_at' => now(),
            ]);

            // quarantine −q, available +q.
            $this->stock->record(
                $medicine,
                -$quantity,
                MedicineStockMovement::TYPE_QUARANTINE,
                'Pembalik karantina ' . $locked->reference,
                null,
                $actor->id,
                $batch->id,
                MedicineStockMovement::STOCK_STATE_QUARANTINE,
                $reversal->id
            );

            $this->stock->record(
                $medicine,
                $quantity,
                MedicineStockMovement::TYPE_QUARANTINE,
                'Pembalik karantina ' . $locked->reference,
                null,
                $actor->id,
                $batch->id,
                MedicineStockMovement::STOCK_STATE_AVAILABLE,
                $reversal->id
            );

            $this->stock->syncExpiryMirror($medicine->fresh());

            return $reversal;
        });
    }

    private function completeQuarantine(
        MedicineStockTransaction $transaction,
        Medicine $medicine,
        MedicineBatch $batch,
        float $quantity,
        User $actor
    ): void {
        if ((float) $batch->stock + 0.0001 < $quantity) {
            throw new RuntimeException(
                "Stok tersedia batch {$batch->batch_number} tidak cukup untuk dikarantina."
            );
        }

        $this->stock->record(
            $medicine,
            -$quantity,
            MedicineStockMovement::TYPE_QUARANTINE,
            $transaction->reason,
            null,
            $actor->id,
            $batch->id,
            MedicineStockMovement::STOCK_STATE_AVAILABLE,
            $transaction->id
        );

        $this->stock->record(
            $medicine,
            $quantity,
            MedicineStockMovement::TYPE_QUARANTINE,
            $transaction->reason,
            null,
            $actor->id,
            $batch->id,
            MedicineStockMovement::STOCK_STATE_QUARANTINE,
            $transaction->id
        );
    }

    private function completeReturn(
        MedicineStockTransaction $transaction,
        Medicine $medicine,
        MedicineBatch $batch,
        float $quantity,
        User $actor
    ): void {
        $state = $transaction->from_quarantine
            ? MedicineStockMovement::STOCK_STATE_QUARANTINE
            : MedicineStockMovement::STOCK_STATE_AVAILABLE;

        $available = $transaction->from_quarantine
            ? (float) $batch->quarantined_stock
            : (float) $batch->stock;

        if ($available + 0.0001 < $quantity) {
            throw new RuntimeException('Stok batch tidak cukup untuk diretur ke supplier.');
        }

        $this->stock->record(
            $medicine,
            -$quantity,
            MedicineStockMovement::TYPE_RETURN_SUPPLIER,
            $transaction->reason,
            null,
            $actor->id,
            $batch->id,
            $state,
            $transaction->id
        );
    }

    private function completeDisposal(
        MedicineStockTransaction $transaction,
        Medicine $medicine,
        MedicineBatch $batch,
        float $quantity,
        User $actor
    ): void {
        if (!$transaction->from_quarantine) {
            throw new RuntimeException('Pemusnahan hanya boleh dari stok karantina.');
        }

        if ((float) $batch->quarantined_stock + 0.0001 < $quantity) {
            throw new RuntimeException('Stok karantina batch tidak cukup untuk dimusnahkan.');
        }

        $this->stock->record(
            $medicine,
            -$quantity,
            MedicineStockMovement::TYPE_DISPOSAL,
            $transaction->reason,
            null,
            $actor->id,
            $batch->id,
            MedicineStockMovement::STOCK_STATE_QUARANTINE,
            $transaction->id
        );

        // Bila seluruh stok fisik obat sudah habis, sinkronkan penanda lama
        // (`disposed_at`) supaya halaman berita acara pemusnahan yang sudah ada
        // tetap menampilkan obat ini. Bila masih ada batch lain, obat tetap aktif.
        $medicine->refresh();

        if ((float) $medicine->stock <= 0 && (float) $medicine->quarantined_stock <= 0) {
            $medicine->forceFill([
                'disposed_at' => now(),
                'disposal_method' => Medicine::DISPOSAL_DESTROYED,
                'disposal_reason' => $transaction->reason,
                'disposed_quantity' => $quantity,
                'is_active' => false,
            ])->save();
        }
    }

    private function completeDamaged(
        MedicineStockTransaction $transaction,
        Medicine $medicine,
        MedicineBatch $batch,
        float $quantity,
        User $actor
    ): void {
        if ((float) $batch->stock + 0.0001 < $quantity) {
            throw new RuntimeException('Stok tersedia batch tidak cukup untuk obat rusak.');
        }

        $this->stock->record(
            $medicine,
            -$quantity,
            MedicineStockMovement::TYPE_DAMAGED,
            $transaction->reason,
            null,
            $actor->id,
            $batch->id,
            MedicineStockMovement::STOCK_STATE_AVAILABLE,
            $transaction->id
        );
    }

    private function completeAdjustment(
        MedicineStockTransaction $transaction,
        Medicine $medicine,
        MedicineBatch $batch,
        float $quantity,
        User $actor
    ): void {
        $direction = $transaction->direction;

        if (!in_array($direction, ['in', 'out'], true)) {
            throw new RuntimeException('Arah penyesuaian tidak valid.');
        }

        if ($direction === 'out' && (float) $batch->stock + 0.0001 < $quantity) {
            throw new RuntimeException('Stok tersedia batch tidak cukup untuk penyesuaian keluar.');
        }

        $this->stock->record(
            $medicine,
            $direction === 'in' ? $quantity : -$quantity,
            MedicineStockMovement::TYPE_ADJUSTMENT,
            $transaction->reason,
            null,
            $actor->id,
            $batch->id,
            MedicineStockMovement::STOCK_STATE_AVAILABLE,
            $transaction->id
        );
    }

    private function generateReference(int $clinicId): string
    {
        $prefix = 'MUT-' . now()->format('Ymd') . '-';

        $last = MedicineStockTransaction::where('clinic_id', $clinicId)
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('reference');

        $sequence = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
