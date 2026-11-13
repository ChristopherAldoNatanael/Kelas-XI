<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineStockTransaction;
use App\Services\MedicineMutationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Monitoring kedaluwarsa dan dokumen mutasi stok obat.
 *
 * Semua query dibatasi `clinic_id` konteks aktif. `clinic_id` tidak pernah
 * diambil dari input request, jadi menebak ID transaksi klinik lain berakhir
 * 404 — bukan kebocoran data.
 */
class MedicineStockController extends Controller
{
    public const DESTRUCTION_METHODS = ['incineration', 'chemical', 'burial', 'other'];

    public function __construct(private readonly MedicineMutationService $mutations)
    {
    }

    private function clinicId(): int
    {
        $clinicId = currentClinicId();

        if (!$clinicId) {
            // super_admin boleh lintas klinik, tetapi inventaris selalu milik
            // satu klinik. Arahkan ke pemilih klinik di dashboard, bukan 403.
            throw new HttpResponseException(
                redirect()->route('admin.dashboard')->with('error', __('inventory.select_clinic_first'))
            );
        }

        return $clinicId;
    }

    private function scopedTransactions()
    {
        return MedicineStockTransaction::query()->where('clinic_id', $this->clinicId());
    }

    private function findTransaction(int $id): MedicineStockTransaction
    {
        return $this->scopedTransactions()
            ->with(['medicine', 'batch', 'creator', 'processor', 'completer', 'canceller', 'sourceTransaction'])
            ->whereKey($id)
            ->firstOrFail();
    }

    /** Monitoring kedaluwarsa per batch. */
    public function expiry(Request $request)
    {
        $clinicId = $this->clinicId();
        $warningDays = medicine_expiry_warning_days();

        $base = MedicineBatch::query()->where('clinic_id', $clinicId);

        $query = (clone $base)->with('medicine');

        $status = (string) $request->query('status', '');
        if ($status === 'expired') {
            $query->expired()->where('stock', '>', 0);
        } elseif ($status === 'expiring') {
            $query->expiringSoon($warningDays)->where('stock', '>', 0);
        } elseif ($status === 'quarantine') {
            $query->withQuarantineStock();
        } elseif ($status === 'safe') {
            $query->where('stock', '>', 0)
                ->notExpired()
                ->where(function ($q) use ($warningDays) {
                    $q->whereNull('expiry_date')
                        ->orWhereDate('expiry_date', '>', today()->addDays($warningDays));
                });
        } else {
            // Default: yang perlu perhatian (ada stok tersedia atau karantina).
            $query->where(function ($q) {
                $q->where('stock', '>', 0)->orWhere('quarantined_stock', '>', 0);
            });
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->where(function ($q) use ($term) {
                $q->where('batch_number', 'like', '%' . $term . '%')
                    ->orWhereHas('medicine', fn ($m) => $m->where('name', 'like', '%' . $term . '%'));
            });
        }

        $sort = (string) $request->query('sort', 'fefo');
        match ($sort) {
            'expiry_desc' => $query->orderByRaw('expiry_date IS NULL')->orderByDesc('expiry_date'),
            'name' => $query->orderBy(
                Medicine::select('name')->whereColumn('medicines.id', 'medicine_batches.medicine_id')
            ),
            default => $query->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date'),
        };

        return view('admin.medicines.expiry', [
            'batches' => $query->paginate(20)->withQueryString(),
            'warningDays' => $warningDays,
            'status' => $status,
            'sort' => $sort,
            'expiringCount' => (clone $base)->where('stock', '>', 0)->expiringSoon($warningDays)->count(),
            'expiredCount' => (clone $base)->where('stock', '>', 0)->expired()->count(),
            'quarantineQuantity' => (float) (clone $base)->sum('quarantined_stock'),
            'quarantineBatchCount' => (clone $base)->withQuarantineStock()->count(),
            'openMutationCount' => $this->scopedTransactions()->open()->count(),
        ]);
    }

