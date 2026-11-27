# P4.6 — Berkas Jawaban PDP dari UI Admin

Status: **kode selesai; pengujian penuh ditunda atas permintaan pengguna**  
Tanggal: 26 November 2026  
Migration: `2026_11_26_000019_add_response_file_to_privacy_requests.php`

## 1. Tujuan

Sebelumnya admin hanya mengisi `response_path` berupa teks. Pemilik tidak
punya berkas nyata untuk diunduh. P4.6 menutup celah itu:

1. Admin dapat membuat salinan data **otomatis dari UI**, tanpa menyiapkan PDF
   manual.
2. Admin dapat mengunggah PDF sendiri lewat form web.
3. Berkas disimpan terenkripsi, bukan plaintext.
4. Pemilik dapat mengunduh dari Android pada bagian **Dokumen & Privasi →
   Status permintaan**.
5. Setiap unduhan dicatat ke audit log dan dihitung.

## 2. Skema

Kolom baru pada `privacy_requests`:

| Kolom | Arti |
|---|---|
| `response_file_path` | path privat berkas terenkripsi |
| `response_file_name` | nama tampilan untuk unduhan |
| `response_file_mime` | saat ini `application/pdf` |
| `response_file_size` | ukuran plaintext |
| `response_file_sha256` | hash plaintext |
| `response_file_encrypted` | true bila tersimpan terenkripsi |
| `response_download_count` | jumlah unduhan |
| `response_last_downloaded_at` | waktu unduhan terakhir |

`response_path` lama tetap dipertahankan untuk kompatibilitas.

## 3. Service

`App\Services\PrivacyResponseService`

- `attach()` — validasi PDF, maksimal 10 MB, hash, enkripsi, simpan.
- `generate()` — membuat PDF otomatis dari:
  - profil pemilik;
  - daftar hewan;
  - riwayat booking;
  - seluruh rekam medis hewan milik pemilik.
- `read()` — dekripsi dengan AES-256-GCM.
- `download()` — baca, tambah counter, catat audit.
- Berkas lama dihapus saat diganti.
- Berkas tidak dapat diubah setelah permintaan `completed` atau `rejected`.

Lokasi penyimpanan:

```text
storage/app/private/privacy-responses/{clinic}/{request}/{uuid}.pdf
```

Disk `local` mengikuti konfigurasi Laravel, bukan folder publik.

## 4. UI Admin

Halaman `/admin/privacy-requests/{id}` mendapat panel **Berkas jawaban**:

- nama berkas;
- ukuran;
- status enkripsi;
- SHA-256;
- jumlah unduhan;
- waktu unduhan terakhir;
- tombol **Unduh berkas jawaban**;
- tombol **Buat salinan data otomatis**;
- form **Unggah berkas jawaban** (`application/pdf`, maksimal 10 MB).

Tombol generate memakai konfirmasi. Setelah dibuat, admin tinggal menandai
permintaan selesai. Tidak perlu membuat PDF manual dari file system.

## 5. API Pemilik

Serializer `GET /api/privacy-requests` sekarang menambahkan:

```json
{
  "response_available": true,
  "response_file_name": "salinan-data-pdp-12.pdf",
  "response_file_size": 2048,
  "response_download_count": 1
}
```

Endpoint baru:

```http
GET /api/privacy-requests/{id}/response
```

- hanya pemilik permintaan;
- mengembalikan PDF asli;
- 404 bila belum ada berkas;
- header `Cache-Control: private, no-store`.
- setiap unduhan dicatat `privacy_request.response_downloaded`.

## 6. Android

`PrivacyRequest` mendapat field:

- `responseAvailable`;
- `responseFileName`;
- `responseFileSize`;
- `responseDownloadCount`.

Perubahan UI di **Dokumen & Privasi → Status permintaan**:

- menampilkan nama dan ukuran berkas;
- tombol **Unduh salinan data** muncul bila tersedia;
- spinner saat mengunduh;
- PDF disimpan ke `Downloads/PetHeal/salinan-data-pdp-{id}.pdf`
  pada Android 10+;
- Android 7–9 membuka lewat FileProvider;
- tidak meminta izin storage lebar.

## 7. Audit

Aksi baru:

- `privacy_request.response_attached`;
- `privacy_request.response_generated`;
- `privacy_request.response_downloaded`.

Metadata tetap memakai whitelist `AuditTrailService`; isi berkas tidak masuk
audit.

## 8. Pengujian yang Sudah Dijalankan

Sebelum pengujian penuh ditunda:

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P45PrivacyResponseFileTest.php
```

Hasil: **4 passed, 29 assertions**.

Cakupan:

- admin upload PDF;
- berkas tersimpan terenkripsi;
- hash plaintext benar;
- pemilik melihat `response_available`;
- pemilik mengunduh isi PDF asli;
- counter dan audit unduhan;
- generate PDF otomatis;
- permintaan tanpa berkas → 404;
- berkas terkunci setelah selesai.

Regresi terkait:

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P45PrivacyResponseFileTest.php \
  tests/Feature/P44AdminPrivacyDashboardTest.php \
  tests/Feature/P34OwnerPortalAmendmentExportPrivacyTest.php
```

Hasil: **11 passed, 107 assertions**.

Android:

- `testDebugUnitTest` dan `assembleDebug` sempat lulus sebelum perubahan UI
  terakhir;
- kontrak JSON `PrivacyRequest` diperbarui;
- full test suite backend **belum dijalankan ulang** setelah perubahan ini.

## 9. Batasan

- Hanya PDF; ZIP/gambar belum didukung.
- Maksimal 10 MB.
- Belum ada pratinjau PDF di web.
- Belum ada penghapusan berkas manual (hanya replace sebelum selesai).
- Belum ada notifikasi ke pemilik saat berkas tersedia.
- Belum ada enkripsi metadata nama berkas.
- Belum ada antivirus/scan malware.
- Belum ada retensi otomatis berkas PDP.
- Belum ada watermark per pemilik.
- Belum ada rate limit khusus unduhan PDP.
- Full suite dan Android test final belum dijalankan setelah perubahan terakhir.
