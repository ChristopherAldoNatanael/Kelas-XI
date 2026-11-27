<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordAmendment;
use App\Models\MedicalRecordExport;
use App\Services\AuditTrailService;
use App\Services\MedicalRecordExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Portal pemilik: ringkasan amendment dan salinan rekam medis.
 *
 * Serializer pemilik bersifat whitelist. Endpoint ini tidak pernah mengirim
 * `before`, `after`, `signature_path`, snapshot kredensial, atau catatan audit.
 */
class OwnerMedicalRecordPortalController extends Controller
{
    public function __construct(
        private readonly MedicalRecordExportService $exports,
        private readonly AuditTrailService $audit
    ) {
    }

    public function amendments(Request $request, int $id)
    {
        $record = $this->ownedRecord($request, $id);

        if (! $record) {
            return $this->notFound('Medical record not found');
        }

        $amendments = MedicalRecordAmendment::where('medical_record_id', $record->id)
            ->with('appliedVersion:id,version_no')
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MedicalRecordAmendment $amendment) => [
                'id' => $amendment->id,
                'type' => $amendment->amendment_type,
                'status' => $amendment->status,
                'requested_at' => $amendment->requested_at?->toDateTimeString(),
                'reviewed_at' => $amendment->reviewed_at?->toDateTimeString(),
                'applied_at' => $amendment->applied_at?->toDateTimeString(),
                'applied_version_no' => $amendment->appliedVersion?->version_no,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $amendments,
        ]);
    }

    /**
     * GET /api/medical-records/{id}/export?format=pdf
     *
     * Membuat salinan pemilik (owner copy) dan mengembalikan token sekali pakai.
     * Format resmi lain (insurance/legal) tetap memerlukan tanda tangan dokter.
     */
    public function createExport(Request $request, int $id)
    {
        $format = strtolower((string) $request->query('format', MedicalRecordExport::FORMAT_PDF));

        if ($format !== MedicalRecordExport::FORMAT_PDF) {
            return response()->json([
                'success' => false,
                'message' => 'Salinan pemilik saat ini hanya tersedia dalam format PDF.',
                'code' => 'OWNER_EXPORT_FORMAT_UNSUPPORTED',
            ], 422);
        }

        $record = $this->ownedRecord($request, $id, [
            'pet.allergies', 'doctor', 'clinic', 'prescriptionItems', 'vitals',
            'currentVersion', 'amendments.appliedVersion',
        ]);

        if (! $record) {
            return $this->notFound('Medical record not found');
        }

        if ($record->status !== MedicalRecord::STATUS_FINAL) {
            return response()->json([
                'success' => false,
                'message' => 'Salinan hanya tersedia untuk rekam medis yang sudah final.',
                'code' => 'RECORD_NOT_FINAL',
            ], 409);
        }

        $created = null;

        try {
            $created = $this->exports->create($record, [
                'format' => MedicalRecordExport::FORMAT_PDF,
                'purpose' => MedicalRecordExport::PURPOSE_OWNER_COPY,
                'recipient_type' => 'owner',
                'recipient_name' => $request->user()->name,
                'ttl_minutes' => 15,
            ], $request->user());

            $export = $created['export'];
            $vitals = $record->vitals;
            $amendments = $record->amendments
                ->map(fn (MedicalRecordAmendment $amendment) => (object) [
                    'applied_at' => $amendment->applied_at,
                    'requested_at' => $amendment->requested_at,
                    'amendment_type' => $amendment->amendment_type,
                    'status' => $amendment->status,
                    'applied_version_no' => $amendment->appliedVersion?->version_no,
                ]);

            $pdf = Pdf::loadView('medical-records.owner-export', [
                'record' => $record,
                'pet' => $record->pet,
                'clinic' => $record->clinic ?? $record->booking?->clinic,
                'vitals' => $vitals,
                'prescriptions' => $record->prescriptionItems,
                'allergies' => $record->pet?->allergies?->where('is_active', true) ?? collect(),
                'amendments' => $amendments,
            ])->setPaper('a4');

            $contents = $pdf->output();
            $path = sprintf(
                'medical-record-exports/%s/%s/%s.pdf',
                $record->clinic_id ?: 'unscoped',
                $record->id,
                $export->id
            );

            Storage::disk('local')->put($path, $contents);

            $this->exports->render($export, [
                'rendered_path' => $path,
                'rendered_hash' => hash('sha256', $contents),
                'content_hash' => $record->currentVersion?->content_hash ?: $record->content_hash,
            ], $request->user());

            $export->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Salinan rekam medis berhasil dibuat.',
                'data' => [
                    'export_id' => $export->id,
                    'format' => $export->format,
                    'purpose' => $export->purpose,
                    'expires_at' => $export->expires_at?->toIso8601String(),
                    'download_url' => url('/api/medical-record-exports/' . $created['token']),
                    'token' => $created['token'],
                ],
            ], 201);
        } catch (MedicalRecordLifecycleException $e) {
            $this->safeRevoke($created, $request);
            $this->auditDenied($request, $id, $e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], $e->httpStatus);
        } catch (\Throwable $e) {
            Log::error('Owner medical record export failed', [
                'medical_record_id' => $id,
                'user_id' => $request->user()->id,
                'message' => $e->getMessage(),
            ]);

            $this->safeRevoke($created, $request);

            return response()->json([
                'success' => false,
                'message' => 'Salinan rekam medis gagal dibuat.',
            ], 500);
        }
    }

    public function downloadExport(Request $request, string $token)
    {
        $export = MedicalRecordExport::where('token_hash', hash('sha256', $token))
            ->with('record.pet')
            ->first();

        if (! $export || ! $export->record || (int) $export->record->pet?->user_id !== (int) $request->user()->id) {
            return $this->notFound('Salinan rekam medis tidak ditemukan');
        }

        if (! $export->rendered_path || ! Storage::disk('local')->exists($export->rendered_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Berkas salinan tidak tersedia.',
                'code' => 'EXPORT_DOCUMENT_NOT_READY',
            ], 410);
        }

        try {
            $export = $this->exports->resolve(
                $token,
                (int) $export->medical_record_id,
                $export->version_id ? (int) $export->version_id : null,
                $export->format,
                $export->purpose,
                $request->ip(),
                $request->user()
            );
        } catch (MedicalRecordLifecycleException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], $e->httpStatus);
        }

        return Storage::disk('local')->download(
            $export->rendered_path,
            'rekam-medis-' . $export->medical_record_id . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    private function ownedRecord(Request $request, int $id, array $with = []): ?MedicalRecord
    {
        return MedicalRecord::whereIn('pet_id', $request->user()->pets()->pluck('id'))
            ->with($with)
            ->find($id);
    }

    private function notFound(string $message)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 404);
    }

    private function safeRevoke(?array $created, Request $request): void
    {
        if (! $created || empty($created['export'])) {
            return;
        }

        try {
            $this->exports->revoke($created['export']->fresh(), 'Pembuatan salinan gagal', $request->user());
        } catch (\Throwable $e) {
            Log::warning('Failed to revoke broken owner export', [
                'export_id' => $created['export']->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function auditDenied(Request $request, int $recordId, MedicalRecordLifecycleException $e): void
    {
        $this->audit->record(
            'medical_record.export.denied',
            null,
            $request->user(),
            'denied',
            $e->errorCode,
            ['record_id' => $recordId, 'request_source' => 'owner_portal'],
            null,
            $request->ip()
        );
    }
}
