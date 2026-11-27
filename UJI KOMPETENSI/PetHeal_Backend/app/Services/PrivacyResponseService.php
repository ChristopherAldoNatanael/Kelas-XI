<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\Booking;
use App\Models\MedicalRecord;
use App\Models\PrivacyRequest;
use App\Models\User;
use Dompdf\Dompdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Berkas jawaban PDP: unggah PDF atau buat salinan data otomatis.
 *
 * Berkas selalu disimpan terenkripsi (AES-256-GCM) dan hanya dapat diunduh
 * oleh pemilik permintaan atau admin yang berwenang.
 */
class PrivacyResponseService
{
    private const DISK = 'local';
    private const MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private AuditTrailService $audit,
        private MedicalRecordFileEncryptionService $encryption,
    ) {
    }

    public function isAvailable(PrivacyRequest $request): bool
    {
        return ! empty($request->response_file_path);
    }

    public function attach(PrivacyRequest $request, UploadedFile $file, User $actor): PrivacyRequest
    {
        $this->assertEditable($request);

        if (! $file->isValid()) {
            throw new MedicalRecordLifecycleException(
                'PRIVACY_RESPONSE_UPLOAD_INVALID',
                'Berkas jawaban tidak valid atau gagal diunggah.',
                422
            );
        }

        if ((int) $file->getSize() > self::MAX_BYTES) {
            throw new MedicalRecordLifecycleException(
                'PRIVACY_RESPONSE_TOO_LARGE',
                'Berkas jawaban maksimal 10 MB.',
                422
            );
        }

        $bytes = file_get_contents($file->getRealPath());

        if ($bytes === false || $bytes === '') {
            throw new MedicalRecordLifecycleException(
                'PRIVACY_RESPONSE_EMPTY',
                'Berkas jawaban kosong.',
                422
            );
        }

        $mime = (string) ($file->getMimeType() ?: 'application/pdf');

        if ($mime !== 'application/pdf' || ! str_starts_with($bytes, '%PDF')) {
            throw new MedicalRecordLifecycleException(
                'PRIVACY_RESPONSE_NOT_PDF',
                'Berkas jawaban wajib berupa PDF.',
                422
            );
        }

        $name = $this->safeName($file->getClientOriginalName());

        return $this->store($request, $bytes, $name, 'application/pdf', $actor, 'privacy_request.response_attached');
    }

    public function generate(PrivacyRequest $request, User $actor): PrivacyRequest
    {
        $this->assertEditable($request);

        $owner = $request->user;

        if (! $owner) {
            throw new MedicalRecordLifecycleException(
                'PRIVACY_RESPONSE_OWNER_MISSING',
                'Pemilik data tidak ditemukan.',
                422
            );
        }

        $pets = $owner->pets()->orderBy('name')->get();

        $records = MedicalRecord::with(['pet:id,name', 'doctor:id,name', 'clinic:id,name'])
            ->whereIn('pet_id', $pets->pluck('id'))
            ->orderByDesc('created_at')
            ->get();

        $bookings = Booking::with(['pet:id,name', 'doctor:id,name', 'clinic:id,name'])
            ->where('user_id', $owner->id)
            ->orderByDesc('booking_date')
            ->get();

        $html = view('privacy.owner-data-copy', [
            'privacyRequest' => $request,
            'owner' => $owner,
            'pets' => $pets,
            'records' => $records,
            'bookings' => $bookings,
            'generatedAt' => now(),
        ])->render();

        $pdf = new Dompdf(['isRemoteEnabled' => false]);
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();

        $bytes = $pdf->output();

        if ($bytes === '' || ! str_starts_with($bytes, '%PDF')) {
            throw new MedicalRecordLifecycleException(
                'PRIVACY_RESPONSE_GENERATE_FAILED',
                'Salinan data gagal dibuat.',
                500
            );
        }

        $name = 'salinan-data-pdp-' . $request->id . '.pdf';

        return $this->store($request, $bytes, $name, 'application/pdf', $actor, 'privacy_request.response_generated');
    }

    /**
     * Baca plaintext berkas jawaban. Pemanggil wajib memeriksa hak akses.
     */
    public function read(PrivacyRequest $request): string
    {
        if (! $this->isAvailable($request)) {
            throw new MedicalRecordLifecycleException(
                'PRIVACY_RESPONSE_NOT_AVAILABLE',
                'Belum ada berkas jawaban untuk permintaan ini.',
                404
            );
        }

        if (! Storage::disk(self::DISK)->exists($request->response_file_path)) {
            throw new MedicalRecordLifecycleException(
                'PRIVACY_RESPONSE_MISSING',
                'Berkas jawaban tidak ditemukan pada penyimpanan.',
                404
            );
        }

        try {
            return $this->encryption->decrypt(
                (string) Storage::disk(self::DISK)->get($request->response_file_path)
            );
        } catch (Throwable $e) {
            throw new MedicalRecordLifecycleException(
                'PRIVACY_RESPONSE_UNREADABLE',
                'Berkas jawaban tidak dapat dibaca atau rusak.',
                500
            );
        }
    }

    /**
     * Unduh + catat audit. Dipakai admin dan pemilik.
     */
    public function download(PrivacyRequest $request, User $actor): string
    {
        $bytes = $this->read($request);

        $request->forceFill([
            'response_download_count' => (int) $request->response_download_count + 1,
            'response_last_downloaded_at' => now(),
        ])->save();

        $this->audit->record(
            'privacy_request.response_downloaded',
            $request,
            $actor,
            'allowed',
            null,
            ['request_type' => $request->request_type, 'status' => $request->status],
            $request->clinic_id
        );

        return $bytes;
    }

    private function store(
        PrivacyRequest $request,
        string $bytes,
        string $name,
        string $mime,
        User $actor,
        string $action
    ): PrivacyRequest {
        $path = 'privacy-responses/'
            . ($request->clinic_id ?: 'global')
            . '/' . $request->id
            . '/' . Str::uuid() . '.pdf';

        $encrypted = $this->encryption->encrypt($bytes);

        Storage::disk(self::DISK)->put($path, $encrypted);

        $oldPath = $request->response_file_path;

        $request->forceFill([
            'response_file_path' => $path,
            'response_file_name' => $name,
            'response_file_mime' => $mime,
            'response_file_size' => strlen($bytes),
            'response_file_sha256' => hash('sha256', $bytes),
            'response_file_encrypted' => $this->encryption->isEncrypted($encrypted),
            'response_download_count' => 0,
            'response_last_downloaded_at' => null,
        ])->save();

        if ($oldPath && $oldPath !== $path) {
            Storage::disk(self::DISK)->delete($oldPath);
        }

        $this->audit->record(
            $action,
            $request,
            $actor,
            'allowed',
            null,
            ['request_type' => $request->request_type, 'status' => $request->status],
            $request->clinic_id
        );

        return $request->refresh();
    }

    private function assertEditable(PrivacyRequest $request): void
    {
        if (in_array($request->status, [PrivacyRequest::STATUS_COMPLETED, PrivacyRequest::STATUS_REJECTED], true)) {
            throw new MedicalRecordLifecycleException(
                'PRIVACY_RESPONSE_LOCKED',
                'Berkas jawaban tidak dapat diubah setelah permintaan selesai atau ditolak.',
                422
            );
        }
    }

    private function safeName(?string $original): string
    {
        $base = basename((string) $original);
        $base = preg_replace('/[^A-Za-z0-9._-]/', '-', $base) ?: '';
        $base = trim($base, '.-');

        if ($base === '') {
            $base = 'salinan-data.pdf';
        }

        if (! str_ends_with(strtolower($base), '.pdf')) {
            $base .= '.pdf';
        }

        return mb_substr($base, 0, 180);
    }
}
