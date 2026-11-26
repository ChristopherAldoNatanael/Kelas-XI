<?php

namespace App\Services;

use App\Models\MedicalRecord;
use App\Models\MedicalRecordAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Simpan & hapus lampiran rekam medis.
 *
 * Aturan yang dijaga di sini (bukan di controller) supaya halaman admin dan
 * portal dokter berperilaku identik:
 *  - berkas disimpan di disk privat dan hanya bisa diunduh lewat URL bertanda
 *    tangan (akses publik langsung ke folder storage tidak lagi mungkin),
 *  - gambar (rontgen/foto) dikecilkan & di-re-encode lewat ImageService,
 *  - PDF disimpan apa adanya (hasil laboratorium sering berupa PDF),
 *  - nama berkas memakai ULID sehingga dua unggahan pada detik yang sama tidak
 *    saling menimpa,
 *  - sha256 dicatat untuk membuktikan berkas tidak berubah,
 *  - kuota total per rekam medis dibatasi agar disk tidak tumbuh tanpa kendali,
 *  - penghapusan bersifat lunak: baris tetap ada sebagai riwayat.
 */
class MedicalRecordAttachmentService
{
    /** Batas ukuran per berkas (KB). Sama dengan aturan validasi form. */
    public const MAX_KB = 5120;

    /** MIME yang diterima. */
    public const ALLOWED_MIMES = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    private function disk(): string
    {
        return (string) config('medical_records.attachment_disk', MedicalRecordAttachment::DISK);
    }

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
        $this->assertQuota($record, (int) ($file->getSize() ?: 0));

        $mime = (string) $file->getMimeType();
        $directory = 'medical-records/' . $record->id;
        $name = Str::ulid()->toBase32();

        if (str_starts_with($mime, 'image/')) {
            $relativePath = $directory . '/' . $name . '.jpg';

            // Max 1600px: cukup untuk membaca hasil rontgen tanpa file raksasa.
            $path = ImageService::process($file, $relativePath, 1600, 82, $this->disk());
            $storedMime = 'image/jpeg';
        } else {
            // PDF: simpan asli, jangan diubah isinya (dokumen medis).
            $relativePath = $directory . '/' . $name . '.pdf';
            Storage::disk($this->disk())->putFileAs($directory, $file, basename($relativePath));
            $path = $relativePath;
            $storedMime = 'application/pdf';
        }

        $fullPath = Storage::disk($this->disk())->path($path);

        return MedicalRecordAttachment::create([
            'medical_record_id' => $record->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'caption' => $caption,
            'mime_type' => $storedMime,
            'size_bytes' => (int) ($file->getSize() ?: 0),
            'sha256' => is_file($fullPath) ? hash_file('sha256', $fullPath) : null,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    /**
     * Hapus lunak. Berkas fisik tetap disimpan karena rekam medis adalah bukti
     * pemeriksaan; tampilan tidak lagi menampilkannya dan penghapusan tercatat
     * di revisi rekam medis.
     */
    public function delete(MedicalRecordAttachment $attachment): void
    {
        $attachment->delete();
    }

    /**
     * Hapus permanen (berkas + baris). Hanya untuk pemeliharaan berkas yatim,
     * bukan untuk alur klinis sehari-hari.
     */
    public function purge(MedicalRecordAttachment $attachment): void
    {
        if ($attachment->path && Storage::disk($this->disk())->exists($attachment->path)) {
            Storage::disk($this->disk())->delete($attachment->path);
        }

        $attachment->forceDelete();
    }

    private function assertQuota(MedicalRecord $record, int $incomingBytes): void
    {
        $maxMb = (int) config('medical_records.attachment_max_total_mb', 200);
        if ($maxMb <= 0) {
            return;
        }

        $used = (int) MedicalRecordAttachment::withTrashed()
            ->where('medical_record_id', $record->id)
            ->sum('size_bytes');

        if ($used + $incomingBytes > $maxMb * 1024 * 1024) {
            throw new RuntimeException(
                "Lampiran rekam medis ini sudah mencapai batas {$maxMb} MB. "
                . 'Hapus lampiran yang tidak diperlukan atau gunakan berkas yang lebih kecil.'
            );
        }
    }
}
