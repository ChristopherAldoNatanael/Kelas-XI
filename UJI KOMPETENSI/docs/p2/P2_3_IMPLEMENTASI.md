# P2.3 — Template, Rujukan, Ekspor, dan Rawat Inap

Status: **SELESAI (lapisan data + service)**  
Tanggal: 26 November 2026  
Migration: `2026_11_26_000013_create_templates_exports_and_inpatient.php`

## 1. Tujuan

P2.3 menutup empat area:

1. Template klinis immutable per versi.
2. Rujukan dengan ringkasan klinis dan hash dokumen.
3. Ekspor rekam medis dengan token sekali pakai.
4. Rawat inap, riwayat lokasi, dan pemberian obat.

Prinsip:

- Template tidak diedit di tempat.
- Rujukan tidak dihapus; pembatalan memakai `void`.
- Token ekspor tidak pernah disimpan dalam bentuk asli.
- Token hanya berlaku sekali dan terikat pada rekam medis/versi/format/tujuan.
- Riwayat lokasi rawat inap tidak menimpa lokasi lama.
- Pemberian obat memakai idempotency key agar tidak tercatat ganda.

## 2. Skema

### 2.1 `clinical_templates`

- `clinic_id`, `template_key`, `name`, `species`
- `version`
- `schema` JSON berisi `fields`
- `is_current`, `active_key` unik
- `created_by`

Aturan:

- Unique `(clinic_id, template_key, version)`.
- Saat versi baru diterbitkan, versi lama menjadi `is_current = false` dan
  `active_key = NULL`.
- Hanya satu versi aktif per klinik dan key.
- Schema wajib memiliki minimal satu field dengan `key`, `label`, dan `type`.

### 2.2 `medical_record_exports`

Kolom utama:

- `clinic_id`, `medical_record_id`, `version_id`
- `format`: `pdf`, `json`, `csv`
- `purpose`: `owner_copy`, `referral`, `insurance`, `legal`
- `recipient_type`, `recipient_name`
- `token_hash` (SHA-256, unik)
- `token_used_at`, `expires_at`, `downloaded_at`
- `revoked_at`, `revoked_by`
- `access_count`, `last_accessed_at`, `last_accessed_ip`
- `file_hash`, `created_by`

Aturan token:

- Token asli dibuat dari 32 byte acak kriptografis, base64url.
- Yang disimpan hanya SHA-256 token.
- Masa berlaku 1–1440 menit. Default 15 menit untuk salinan pemilik dan
  60 menit untuk tujuan lain.
- Token sekali pakai; akses kedua menghasilkan `410`.
- Token dapat dicabut sebelum dipakai.
- Token terikat pada rekam medis, versi, format, dan tujuan.
- Setelah dipakai, ekspor tidak dapat dicabut.

### 2.3 `referrals` (additive)

Kolom baru:

- `direction`: `outgoing` / `incoming`
- `referral_number` unik per klinik
- `external_facility`
- `summary`
- `document_hash`
- `verified_by`, `verified_at`
- `voided_at`, `voided_by`, `void_reason`

Status tetap: `sent`, `accepted`, `returned`, `cancelled`, `void`.

### 2.4 `inpatient_stays`

- `clinic_id`
- `encounter_id` unik — satu encounter maksimal satu rawat inap
- `medical_record_id`
- `admitted_at`, `discharged_at`
- `kennel_code`, `ward`
- `status`: `admitted`, `in_treatment`, `discharged`, `transferred`, `died`
- `admitting_doctor_id`
- `discharge_summary`, `discharge_instructions`

### 2.5 `inpatient_location_logs`

- `clinic_id`, `inpatient_stay_id`
- `from_ward`, `from_kennel_code`
- `to_ward`, `to_kennel_code`
- `moved_at`, `moved_by`, `reason`

Setiap perpindahan adalah baris baru.

### 2.6 `medication_administrations`

- `clinic_id`, `inpatient_stay_id`
- `prescription_item_id` nullable
- `order_source`: `prescription`, `verbal_order`, `emergency`
- `ordered_by`
- `medicine_id`, `batch_id`
- `dose_value`, `dose_unit`, `route`
- `administered_at`, `administered_by`
- `status`: `given`, `refused`, `missed`, `held`
- `idempotency_key` unik
- `notes`

