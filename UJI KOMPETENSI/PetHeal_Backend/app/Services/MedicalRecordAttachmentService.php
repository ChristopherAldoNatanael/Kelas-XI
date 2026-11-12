<?php

namespace App\Services;

use App\Models\MedicalRecord;
use App\Models\MedicalRecordAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Simpan & hapus lampiran rekam medis.
 *
 * Aturan yang dijaga di sini (bukan di controller) supaya halaman admin dan
 * portal dokter berperilaku identik:
 *  - gambar (rontgen/foto) dikecilkan & di-re-encode lewat ImageService,
 *  - PDF disimpan apa adanya (hasil laboratorium sering berupa PDF),
 *  - selain itu tidak diterima,
 *  - file lama dihapus saat lampiran dihapus, tidak dibiarkan menumpuk.
 */
class MedicalRecordAttachmentService
{
    /** Batas ukuran per berkas (KB). Sama dengan aturan validasi form. */
    public const MAX_KB = 5120;

    /** MIME yang diterima. */
    public const ALLOWED_MIMES = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, MedicalRecordAttachment>
     */
    public function storeMany(MedicalRecord $record, array $files, ?int $uploadedBy = null): array
    {
        $saved = [];

        foreach ($files as $index => $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $saved[] = $this->storeOne($record, $file, $uploadedBy, $index);
        }

        return $saved;
    }

    public function storeOne(
        MedicalRecord $record,
        UploadedFile $file,
        ?int $uploadedBy = null,
        int $index = 0,
        ?string $caption = null
    ): MedicalRecordAttachment {
        $mime = (string) $file->getMimeType();
        $directory = 'medical-records/' . $record->id;
        $slug = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'lampiran';

        if (str_starts_with($mime, 'image/')) {
            $relativePath = $directory . '/' . $slug . '-' . time() . '-' . $index . '.jpg';

            // Max 1600px: cukup untuk membaca hasil rontgen tanpa file raksasa.
            $path = ImageService::process($file, $relativePath, 1600, 82);
            $storedMime = 'image/jpeg';
        } else {
            // PDF: simpan asli, jangan diubah isinya (dokumen medis).
            $relativePath = $directory . '/' . $slug . '-' . time() . '-' . $index . '.pdf';
            Storage::disk('public')->putFileAs($directory, $file, basename($relativePath));
            $path = $relativePath;
            $storedMime = 'application/pdf';
        }

        return MedicalRecordAttachment::create([
            'medical_record_id' => $record->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'caption' => $caption,
            'mime_type' => $storedMime,
            'size_bytes' => (int) ($file->getSize() ?: 0),
            'uploaded_by' => $uploadedBy,
        ]);
    }

    public function delete(MedicalRecordAttachment $attachment): void
    {
        if ($attachment->path && Storage::disk('public')->exists($attachment->path)) {
            Storage::disk('public')->delete($attachment->path);
        }

        $attachment->delete();
    }
}
