# P1.3 — Vital Berulang dan Catatan Klinis Bertanda Tangan

Status: **SELESAI (lapisan data + service + API additive)**  
Tanggal: 26 November 2026  
Migration: `2026_11_26_000010_create_vital_measurements_and_harden_clinical_notes.php`

## 1. Tujuan

P1.3 menutup dua kelemahan klinis:

- Tanda vital sebelumnya hanya satu baris per rekam medis
  (`medical_record_vitals.medical_record_id` unik), sehingga perubahan kondisi
  pasien tidak dapat dipantau dari waktu ke waktu.
- Catatan klinis sudah punya kolom `signed`/`correction_of_id`, tetapi belum
  ada service yang menghitung hash, menandatangani, mengoreksi, dan menolak
  perubahan isi catatan yang sudah sah.

Prinsip yang dipakai:

- Data lama **tidak dihapus** dan kontrak Android **tidak berubah**.
- Snapshot terbaru tetap ada untuk UI lama; riwayat lengkap ditambahkan.
- Catatan bertanda tangan bersifat immutable di service **dan** database.
- Koreksi selalu membuat catatan baru, bukan menimpa catatan lama.
- Semua data memakai `clinic_id`, `encounter_id`, dan `pet_id` yang benar.

## 2. Perubahan Skema

### 2.1 Tabel baru `vital_measurements`

Menyimpan banyak pengukuran per encounter/rekam medis:

| Kolom | Keterangan |
|---|---|
| `clinic_id` | Isolasi tenant wajib. |
| `encounter_id` | Nullable untuk data lama yang belum punya encounter. |
| `medical_record_id` | Nullable, diisi bila encounter sudah punya rekam medis. |
| `pet_id` | Pasien yang diukur. |
| `legacy_vital_id` | Unik; menandai baris hasil backfill agar idempotent. |
| `temperature_c`, `heart_rate`, `respiratory_rate`, `crt_seconds`, `mucosa`, `hydration`, `bcs`, `pain_score`, `weight_kg`, `notes` | Nilai klinis. |
| `measured_at` | Waktu pengukuran. |
| `source` | `manual`, `legacy`, atau `device`. |
| `shift` | `pagi`, `siang`, `malam` untuk monitoring rawat inap. |
| `status` | `recorded` atau `void`. |
| `recorded_by`, `void_reason`, `voided_at`, `voided_by` | Jejak pelaku. |

Index: encounter+waktu, rekam medis+waktu, hewan+waktu, klinik+status+waktu.

### 2.2 `clinical_notes` (additive)

- Kolom `shift` (`pagi`/`siang`/`malam`).
- Index `(clinic_id, note_type, shift)` untuk monitoring per shift.
- Kolom lama tetap dipertahankan: `content_hash`, `signed_by`, `signed_at`,
  `correction_of_id`, `void_*`.

### 2.3 Penjaga database (MySQL trigger)

1. `clinical_notes_signed_immutable`
   - Catatan `signed` hanya boleh tetap `signed` atau menjadi `void`.
   - Isi SOAP/body/tipe/shift/hash/tanda tangan tidak boleh berubah.
   - `void` wajib punya alasan, waktu, dan pelaku.
2. `clinical_notes_signed_no_delete`
   - Catatan `signed` tidak boleh dihapus.
3. `vital_measurements_no_update`
   - Satu-satunya perubahan yang diizinkan adalah `recorded` → `void` beserta
     alasan/waktu/pelaku; nilai klinis tidak boleh diubah.
4. `vital_measurements_no_delete`
   - Riwayat pengukuran tidak boleh dihapus.

Pada driver non-MySQL trigger dilewati, tetapi penjaga di model/service tetap
berjalan.

## 3. Model

- `VitalMeasurement`
  - Konstanta status, sumber, dan shift.
  - Relasi ke klinik, encounter, rekam medis, hewan, perekam, pembatal.
  - Scope `recorded()` dan `forClinic()`.
  - `toSeriesArray()` untuk API/snapshot.