## 3. Service

### 3.1 `ClinicalTemplateService`

- `publish(clinic, data, actor)`
  - Validasi key, nama, dan schema.
  - Menonaktifkan versi lama dalam transaksi.
  - Versi baru = versi lama + 1.
  - `active_key` mencegah dua versi aktif.
- `current(clinic, key, actor)`
  - Mengembalikan versi aktif atau `404`.

### 3.2 `ReferralService`

- `create(record, data, actor)`
  - Isolasi klinik.
  - Rujukan keluar wajib `summary`.
  - Nomor rujukan keluar dibuat unik per klinik.
  - `document_hash` = SHA-256 payload rujukan.
- `markAccepted()`
  - Hanya dari status `sent`.
- `markReturned()`
  - Mengisi `returned_at` dan `result_notes`.
- `verify()`
  - Hanya role `doctor`.
  - Mengisi `verified_by`, `verified_at`.
- `void(referral, reason, actor)`
  - Wajib alasan.
  - Tidak menghapus baris.
  - Rujukan void tidak dapat berubah status.
  - Error: `REFERRAL_VOIDED`.

### 3.3 `MedicalRecordExportService`

- `create(record, data, actor)`
  - Validasi format, tujuan, dan TTL.
  - Versi harus milik rekam medis yang sama.
  - Mengembalikan `['export' => ..., 'token' => ...]`.
  - Token asli hanya dikembalikan sekali di return value.
- `resolve(token, recordId, versionId, format, purpose, ip)`
  - Lookup berdasarkan hash di dalam transaksi + row lock.
  - Menolak token dicabut, kedaluwarsa, sudah dipakai, atau tidak cocok.
  - Menandai `token_used_at`, `downloaded_at`, `access_count = 1`, IP akses.
- `revoke(export, reason, actor)`
  - Hanya ekspor yang belum dipakai.
  - Mengisi `revoked_at`, `revoked_by`.

Error penting:

- `EXPORT_TOKEN_INVALID` (404)
- `EXPORT_TOKEN_REVOKED` (410)
- `EXPORT_TOKEN_EXPIRED` (410)
- `EXPORT_TOKEN_ALREADY_USED` (410)
- `EXPORT_TOKEN_MISMATCH` (404)
- `EXPORT_TTL_INVALID` (422)
- `EXPORT_VERSION_MISMATCH` (404)
- `EXPORT_FILE_HASH_INVALID` (422)

### 3.4 `InpatientService`

- `admit(record, data, actor)`
  - Wajib encounter.
  - Encounter unik; rawat ulang harus encounter baru.
  - Dokter harus berada di klinik yang sama.
  - Membuat log lokasi awal.
- `transfer(stay, data, actor)`
  - Menulis log dari lokasi lama ke lokasi baru.
  - Status menjadi `in_treatment`.
- `discharge(stay, data, actor)`
  - Wajib ringkasan pulang.
  - Status `discharged`.
- `markDied(stay, data, actor)`
  - Wajib ringkasan akhir.
  - Status `died`.

Rawat inap yang sudah pulang/meninggal/transfer tidak dapat diubah lagi.

### 3.5 `MedicationAdministrationService`

- `administer(stay, data, actor)`
  - Rawat inap harus aktif.
  - `idempotency_key` wajib, maksimal 80 karakter.
  - Key yang sama pada rawat inap yang sama mengembalikan baris lama.
  - Key yang sama pada rawat inap lain menghasilkan konflik.
  - Obat dan batch harus berada di klinik yang sama.
  - Sumber `prescription` wajib memiliki `prescription_item_id` dari klinik
    yang sama.
  - Sumber non-resep tidak boleh menyertakan `prescription_item_id`.
  - Dosis aktual tidak boleh negatif.
  - Status pemberian divalidasi.

## 4. Integrasi Rekam Medis

