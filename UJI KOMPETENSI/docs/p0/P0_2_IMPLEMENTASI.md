# P0.2 — Encounter dan Konsistensi Booking

Tanggal: 26 November 2026  
Status: **diterapkan di database lokal dan database test; suite penuh belum dijalankan ulang.**

## 1. Perubahan skema

### 1.1 `clinical_encounters`

Sumber kebenaran relasi booking.

Kolom utama:

- `clinic_id`, `pet_id`, `booking_id` (nullable, unique);
- `type`: `booking`, `walk_in`, `emergency`, `follow_up`, `inpatient`;
- `status`: `planned`, `in_progress`, `completed`, `cancelled`;
- `attending_doctor_id`, `supervising_doctor_id`;
- `chief_complaint`, `triage_level`, `opened_at`, `closed_at`, `disposition`;
- `created_by`, `updated_by`, timestamps.

### 1.2 `clinical_notes`

Catatan perkembangan per encounter.

Kolom utama:

- `clinic_id`, `encounter_id`, `medical_record_id` (nullable);
- `note_type`, `status` (`draft`, `signed`, `void`);
- SOAP dan `body`;
- `recorded_by`, `recorded_at`, `signed_by`, `signed_at`, `content_hash`;
- `correction_of_id`, `void_reason`, `voided_at`, `voided_by`.

Catatan `signed` tidak diedit. Koreksi membuat catatan baru bertipe
`correction`; catatan lama tetap `signed` atau di-void dengan alasan.

### 1.3 Perubahan `medical_records`

- `encounter_id` nullable, unique, FK ke `clinical_encounters`;
- `booking_id` menjadi nullable untuk walk-in/emergency;
- unique index lama `medical_records_booking_id_unique` dipertahankan.

### 1.4 Perubahan `bookings`

- unique index `bookings_medical_record_id_unique`.

Migrasi akan gagal dengan pesan eksplisit bila menemukan duplikat
`bookings.medical_record_id`. Tidak ada pemilihan otomatis.

## 2. Service dan command

### `RecordLinkService`

Satu-satunya penulis:

- `clinical_encounters.booking_id`;
- `medical_records.encounter_id`;
- `medical_records.booking_id`;
- `bookings.medical_record_id`.

Aturan:

- transaksi + row lock;
- validasi klinik dan hewan;
- menolak record yang sudah tertaut ke booking lain;
- menolak encounter yang sudah punya record lain;
- idempoten untuk pemanggilan berulang;
- walk-in/emergency/follow-up/inpatient tanpa booking lewat
  `openWithoutBooking()`.

### Command

```bash
php artisan medical-records:backfill-encounters
php artisan medical-records:backfill-encounters --dry-run
php artisan medical-records:check-links
php artisan medical-records:check-links --clinic=2577 --json
```

`backfill` idempoten dan mencatat konflik. `check-links` **read-only** dan
tidak pernah memperbaiki data sendiri.

## 3. Perubahan controller

`Admin\MedicalRecordController` dan `Doctor\MedicalRecordController` tidak lagi
menulis `booking_id` langsung. Alurnya:

1. record disimpan tanpa `booking_id`;
2. `RecordLinkService::linkBooking()` membuat/menemukan encounter;
3. service menulis ketiga kolom relasi;
4. resep, vital, lampiran, dan status booking diproses setelahnya.

Update record juga memanggil service secara idempoten agar record lama yang
belum punya encounter ikut tertaut saat diedit.

## 4. Hasil di database lokal

Migrasi:

```text
2026_11_26_000001_create_clinical_encounters_and_notes  DONE
2026_11_26_000002_add_unique_booking_medical_record_pointer  DONE
```

Backfill:

```text
Rekam medis tanpa encounter: 14
Berhasil ditautkan: 14
Semua rekam medis sudah memiliki encounter.
```

Pemeriksaan relasi:

```text
OK  record_without_encounter
OK  encounter_without_record
OK  booking_relation_mismatch
OK  booking_pointer_missing
OK  duplicate_booking_pointer
Total temuan: 0
```

## 5. Test yang dijalankan

```bash
php artisan test --filter=P02EncounterLinkTest
php artisan test --filter='MedicalRecordGovernanceTest|MedicalRecordSoapAndAttachmentsTest|P01CharacterizationTest|P02EncounterLinkTest'
```

Hasil:

- `P02EncounterLinkTest`: **4 passed, 24 assertions**;
- gabungan targeted: **18 passed, 74 assertions**.

Yang diuji:

- relasi tiga kolom konsisten dan idempoten;
- tautan lintas klinik ditolak tanpa menulis apa pun;
- walk-in/emergency tanpa booking berhasil;
- backfill menautkan record lama;
- `check-links` mendeteksi drift lalu bersih setelah backfill.

## 6. Batas P0.2

Belum dikerjakan pada tahap ini:

- UI walk-in/emergency;
- lifecycle `draft`/`in_review` dan versioning (P0.3);
- amendment;
- policy terpusat;
- serializer pemilik yang ketat;
- suite penuh 577 test.

Karena suite penuh belum dijalankan ulang setelah perubahan ini, P0.2 belum
boleh disebut production-ready. Database lokal sudah dimigrasi dan backfill
selesai, tetapi gate rilis tetap mensyaratkan suite penuh dan review manual
sebelum deploy.

## 7. Rollback

Urutan rollback yang aman:

1. hentikan pembuatan record baru;
2. `php artisan migrate:rollback --step=1` untuk unique pointer;
3. `php artisan migrate:rollback --step=1` untuk encounter;
4. rollback migrasi encounter sengaja menolak mengembalikan `booking_id` ke
   NOT NULL bila masih ada walk-in;
5. restore backup P0.1 bila data walk-in harus dihapus.

Backup P0.1: `C:/Users/RENI KUSWATI/petheal_p0_backup/pet_heal_20261126_190716.sql`.
