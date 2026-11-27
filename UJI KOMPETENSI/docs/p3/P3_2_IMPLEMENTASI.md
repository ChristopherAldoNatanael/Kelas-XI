# P3.2 — Ekspor Resmi, Tanda Tangan, dan Audit Akses

Status: **SELESAI (lapisan data + service)**  
Tanggal: 26 November 2026  
Migration: `2026_11_26_000015_add_export_signing_and_audit_guards.php`

## 1. Tujuan

P3.2 melengkapi ekspor rekam medis agar:

- dokumen resmi memiliki hash dokumen dan hash isi,
- ekspor resmi wajib ditandatangani dokter sebelum token dapat dipakai,
- setiap pembuatan, pembuatan dokumen, tanda tangan, akses, penolakan, dan
  pencabutan tercatat di `audit_logs`,
- audit tidak dapat diubah atau dihapus dari aplikasi/database,
- token asli dan isi klinis tidak pernah masuk metadata audit.

## 2. Perubahan Skema

### 2.1 `medical_record_exports` (additive)

| Kolom | Keterangan |
|---|---|
| `rendered_path` | Path dokumen hasil render. |
| `rendered_hash` | SHA-256 dokumen hasil render. |
| `content_hash` | SHA-256 isi rekam medis/versi saat diekspor. |
| `signature_path` | Path tanda tangan pengesahan. |
| `signature_hash` | SHA-256 berkas tanda tangan (opsional). |
| `signed_at` | Waktu tanda tangan. |
| `signed_by` | Dokter penanda tangan. |

`file_hash` lama tetap dipakai dan diisi sama dengan `rendered_hash` saat
dokumen dirender.

### 2.2 `audit_logs` (guard database)

Kolom `clinic_id`, `request_id`, `result`, `reason`, dan `metadata` sudah ada
sejak migrasi lifecycle. P3.2 menambahkan:

- `AuditLog` fillable dan cast `metadata => array`.
- Trigger MySQL:
  - `audit_logs_no_update`
  - `audit_logs_no_delete`

Keduanya menolak `UPDATE` dan `DELETE` langsung di database.

## 3. Service

### 3.1 `AuditTrailService`

`record(action, model, actor, result, reason, metadata, clinicId, ip, userAgent)`

Aturan:

- `result` hanya `allowed`, `denied`, atau `failed`.
- `description` selalu NULL agar isi klinis tidak bocor.
- Metadata memakai whitelist:
  - `format`, `purpose`, `version_id`, `export_id`, `record_id`,
    `recipient_type`, `token_ttl_minutes`, `access_count`, `request_source`.
- Nilai metadata hanya scalar/null.
- `request_id` diambil dari header `X-Request-Id` yang valid, atau dibuat acak
  32 karakter dan disimpan pada request yang sama.
- `reason` dipotong 120 karakter.
- Akses super admin tetap tercatat.

### 3.2 `MedicalRecordExportService`

#### `create()`

- Membuat ekspor dan token.
- Mencatat `medical_record.export.created`.
- Metadata tidak memuat token.

#### `render(export, data, actor)`

- Wajib `rendered_path`.
- `rendered_hash` wajib SHA-256 64 heksadesimal.
- `content_hash` opsional; default dari versi aktif atau `record.content_hash`.
- `file_hash` diisi `rendered_hash`.
- Ekspor yang sudah dipakai/dicabut tidak dapat dirender.
- Mencatat `medical_record.export.rendered`.

Error:

- `EXPORT_RENDERED_PATH_REQUIRED`
- `EXPORT_RENDERED_HASH_INVALID`
- `EXPORT_HASH_INVALID`
- `EXPORT_ALREADY_USED`
- `EXPORT_ALREADY_REVOKED`

#### `sign(export, data, actor)`

- Hanya role `doctor`.
- Dokumen harus sudah dirender.
- `signature_path` wajib.
- `signature_hash` opsional, harus SHA-256.
- Tidak boleh tanda tangan ganda.
- Mencatat `medical_record.export.signed`.

Error:

- `EXPORT_SIGNER_NOT_DOCTOR`
- `EXPORT_DOCUMENT_NOT_READY`
- `EXPORT_SIGNATURE_PATH_REQUIRED`
- `EXPORT_ALREADY_SIGNED`

#### `resolve()`

Urutan pemeriksaan:

1. Token valid.
2. Tidak dicabut.
3. Tidak kedaluwarsa.
4. Belum dipakai.
5. Cocok dengan rekam medis/versi/format/tujuan.
6. Untuk `legal` dan `insurance`: **wajib sudah ditandatangani dokter**.