- `ClinicalNote`
  - Kolom `shift` ditambahkan.
  - Konstanta `SHIFTS`.
  - Event `updating` menolak perubahan isi catatan `signed`.
  - Event `deleting` menolak penghapusan catatan `signed`.
  - Helper `isDraft()` dan `hasCorrections()`.
- `MedicalRecord`
  - Relasi baru `vitalMeasurements()`.
  - `computeContentHash()` sekarang membaca seluruh seri vital.
- `ClinicalEncounter`
  - Relasi baru `vitalMeasurements()`.

## 4. Service

### 4.1 `VitalMeasurementService`

- `recordForRecord(MedicalRecord, data, actor)`
  - Membuat baris riwayat baru.
  - Memperbarui `medical_record_vitals` sebagai snapshot terbaru agar form dan
    API lama tetap bekerja.
  - Bila nilai vital sama dengan pengukuran terakhir, tidak membuat duplikat.
  - Memilih shift otomatis dari jam pengukuran bila tidak dikirim.
- `recordForEncounter(ClinicalEncounter, data, actor)`
  - Untuk monitoring sebelum/ketika rekam medis belum dibuat.
  - Otomatis menautkan `medical_record_id` bila encounter sudah punya rekam medis.
- `seriesForRecord()` / `seriesForEncounter()`
  - Mengembalikan seri berurutan; baris `void` disembunyikan kecuali diminta.
- `void(measurement, reason, actor)`
  - Wajib alasan; tidak mengubah nilai asli; mencegah void ganda.
- Penjaga klinik: aktor dari klinik lain mendapat `404 VITAL_CLINIC_MISMATCH`.

### 4.2 `ClinicalNoteService`

- `create(encounter, data, actor)`
  - Status awal `draft`, tipe divalidasi, `recorded_at` diisi.
  - `medical_record_id` diambil dari encounter.
- `update(note, data, actor)`
  - Hanya untuk `draft`; catatan `signed` ditolak
    (`409 CLINICAL_NOTE_NOT_DRAFT`).
- `sign(note, actor)`
  - Hanya dokter (`403 CLINICAL_NOTE_SIGNER_INVALID`).
  - Mengisi `signed_by`, `signed_at`, dan SHA-256 isi kanonik.
  - Hash mencakup klinik, encounter, rekam medis, tipe, shift, SOAP/body,
    perekam, waktu, dan `correction_of_id`.
- `correct(original, data, actor)`
  - Hanya untuk catatan yang sudah `signed`.
  - Membuat catatan baru bertipe `correction` dengan `correction_of_id`.
  - Catatan asli tetap utuh dan tetap `signed`.
- `void(note, reason, actor)`
  - Hanya catatan `signed`; wajib alasan; isi tidak diubah.
- `monitoring(encounter, data, actor)`
  - Membuat catatan tipe `monitoring` dengan shift eksplisit/otomatis.

## 5. Integrasi dengan Alur Lama

`persistVitals()` di panel admin dan portal dokter sekarang memanggil
`VitalMeasurementService::recordForRecord()`.

Akibatnya:

- Form lama tetap menyimpan tanda vital seperti biasa.
- Setiap perubahan nilai vital menghasilkan riwayat baru.
- Simpan ulang tanpa perubahan tidak menggandakan riwayat.
- `medical_record_vitals` tetap berisi nilai terbaru untuk tampilan lama.
- Revisi rekam medis dan versi final sekarang juga memuat `vital_series`.

## 6. API Additive

Payload pemilik tidak mendapat top-level key baru. Key `vitals` tetap ada dan
kini berisi tambahan `vital_series`:

```json
{
  "vitals": {
    "temperature_c": "39.2",
    "heart_rate": 156,
    "vital_series": [
      {
        "id": 1,
        "measured_at": "2026-11-26 10:00:00",
        "shift": "pagi",
        "source": "manual",
        "temperature_c": "38.5",
        "heart_rate": 140
      }
    ]
  }
}
```