- `computeContentHash()` memuat:
  - template_key/template_version
  - rujukan (arah, nomor, status, hash dokumen, void)
  - rawat inap dan pemberian obat
- `MedicalRecordVersionService` memuat seluruh rujukan, rawat inap, log lokasi,
  dan pemberian obat ke snapshot.
- Schema snapshot dinaikkan ke `1.4`.
- `MedicalRecordRevisionService` memuat rujukan dan rawat inap.
- `ClinicalEncounter` mendapat relasi `inpatientStay()` dan
  `medicationAdministrations()`.
- `MedicalRecord` mendapat relasi `inpatientStay()` dan `exports()`.

## 5. Pengujian

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P03LifecycleAndAuditChainTest.php \
  tests/Feature/P22ConsentWelfareEmergencyTest.php \
  tests/Feature/P23TemplatesReferralsExportsInpatientTest.php
```

Hasil:

- **16 passed, 154 assertions**
- `P23TemplatesReferralsExportsInpatientTest`: **4 passed, 62 assertions**
  - Template v1 → v2, versi lama nonaktif, schema tervalidasi.
  - Rujukan keluar punya nomor + hash, verifikasi hanya dokter, returned,
    void wajib alasan, rujukan void tidak dapat berubah, rujukan masuk tanpa
    nomor.
  - Token ekspor: hash tidak sama dengan token, sekali pakai, kedaluwarsa,
    dicabut, terikat rekam medis, dan TTL > 24 jam ditolak.
  - Rawat inap: satu encounter satu stay, riwayat lokasi bertambah,
    pemberian obat idempoten, resep tanpa item ditolak, discharge wajib
    ringkasan, obat setelah pulang ditolak.
- Regresi P03 + P22 + P23: **16 passed, 154 assertions**.
- `php -l` bersih.
- Migration berhasil di `pet_heal_test` dan `pet_heal`.

**Catatan jujur:** full test suite tidak dijalankan ulang pada putaran ini.

## 6. Data pada DB `pet_heal`

- Migration `000013`: **Ran**
- Total tabel: **86**
- `clinical_templates`: 0
- `medical_record_exports`: 0
- `inpatient_stays`: 0
- `inpatient_location_logs`: 0
- `medication_administrations`: 0

Tidak ada backfill karena belum ada data lama untuk template/ekspor/rawat inap.

## 7. Batasan yang Masih Terbuka

- Belum ada route/controller/UI untuk:
  - template klinis
  - rujukan
  - ekspor
  - rawat inap
  - pemberian obat
- Belum ada pembuatan berkas PDF/JSON/CSV nyata dan endpoint unduh.
  Service saat ini mengelola token dan metadata ekspor.
- `file_hash` belum diisi otomatis karena berkas belum dibuat.
- Setiap akses/penolakan/pencabutan ekspor **belum** menulis `audit_logs`.
  Ini wajib sebelum produksi.
- Pemberian obat belum mengurangi stok farmasi; ia mencatat pemberian klinis.
  Pengurangan stok tetap menjadi ranah dispensing.
- Belum ada UI peringatan interaksi obat atau verifikasi hak akses batch.
- Belum ada barcode/kandang, label spesimen, atau integrasi alat.
- Rawat inap belum terhubung ke billing/kamar.
- Belum ada trigger database untuk melarang update/delete ekspor atau
  rujukan void; aturan baru ditegakkan di service.
- Belum ada portal pemilik untuk meminta ekspor/rujukan.

## 8. Keputusan Penting

- Template immutable; perubahan = versi baru.
- Nomor rujukan hanya untuk rujukan keluar dan unik per klinik.
- Rujukan dibatalkan dengan `void`, bukan dihapus.
- Token ekspor hanya disimpan sebagai hash.
- Token ekspor sekali pakai, maksimal 24 jam, dan terikat konteks.
- `access_count` maksimal 1 untuk token sekali pakai.
- Encounter hanya boleh punya satu rawat inap.
- Riwayat lokasi dan pemberian obat append-only melalui service.
- Pemberian obat wajib idempotency key.
- Ekspor bukan sumber isi klinis; isi tetap mengacu pada versi rekam medis.
