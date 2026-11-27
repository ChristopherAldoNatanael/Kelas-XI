# P3.4 — Portal Pemilik: Amendment, Ekspor, dan Permintaan PDP

Status: **SELESAI (API pemilik + generator PDF)**  
Tanggal: 26 November 2026

## 1. Tujuan

P3.4 membuka akses pemilik tanpa mengubah kontrak Android lama:

1. Pemilik dapat melihat ringkasan riwayat amendment.
2. Pemilik dapat membuat salinan PDF rekam medis final.
3. Pemilik dapat mengajukan dan memantau permintaan PDP.
4. Tidak ada field internal/rahasia yang bocor ke serializer pemilik.

## 2. Endpoint Baru (additive)

| Method | Path | Fungsi |
|---|---|---|
| GET | `/api/medical-records/{id}/amendments` | Ringkasan amendment milik hewan pemilik. |
| GET | `/api/medical-records/{id}/export?format=pdf` | Buat salinan PDF final dan token sekali pakai. |
| GET | `/api/medical-record-exports/{token}` | Unduh salinan PDF sekali pakai. |
| GET | `/api/privacy-requests` | Daftar permintaan PDP milik sendiri. |
| POST | `/api/privacy-requests` | Ajukan permintaan PDP. |

Semua endpoint memakai middleware API yang sudah ada dan otorisasi pemilik
lewat relasi `pets.user_id`.

## 3. Controller

### 3.1 `OwnerMedicalRecordPortalController`

- `amendments()`
  - Hanya rekam medis milik hewan pemilik.
  - Response whitelist:
    - `id`
    - `type`
    - `status`
    - `requested_at`
    - `reviewed_at`
    - `applied_at`
    - `applied_version_no`
  - Tidak pernah mengirim:
    - `before`
    - `after`
    - `reason`
    - `review_notes`
    - `reviewer_credential_snapshot`
    - `signature_path`
    - audit
- `createExport()`
  - Hanya rekam medis `final`.
  - Format pemilik saat ini hanya `pdf`.
  - Membuat `medical_record_exports` dengan tujuan `owner_copy`, TTL 15 menit.
  - Merender PDF nyata memakai Dompdf dari view
    `resources/views/medical-records/owner-export.blade.php`.
  - Menyimpan berkas di disk `local`:
    `medical-record-exports/{clinic}/{record}/{export}.pdf`.
  - Menghitung:
    - `rendered_hash` = SHA-256 isi PDF
    - `content_hash` = hash versi rekam medis
  - Mengembalikan token, `download_url`, dan `expires_at`.
  - Audit `medical_record.export.created`, `.rendered`, dan `.denied` bila gagal.
- `downloadExport()`
  - Wajib login pemilik yang sama.
  - Token hanya disimpan sebagai SHA-256 di database.
  - Sekali pakai; unduhan kedua menghasilkan `410 EXPORT_TOKEN_ALREADY_USED`.
  - Memeriksa keberadaan berkas sebelum token dibakar.
  - Audit akses lewat `MedicalRecordExportService::resolve()`.

### 3.2 `PrivacyRequestController`

- `index()`
  - Hanya `user_id` pemilik yang login.
  - Pagination 20.
- `store()`
  - Validasi tipe, uraian, dan `clinic_id` opsional.
  - Memakai `PrivacyRequestService`; status awal `received`.
  - SLA default 14 hari.
- Serializer whitelist:
  - `id`
  - `request_type`
  - `status`
  - `description`
  - `requested_at`
  - `due_at`
  - `verified_at`
  - `completed_at`
  - `rejection_reason`
  - `is_overdue`
  - `created_at`
- Tidak mengirim `handled_by`, `response_path`, `user_id`, atau data admin.

## 4. PDF Salinan Pemilik

View memuat hanya data yang boleh dilihat pemilik:

- identitas hewan dan klinik;
- dokter, STR, SIP;
- diagnosis dan SOAP;
- tindakan, obat, catatan, jadwal kontrol;
- vital terbaru;
- resep;
- alergi aktif;
- ringkasan amendment.