    /** Riwayat mutasi (semua jenis, semua status). */
    public function mutations(Request $request)
    {
        $query = $this->scopedTransactions()
            ->with(['medicine', 'batch', 'creator', 'completer'])
            ->latest('id');

        if ($request->filled('status') && in_array($request->query('status'), MedicineStockTransaction::STATUSES, true)) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('type') && in_array($request->query('type'), MedicineStockTransaction::TYPES, true)) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('medicine_id')) {
            $query->where('medicine_id', (int) $request->query('medicine_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        if ($request->filled('q')) {
            $query->search(trim((string) $request->query('q')));
        }

        $medicines = Medicine::where('clinic_id', $this->clinicId())
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.medicines.mutations.index', [
            'transactions' => $query->paginate(20)->withQueryString(),
            'medicines' => $medicines,
            'openCount' => $this->scopedTransactions()->open()->count(),
        ]);
    }

    /** Form mutasi baru. */
    public function createMutation(Request $request)
    {
        $clinicId = $this->clinicId();

        $medicines = Medicine::where('clinic_id', $clinicId)
            ->with(['batches' => fn ($q) => $q->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date')])
            ->orderBy('name')
            ->get();

        $source = null;
        if ($request->filled('source_transaction_id')) {
            $source = $this->scopedTransactions()
                ->whereKey((int) $request->query('source_transaction_id'))
                ->where('type', MedicineStockTransaction::TYPE_QUARANTINE)
                ->where('status', MedicineStockTransaction::STATUS_COMPLETED)
                ->with(['medicine', 'batch'])
                ->first();

            if (!$source || !$source->needsFollowUp()) {
                return redirect()->route('admin.medicines.mutations.index')
                    ->with('error', __('inventory.source_invalid'));
            }
        }

        return view('admin.medicines.mutations.create', [
            'medicines' => $medicines,
            'batchesData' => $medicines->flatMap(fn (Medicine $medicine) => $medicine->batches->map(
                fn (MedicineBatch $batch) => [
                    'id' => $batch->id,
                    'medicine_id' => $batch->medicine_id,
                    'batch_number' => $batch->batch_number,
                    'expiry_date' => $batch->expiryDateValue(),
                    'expiry_status' => $batch->expiryStatus(),
                    'stock' => (float) $batch->stock,
                    'quarantined_stock' => (float) $batch->quarantined_stock,
                ]
            ))->values(),
            'source' => $source,
            'destructionMethods' => self::DESTRUCTION_METHODS,
        ]);
    }

    public function storeMutation(Request $request)
    {
        $clinicId = $this->clinicId();

        $maxKb = (int) config('medicines.document_max_kb', 4096);

        $validated = $request->validate([
            'type' => ['required', Rule::in(MedicineStockTransaction::TYPES)],
            'medicine_id' => ['required', 'integer'],
            'batch_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'supplier_name' => ['required_if:type,' . MedicineStockTransaction::TYPE_RETURN_SUPPLIER, 'nullable', 'string', 'max:150'],
            'return_date' => ['required_if:type,' . MedicineStockTransaction::TYPE_RETURN_SUPPLIER, 'nullable', 'date'],
            'destruction_date' => ['required_if:type,' . MedicineStockTransaction::TYPE_DISPOSAL, 'nullable', 'date'],
            'destruction_method' => ['required_if:type,' . MedicineStockTransaction::TYPE_DISPOSAL, 'nullable', Rule::in(self::DESTRUCTION_METHODS)],
            'witness_name' => ['required_if:type,' . MedicineStockTransaction::TYPE_DISPOSAL, 'nullable', 'string', 'max:150'],
            'quarantine_location' => ['required_if:type,' . MedicineStockTransaction::TYPE_QUARANTINE, 'nullable', 'string', 'max:100'],
            'direction' => ['required_if:type,' . MedicineStockTransaction::TYPE_ADJUSTMENT, 'nullable', Rule::in(['in', 'out'])],
            'from_quarantine' => ['nullable', 'boolean'],
            'source_transaction_id' => ['nullable', 'integer'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:' . $maxKb],
            'minutes' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:' . $maxKb],
        ]);

        // Pastikan obat & batch memang milik klinik aktif sebelum file disimpan.
        Medicine::whereKey($validated['medicine_id'])
            ->where('clinic_id', $clinicId)
            ->firstOrFail();

        MedicineBatch::whereKey($validated['batch_id'])
            ->where('clinic_id', $clinicId)
            ->firstOrFail();

        $documentPath = null;
        $minutesPath = null;

        try {
            if ($request->hasFile('document')) {
                $documentPath = $request->file('document')->store(
                    config('medicines.document_dir', 'medicine-mutations'),
                    config('medicines.document_disk', 'public')
                );
            }

            if ($request->hasFile('minutes')) {
                $minutesPath = $request->file('minutes')->store(
                    config('medicines.document_dir', 'medicine-mutations'),
                    config('medicines.document_disk', 'public')
                );
            }

            $transaction = $this->mutations->create([
                ...$validated,
                'document_path' => $documentPath,
                'minutes_path' => $minutesPath,
                'from_quarantine' => $request->boolean('from_quarantine')
                    || $request->filled('source_transaction_id')
                    || $request->input('type') === MedicineStockTransaction::TYPE_DISPOSAL,
            ], $clinicId, auth()->id());
        } catch (RuntimeException $e) {
            if ($documentPath) {
                Storage::disk(config('medicines.document_disk', 'public'))->delete($documentPath);
            }
            if ($minutesPath) {
                Storage::disk(config('medicines.document_disk', 'public'))->delete($minutesPath);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }

        AuditLog::log(
            'medicine.mutation_created',
            "Created stock mutation {$transaction->reference} ({$transaction->type}) qty {$transaction->quantity}",
            $transaction
        );

        return redirect()->route('admin.medicines.mutations.show', $transaction->id)
            ->with('success', __('inventory.created'));
    }

    public function showMutation(int $id)
    {
        $transaction = $this->findTransaction($id);

        return view('admin.medicines.mutations.show', [
            'transaction' => $transaction,
            'movements' => $transaction->movements()->with('batch')->oldest('id')->get(),
            'followUps' => $transaction->followUps()
                ->with(['creator', 'completer'])
                ->latest('id')
                ->get(),
        ]);
    }

    public function processMutation(int $id)
    {
        $transaction = $this->findTransaction($id);

        try {
            $this->mutations->process($transaction, auth()->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::log('medicine.mutation_processed', "Processed mutation {$transaction->reference}", $transaction);

        return back()->with('success', __('inventory.processed'));
    }

    public function completeMutation(int $id)
    {
        $transaction = $this->findTransaction($id);

        try {
            $this->mutations->complete($transaction, auth()->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::log('medicine.mutation_completed', "Completed mutation {$transaction->reference}", $transaction);

        return back()->with('success', __('inventory.completed'));
    }

    public function cancelMutation(Request $request, int $id)
    {
        $transaction = $this->findTransaction($id);

        $validated = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:500'],
        ], [
            'cancel_reason.required' => __('inventory.cancel_reason_required'),
        ]);

        try {
            $this->mutations->cancel($transaction, auth()->user(), $validated['cancel_reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::log('medicine.mutation_cancelled', "Cancelled mutation {$transaction->reference}", $transaction);

        return back()->with('success', __('inventory.cancelled'));
    }

    public function reverseMutation(Request $request, int $id)
    {
        $transaction = $this->findTransaction($id);

        $validated = $request->validate([
            'reverse_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $reversal = $this->mutations->reverse($transaction, auth()->user(), $validated['reverse_reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::log('medicine.mutation_reversed', "Reversed mutation {$transaction->reference}", $transaction);

        return redirect()->route('admin.medicines.mutations.show', $reversal->id)
            ->with('success', __('inventory.reversed'));
    }

    /** Unduh bukti retur / berita acara milik klinik sendiri. */
    public function downloadDocument(int $id, string $field)
    {
        $transaction = $this->findTransaction($id);

        if (!in_array($field, ['document_path', 'minutes_path'], true)) {
            abort(404);
        }

        $path = $transaction->{$field};

        if (!$path) {
            abort(404);
        }

        $disk = Storage::disk(config('medicines.document_disk', 'public'));

        if (!$disk->exists($path)) {
            abort(404);
        }

        return $disk->download($path);
    }
}