Token yang ditolak **tidak** menambah `access_count`.

Setiap akses berhasil mencatat:

- `token_used_at`, `downloaded_at`
- `access_count = 1`
- `last_accessed_at`, `last_accessed_ip`

Audit:

- `medical_record.export.access` dengan `result = allowed`
- `medical_record.export.access` dengan `result = denied`
  dan `reason` berisi kode error, tanpa token.

#### `revoke()`

- Ekspor yang sudah dipakai tidak dapat dicabut.
- Mencatat `medical_record.export.revoked`.

### 3.3 Kapan tanda tangan wajib

| Purpose | Wajib tanda tangan |
|---|---|
| `owner_copy` | Tidak |
| `referral` | Tidak |
| `insurance` | Ya |
| `legal` | Ya |

Alasannya: salinan pemilik dan rujukan operasional tidak selalu memerlukan
pengesahan dokter, sedangkan klaim asuransi dan kepentingan hukum memerlukan
dokumen yang dapat dipertanggungjawabkan.

## 4. Audit yang Dicatat

| Action | Kapan |
|---|---|
| `medical_record.export.created` | Ekspor dan token dibuat. |
| `medical_record.export.rendered` | Dokumen hasil render disimpan. |
| `medical_record.export.signed` | Dokter menandatangani. |
| `medical_record.export.access` | Token berhasil dipakai. |
| `medical_record.export.access` | Token ditolak (`denied`). |
| `medical_record.export.revoked` | Ekspor dicabut. |

Tidak dicatat:

- token asli,
- hash token,
- diagnosis/SOAP,
- isi dokumen,
- data pribadi pemilik.

## 5. Pengujian

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P03LifecycleAndAuditChainTest.php \
  tests/Feature/P23TemplatesReferralsExportsInpatientTest.php \
  tests/Feature/P32ExportSigningAndAuditTest.php
```

Hasil:

- **15 passed, 138 assertions**
- `P32ExportSigningAndAuditTest`: **4 passed, 33 assertions**
  - Ekspor legal tidak dapat dipakai sebelum dirender dan ditandatangani.
  - Tanda tangan sebelum render ditolak.
  - Admin tidak boleh menandatangani.
  - Path tanda tangan wajib.
  - Tanda tangan ganda ditolak.
  - Hash dokumen dan tanda tangan divalidasi.
  - Akses berhasil dan ditolak tercatat di `audit_logs`.
  - Metadata audit tidak memuat token atau diagnosis.
  - `request_id` terisi.
  - Trigger database menolak `UPDATE` dan `DELETE` pada `audit_logs`.
- Regresi P23 tetap lulus.
- `php -l` bersih.
- Migration berhasil di `pet_heal_test` dan `pet_heal`.
- DB `pet_heal`: 87 tabel, 2 trigger `audit_logs` aktif.

**Catatan jujur:** full test suite tidak dijalankan ulang pada putaran ini.

## 6. Batasan yang Masih Terbuka

- Belum ada route/controller/UI untuk render, tanda tangan, atau unduh ekspor.
- Belum ada generator PDF/JSON/CSV nyata; service hanya menerima path dan hash.
- Belum ada penyimpanan berkas aman/signed URL.
- Tanda tangan masih berupa path + hash berkas, **bukan** tanda tangan digital
  tersertifikasi (PKI/CA).
- Belum ada verifikasi kriptografis tanda tangan.
- Belum ada `file_operations` staging/cleanup untuk ekspor.
- Belum ada integrasi audit ke `request_id` middleware global.
- Belum ada laporan/query dashboard audit ekspor.
- Retensi dan pembersihan `audit_logs` belum dijalankan; trigger append-only
  juga berarti pembersihan harus melalui prosedur terpisah.
- Trigger append-only hanya dipasang untuk MySQL.
- Metadata audit masih whitelist sempit; kebutuhan forensik tambahan harus
  ditambahkan secara sadar, bukan dengan menyalin payload.

## 7. Keputusan Penting

- Ekspor legal dan asuransi wajib ditandatangani dokter.
- Tanda tangan hanya setelah dokumen dirender.
- Token yang gagal tidak dihitung sebagai akses.
- Audit memakai whitelist metadata, bukan payload mentah.
- Audit tidak menyimpan token asli/hash token.
- `audit_logs` append-only di level aplikasi dan database.
- `content_hash` dan `rendered_hash` dipisahkan agar integritas isi dan
  integritas berkas dapat dibuktikan masing-masing.
