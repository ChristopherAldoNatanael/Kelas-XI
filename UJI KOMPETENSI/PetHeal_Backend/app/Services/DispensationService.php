<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\Dispensation;
use App\Models\DispensationItem;
use App\Models\MedicalRecord;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Penyerahan obat dan reversal.
 *
 * Idempotency key mencegah double submit. Stok selalu berubah lewat
 * MedicineStockService; barang asli tidak dihapus saat dikoreksi.
 */
class DispensationService
{
    public function __construct(private readonly MedicineStockService $stock)
    {
    }

    public function dispense(
        Prescription $prescription,
        array $items,
        string $idempotencyKey,
        ?User $actor = null
    ): Dispensation {
        $idempotencyKey = trim($idempotencyKey);

        if ($idempotencyKey === '' || strlen($idempotencyKey) > 80) {
            throw new MedicalRecordLifecycleException(
                'IDEMPOTENCY_KEY_REQUIRED',
                'Idempotency-Key wajib diisi (maksimal 80 karakter).',
                422
            );
        }

        $existing = Dispensation::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing->load('items');
        }

        return DB::transaction(function () use ($prescription, $items, $idempotencyKey, $actor) {
            $locked = Prescription::whereKey($prescription->id)->lockForUpdate()->firstOrFail();

            $existing = Dispensation::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing->load('items');
            }

            if ($locked->status === Prescription::STATUS_CANCELLED) {
                throw new MedicalRecordLifecycleException(
                    'PRESCRIPTION_CANCELLED',
                    'Resep yang dibatalkan tidak dapat diserahkan.',
                    409
                );
            }

            if ($items === []) {
                throw new MedicalRecordLifecycleException(
                    'DISPENSATION_EMPTY',
                    'Tidak ada item yang diserahkan.',
                    422
                );
            }

            $dispensation = Dispensation::create([
                'clinic_id' => $locked->clinic_id,
                'prescription_id' => $locked->id,
                'dispensation_number' => $this->number('DSP', $locked->clinic_id),
                'dispensed_by' => $actor?->id,
                'dispensed_at' => now(),
                'kind' => Dispensation::KIND_DISPENSE,
                'status' => Dispensation::STATUS_COMPLETED,
                'idempotency_key' => $idempotencyKey,
                'notes' => 'Dispensing dibuat oleh DispensationService.',
            ]);

            foreach ($items as $row) {
                $prescriptionItemId = (int) ($row['prescription_item_id'] ?? 0);
                $item = PrescriptionItem::whereKey($prescriptionItemId)
                    ->where('prescription_id', $locked->id)
                    ->lockForUpdate()
                    ->first();

                if (! $item) {
                    throw new MedicalRecordLifecycleException(
                        'PRESCRIPTION_ITEM_NOT_FOUND',
                        'Item resep tidak ditemukan pada resep ini.',
                        404
                    );
                }

                $remaining = $this->remainingQuantity($item);
                $quantity = isset($row['quantity']) && $row['quantity'] !== ''
                    ? (float) $row['quantity']
                    : $remaining;

                if ($remaining <= 0.0001) {
                    throw new MedicalRecordLifecycleException(
                        'DISPENSATION_EXCEEDS_REMAINING',
                        'Item resep ini sudah diserahkan seluruhnya.',
                        422
                    );
                }

                if ($quantity <= 0.0001) {
                    throw new MedicalRecordLifecycleException(
                        'DISPENSATION_QUANTITY_INVALID',
                        'Jumlah penyerahan harus lebih dari 0.',
                        422
                    );
                }

                if ($quantity - 0.0001 > $remaining) {
                    throw new MedicalRecordLifecycleException(
                        'DISPENSATION_EXCEEDS_REMAINING',
                        "Jumlah yang diserahkan melebihi sisa resep ({$remaining}).",
                        422
                    );
                }

                $medicine = Medicine::whereKey($item->medicine_id)
                    ->where('clinic_id', $locked->clinic_id)
                    ->lockForUpdate()
                    ->first();

                if (! $medicine) {
                    throw new MedicalRecordLifecycleException(
                        'MEDICINE_NOT_FOUND',
                        'Obat pada item resep tidak ditemukan di klinik ini.',
                        404
                    );
                }

                $registrationStatus = $this->registrationStatus($medicine);
                $overrideReason = trim((string) ($row['eligibility_override_reason'] ?? ''));

                if ($registrationStatus !== Medicine::REGISTRATION_REGISTERED
                    && ! ($overrideReason !== '' && $actor?->role === 'doctor')) {
                    throw new MedicalRecordLifecycleException(
                        'MEDICINE_NOT_ELIGIBLE',
                        "Obat {$medicine->name} tidak dapat diserahkan: status registrasi {$registrationStatus}.",
                        422,
                        ['registration_status' => $registrationStatus]
                    );
                }

                $batches = $this->batchesFor($medicine, $row['batch_id'] ?? null);
                $available = (float) $batches->sum('stock');

                if ($batches->isNotEmpty() && $available + 0.0001 < $quantity) {
                    throw new MedicalRecordLifecycleException(
                        'MEDICINE_STOCK_INSUFFICIENT',
                        "Stok layak pakai {$medicine->name} tidak cukup untuk diserahkan.",
                        422
                    );
                }

                $remainingToTake = $quantity;

                if ($batches->isEmpty()) {
                    $dispensationItem = $this->createItem(
                        $dispensation,
                        $item,
                        $medicine,
                        null,
                        $remainingToTake,
                        $registrationStatus,
                        $overrideReason
                    );
                    $this->recordMovement($dispensation, $dispensationItem, $medicine, null, $remainingToTake, $idempotencyKey);
                    continue;
                }

                foreach ($batches as $batch) {
                    if ($remainingToTake <= 0.0001) {
                        break;
                    }

                    $take = min((float) $batch->stock, $remainingToTake);
                    if ($take <= 0) {
                        continue;
                    }

                    $dispensationItem = $this->createItem(
                        $dispensation,
                        $item,
                        $medicine,
                        $batch,
                        $take,
                        $registrationStatus,
                        $overrideReason
                    );
                    $this->recordMovement($dispensation, $dispensationItem, $medicine, $batch, $take, $idempotencyKey);
                    $remainingToTake -= $take;
                }
            }

            $this->refreshPrescriptionStatus($locked);

            return $dispensation->load('items');
        });
    }

    public function reverse(
        Dispensation $dispensation,
        array $items,
        string $idempotencyKey,
        string $reason,
        ?User $actor = null
    ): Dispensation {
        $idempotencyKey = trim($idempotencyKey);

        if ($idempotencyKey === '') {
            throw new MedicalRecordLifecycleException(
                'IDEMPOTENCY_KEY_REQUIRED',
                'Idempotency-Key wajib diisi.',
                422
            );
        }

        if (trim($reason) === '') {
            throw new MedicalRecordLifecycleException(
                'REVERSAL_REASON_REQUIRED',
                'Alasan reversal wajib diisi.',
                422
            );
        }

        $existing = Dispensation::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing->load('items');
        }

        return DB::transaction(function () use ($dispensation, $items, $idempotencyKey, $reason, $actor) {
            $original = Dispensation::whereKey($dispensation->id)->lockForUpdate()->firstOrFail();

            if ($original->kind !== Dispensation::KIND_DISPENSE) {
                throw new MedicalRecordLifecycleException(
                    'REVERSAL_TARGET_INVALID',
                    'Hanya dispensasi penyerahan yang dapat dibalik.',
                    422
                );
            }

            if ($original->status === Dispensation::STATUS_REVERSED) {
                throw new MedicalRecordLifecycleException(
                    'DISPENSATION_ALREADY_REVERSED',
                    'Dispensasi ini sudah dibalik seluruhnya.',
                    409
                );
            }

            $existing = Dispensation::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing->load('items');
            }

            $reversal = Dispensation::create([
                'clinic_id' => $original->clinic_id,
                'prescription_id' => $original->prescription_id,
                'dispensation_number' => $this->number('REV', $original->clinic_id),
                'dispensed_by' => $actor?->id,
                'dispensed_at' => now(),
                'kind' => Dispensation::KIND_REVERSAL,
                'status' => Dispensation::STATUS_COMPLETED,
                'idempotency_key' => $idempotencyKey,
                'reversal_of_id' => $original->id,
                'reason' => $reason,
            ]);

            $requested = collect($items)->keyBy('dispensation_item_id');

            foreach ($original->items()->lockForUpdate()->get() as $originalItem) {
                $alreadyReversed = (float) DispensationItem::where('reversal_of_item_id', $originalItem->id)
                    ->whereHas('dispensation', fn ($q) => $q->where('kind', Dispensation::KIND_REVERSAL))
                    ->sum('reversed_quantity');

                $remaining = (float) $originalItem->quantity - $alreadyReversed;

                if ($remaining <= 0.0001) {
                    continue;
                }

                $row = $requested->get($originalItem->id);
                $quantity = $row && isset($row['quantity']) && $row['quantity'] !== ''
                    ? (float) $row['quantity']
                    : $remaining;

                if ($quantity <= 0 || $quantity - 0.0001 > $remaining) {
                    throw new MedicalRecordLifecycleException(
                        'REVERSAL_QUANTITY_INVALID',
                        'Jumlah reversal melebihi sisa yang belum dibalik.',
                        422
                    );
                }

                $medicine = Medicine::whereKey($originalItem->medicine_id)
                    ->where('clinic_id', $original->clinic_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $reversalItem = DispensationItem::create([
                    'clinic_id' => $original->clinic_id,
                    'dispensation_id' => $reversal->id,
                    'prescription_item_id' => $originalItem->prescription_item_id,
                    'medicine_id' => $originalItem->medicine_id,
                    'batch_id' => $originalItem->batch_id,
                    'quantity' => $quantity,
                    'unit' => $originalItem->unit,
                    'medicine_name' => $originalItem->medicine_name,
                    'batch_number' => $originalItem->batch_number,
                    'expiry_date' => $originalItem->expiry_date,
                    'unit_price' => $originalItem->unit_price,
                    'reversal_of_item_id' => $originalItem->id,
                    'original_batch_id' => $originalItem->batch_id,
                    'reversed_quantity' => $quantity,
                    'registration_status_snapshot' => $originalItem->registration_status_snapshot,
                ]);

                $movement = $this->stock->record(
                    $medicine,
                    $quantity,
                    \App\Models\MedicineStockMovement::TYPE_RETURN,
                    "Reversal dispensasi #{$original->dispensation_number}: {$reason}",
                    $original->prescription?->medical_record_id,
                    $actor?->id,
                    $originalItem->batch_id ? (int) $originalItem->batch_id : null,
                    \App\Models\MedicineStockMovement::STOCK_STATE_AVAILABLE,
                    null,
                    $reversalItem->id,
                    'dispensation',
                    $reversal->id,
                    'movement:' . $idempotencyKey . ':' . $reversalItem->id,
                    $originalItem->stock_movement_id,
                    'reversal',
                    $reason
                );

                $reversalItem->forceFill(['stock_movement_id' => $movement->id])->save();
                $originalItem->forceFill([
                    'reversed_quantity' => $alreadyReversed + $quantity,
                ])->save();
            }

            $originalTotal = (float) $original->items()->sum('quantity');
            $reversedTotal = (float) DispensationItem::whereIn(
                'reversal_of_item_id',
                $original->items()->pluck('id')->all()
            )->sum('reversed_quantity');

            $original->forceFill([
                'status' => $reversedTotal + 0.0001 >= $originalTotal
                    ? Dispensation::STATUS_REVERSED
                    : Dispensation::STATUS_PARTIALLY_REVERSED,
            ])->save();

            $this->refreshPrescriptionStatus($original->prescription);

            return $reversal->load('items');
        });
    }

    private function createItem(
        Dispensation $dispensation,
        PrescriptionItem $item,
        Medicine $medicine,
        ?MedicineBatch $batch,
        float $quantity,
        string $registrationStatus,
        string $overrideReason
    ): DispensationItem {
        return DispensationItem::create([
            'clinic_id' => $dispensation->clinic_id,
            'dispensation_id' => $dispensation->id,
            'prescription_item_id' => $item->id,
            'medicine_id' => $medicine->id,
            'batch_id' => $batch?->id,
            'quantity' => $quantity,
            'unit' => $item->unit ?: $medicine->unit,
            'medicine_name' => $item->medicine_name ?: $medicine->name,
            'batch_number' => $batch?->batch_number,
            'expiry_date' => $batch?->expiry_date,
            'unit_price' => $item->unit_price,
            'registration_status_snapshot' => $registrationStatus,
            'eligibility_override_reason' => $overrideReason ?: null,
        ]);
    }

    private function recordMovement(
        Dispensation $dispensation,
        DispensationItem $item,
        Medicine $medicine,
        ?MedicineBatch $batch,
        float $quantity,
        string $idempotencyKey
    ): void {
        $movement = $this->stock->record(
            $medicine,
            -$quantity,
            \App\Models\MedicineStockMovement::TYPE_OUT,
            "Dispensasi resep #{$dispensation->prescription_id}",
            $dispensation->prescription?->medical_record_id,
            $dispensation->dispensed_by,
            $batch?->id,
            \App\Models\MedicineStockMovement::STOCK_STATE_AVAILABLE,
            null,
            $item->id,
            'dispensation',
            $dispensation->id,
            'movement:' . $idempotencyKey . ':' . $item->id,
            null,
            'dispense',
            null
        );

        $item->forceFill(['stock_movement_id' => $movement->id])->save();
    }

    private function remainingQuantity(PrescriptionItem $item): float
    {
        $dispensed = (float) DispensationItem::where('prescription_item_id', $item->id)
            ->whereHas('dispensation', fn ($q) => $q->where('kind', Dispensation::KIND_DISPENSE))
            ->sum('quantity');

        $reversed = (float) DispensationItem::where('prescription_item_id', $item->id)
            ->whereHas('dispensation', fn ($q) => $q->where('kind', Dispensation::KIND_REVERSAL))
            ->sum('reversed_quantity');

        return max(0, (float) $item->quantity - ($dispensed - $reversed));
    }

    private function refreshPrescriptionStatus(Prescription $prescription): void
    {
        $items = $prescription->items()->get();

        if ($items->isEmpty()) {
            return;
        }

        $net = [];
        foreach ($items as $item) {
            $net[$item->id] = (float) $item->quantity - $this->remainingQuantity($item);
        }

        $any = collect($net)->contains(fn ($value) => $value > 0.0001);
        $all = $items->every(function (PrescriptionItem $item) use ($net) {
            return $net[$item->id] + 0.0001 >= (float) $item->quantity;
        });

        $status = $all
            ? Prescription::STATUS_DISPENSED
            : ($any ? Prescription::STATUS_PARTIALLY_DISPENSED : Prescription::STATUS_ISSUED);

        $prescription->forceFill(['status' => $status])->save();

        if ($all) {
            $prescription->items()->get()->each(function (PrescriptionItem $item) {
                if (! $item->dispensed_at) {
                    $item->forceFill(['dispensed_at' => now()])->save();
                }
            });
        }
    }

    private function batchesFor(Medicine $medicine, mixed $batchId)
    {
        $query = MedicineBatch::where('medicine_id', $medicine->id)
            ->where('clinic_id', $medicine->clinic_id)
            ->where('stock', '>', 0)
            ->where(function ($q) {
                $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', today());
            });

        if ($batchId) {
            $query->whereKey((int) $batchId);
        }

        return $query->orderByRaw('expiry_date IS NULL ASC')
            ->orderBy('expiry_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function registrationStatus(Medicine $medicine): string
    {
        $status = $medicine->registration_status ?: Medicine::REGISTRATION_UNKNOWN;

        if ($status === Medicine::REGISTRATION_REGISTERED
            && $medicine->registration_expires_at
            && $medicine->registration_expires_at->lt(today())) {
            return Medicine::REGISTRATION_EXPIRED;
        }

        return $status;
    }

    private function number(string $prefix, int $clinicId): string
    {
        do {
            $number = sprintf('%s-%d-%s-%s', $prefix, $clinicId, now()->format('Ymd'), Str::upper(Str::random(6)));
        } while (Dispensation::where('clinic_id', $clinicId)->where('dispensation_number', $number)->exists());

        return $number;
    }
}