Footer menegaskan dokumen bukan pengganti dokumen resmi bertanda tangan.
Ekspor resmi `insurance`/`legal` tetap memerlukan tanda tangan dokter melalui
`MedicalRecordExportService::sign()`.

## 5. Keamanan dan Batasan Akses

- Rekam medis pemilik lain → `404`.
- Rekam medis bukan final → `409 RECORD_NOT_FINAL`.
- Format selain PDF → `422 OWNER_EXPORT_FORMAT_UNSUPPORTED`.
- Token salah → `404 EXPORT_TOKEN_INVALID`.
- Token kedaluwarsa → `410 EXPORT_TOKEN_EXPIRED`.
- Token dipakai dua kali → `410 EXPORT_TOKEN_ALREADY_USED`.
- Berkas hilang → `410 EXPORT_DOCUMENT_NOT_READY`.
- Payload `/api/medical-records` **tidak berubah**; tidak ada key top-level baru.
- Kontrak Android lama tetap diuji oleh `P01CharacterizationTest`.

## 6. Pengujian

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P34OwnerPortalAmendmentExportPrivacyTest.php
```

Hasil P3.4:

- **4 passed, 53 assertions**
  - Ringkasan amendment hanya berisi field whitelist.
  - String rahasia `RAHASIA_*` tidak muncul.
  - Pemilik lain mendapat 404.
  - PDF berhasil dibuat dan hash berkas cocok.
  - Unduhan pertama berhasil dengan `Content-Type: application/pdf`.
  - Unduhan kedua ditolak 410.
  - Ekspor rekam medis draft ditolak.
  - Format CSV ditolak.
  - Pemilik lain tidak bisa mengekspor.
  - Permintaan PDP tersimpan dan hanya terlihat oleh pemiliknya.
  - Tipe PDP tidak valid ditolak 422.
  - Serializer PDP tidak memuat `handled_by`/`response_path`/`user_id`.

Regresi kontrak bersama P01:

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P01CharacterizationTest.php \
  tests/Feature/P34OwnerPortalAmendmentExportPrivacyTest.php
```

Hasil: **9 passed, 73 assertions**.

`php -l` bersih dan `php artisan route:list` memuat kelima endpoint baru.

**Catatan jujur:** full test suite tidak dijalankan ulang pada putaran ini.

## 7. Batasan yang Masih Terbuka

- Belum ada UI Android/web untuk kelima endpoint.
- Belum ada halaman admin PDP; penanganan masih lewat service/command.
- Belum ada notifikasi status permintaan PDP.
- Belum ada unggah/unduh `response_path` PDP yang aman.
- PDF memakai Dompdf, belum PDF/A dan belum memiliki tanda tangan digital PKI.
- Token unduhan adalah bearer acak 32 byte, bukan signed URL kriptografis
  berumur pendek dengan rotasi kunci.
- Token dikirim di body JSON dan URL unduhan; belum ada header khusus.
- Berkas PDF belum dienkripsi at-rest dan belum memiliki kebijakan cleanup
  otomatis; bila proses render gagal setelah berkas dibuat, pembersihan orphan
  belum otomatis.
- `clinic_id` pada permintaan PDP belum divalidasi terhadap riwayat booking
  pemilik.
- Amendment yang ditampilkan mencakup semua status, bukan hanya `applied`.
- Belum ada rate limit khusus pembuatan ekspor.
- Belum ada portal untuk penerima eksternal (insurance/legal).
- Full suite belum dijalankan ulang.

## 8. Keputusan Penting

- Endpoint baru hanya additive; payload lama tidak disentuh.
- Serializer pemilik adalah whitelist.
- Salinan pemilik hanya untuk rekam medis final.
- Token ekspor sekali pakai dan disimpan sebagai hash.
- PDF nyata dibuat, bukan URL palsu.
- Ekspor resmi tetap membutuhkan tanda tangan dokter.
- Unduhan wajib login sebagai pemilik yang sama.
- Permintaan PDP tidak dapat diubah pemilik menjadi completed/rejected.