Kontrak Android lama tetap lulus: top-level keys tidak berubah, field lama
tetap ada, dan `vital_series` hanya tambahan di dalam objek `vitals`.

## 7. Snapshot dan Integritas

- `MedicalRecord::computeContentHash()` memuat `vital_series`.
- `MedicalRecordVersionService::buildSnapshot()` memuat `vital_series` dan
  schema version dinaikkan ke `1.1`.
- `MedicalRecordRevisionService::snapshot()` memuat `vital_series`.
- Dengan demikian versi final tidak hanya menyimpan vital terakhir, tetapi
  seluruh riwayat pengukuran yang sah.

## 8. Backfill

```bash
php artisan medical-records:backfill-vitals --dry-run
php artisan medical-records:backfill-vitals
```

- Menyalin setiap baris `medical_record_vitals` lama ke `vital_measurements`.
- Idempotent melalui `legacy_vital_id` unik.
- Tidak mengubah atau menghapus tabel lama.

Hasil pada DB `pet_heal`: 0 snapshot lama, 0 baris dibuat (memang belum ada
data vital lama). Pada instalasi yang punya data, command ini yang dipakai.

## 9. Pengujian

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P03LifecycleAndAuditChainTest.php \
  tests/Feature/P13VitalsAndClinicalNotesTest.php

DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P01CharacterizationTest.php \
  tests/Feature/MedicalRecordSoapAndAttachmentsTest.php
```

Hasil:

- P03 + P13: **14 passed, 86 assertions**.
- P13: **7 passed, 43 assertions**.
  - Banyak vital per encounter, snapshot lama berisi nilai terbaru, simpan
    ulang tidak menggandakan.
  - Void wajib alasan, dikecualikan dari seri, void ganda ditolak.
  - Tanda tangan menghasilkan hash 64 karakter dan catatan menjadi immutable
    di service, model, dan database.
  - Koreksi membuat catatan baru; catatan asli tetap `signed`.
  - Admin tidak boleh menandatangani; monitoring menyimpan shift.
  - API pemilik memuat `vital_series` di dalam `vitals` tanpa key top-level baru.
- P01 + SOAP/attachments: **10 passed, 41 assertions** (kontrak Android dan
  alur lampiran tetap aman).
- `php -l` bersih untuk file baru/berubah.
- Migration berhasil di `pet_heal_test` dan `pet_heal`.

**Catatan jujur:** full test suite tidak dijalankan ulang pada putaran ini.
Yang dijalankan hanya tes tertarget di atas.

## 10. Batasan yang Masih Terbuka

- Belum ada layar/route UI untuk membuat, menandatangani, mengoreksi, atau
  mem-void catatan klinis; service dan database sudah siap.
- Belum ada layar grafik/riwayat vital; API sudah mengirim `vital_series`.
- Monitoring rawat inap belum terhubung ke tabel `inpatient_stays` (P2.3).
- Belum ada notifikasi otomatis bila tanda vital di luar rentang normal.
- Backfill mengisi `shift = null` untuk data lama karena jam asli tidak dapat
  dibuktikan; pengukuran baru mengisi shift otomatis/eksplisit.
- Belum ada endpoint khusus `GET /vital-series`; data saat ini ikut payload
  rekam medis.

## 11. Keputusan Penting

- `medical_record_vitals` tetap dipertahankan sebagai snapshot kompatibilitas,
  bukan sumber kebenaran riwayat.
- Penghapusan data vital tidak diizinkan; kesalahan dikoreksi dengan `void`.
- Koreksi catatan klinis selalu baris baru; catatan lama tidak diedit.
- Hanya dokter yang menandatangani catatan klinis.
- Shift divalidasi ke `pagi`/`siang`/`malam`; nilai lain menjadi `null`.
- Schema snapshot versi dinaikkan ke `1.1` karena bentuk snapshot bertambah.
