# P0.3 — Lifecycle, Versi Immutable, Amendment, dan Audit Chain

Status: **implementasi teknis selesai dan teruji terarah (targeted tests)**.
Tanggal verifikasi: 26 November 2026.
Rujukan utama: `docs/REKAM_MEDIS_ROADMAP_REGULASI.md` §4.10–§4.12, §6 M3–M5, §P0.3.

> Catatan kejujuran: setelah perubahan P0.3, **full suite tidak dijalankan ulang**
> sesuai permintaan pengguna. Yang dijalankan adalah test terarah P0.3,
> P0.1/P0.2, governance, SOAP, serta integrasi admin/end-to-end yang menyentuh
> rekam medis. Hasilnya dicatat di bagian 11.

---

## 1. Tujuan P0.3

P0.3 menutup celah terbesar rekam medis sebelum fitur klinis diperluas:

1. Status rekam medis memiliki alur yang jelas: `draft`, `in_review`, `final`,
   `void`, `archived`. `amended` **tidak pernah** menjadi status rekam medis.
2. Setiap finalisasi menghasilkan snapshot immutable dan hash kanonik.
3. Perubahan setelah final hanya melalui amendment, bukan edit langsung.
4. Hanya satu amendment aktif per rekam medis.
5. Amendment klinis hanya disetujui dokter.
6. Admin tidak dapat memfinalkan rekam medis saat enforcement aktif.
7. Revisi rekam medis memiliki hash berantai dan database menolak manipulasi
   langsung.

---

## 2. Migrasi yang Ditambahkan

| Migrasi | Isi |
|---|---|
| `2026_11_26_000003_create_medical_record_versions_and_amendments.php` | Tabel `medical_record_versions` dan `medical_record_amendments`, termasuk `pending_key` unik. |
| `2026_11_26_000004_add_lifecycle_columns_to_medical_records.php` | `current_version_id`, `version`, `in_review_at/by`, `last_amended_at`, `retention_until`, `legal_hold*`, `template_key/version`; default status menjadi `draft`; kolom audit request. |
| `2026_11_26_000005_add_hash_chain_to_medical_record_revisions.php` | `prev_hash`, `entry_hash`, dan index rantai. |
| `2026_11_26_000006_make_medical_record_revisions_append_only.php` | Trigger MySQL menolak UPDATE setelah hash terisi dan menolak DELETE. |

### 2.1 Prinsip kompatibilitas

- Tidak ada kolom lama yang dihapus.
- `medical_records.booking_id` tetap nullable dan unik seperti hasil P0.2.
- Kolom proyeksi lama (`subjective`, `objective`, `assessment`, `plan`,
  `diagnosis`, `treatment`, dan seterusnya) tetap diperbarui sebagai cache
  kompatibilitas Android/web lama.
- Sumber kebenaran isi adalah `medical_record_versions.snapshot` yang ditunjuk
  `medical_records.current_version_id`.
- Default `status` berubah menjadi `draft`; data lama tidak diubah otomatis.
  Backfill versi memberi snapshot pada record lama tanpa mengubah statusnya.

---

## 3. Struktur Tabel

### 3.1 `medical_record_versions`

Kolom penting:

- `clinic_id` — tenant;
- `medical_record_id`;
- `version_no` — unik per rekam medis;
- `snapshot` — JSON kanonik;
- `content_hash` — SHA-256 snapshot;
- `legacy_content_hash` — hash lama sebelum P0.3, disimpan sebagai bukti;
- `finalized_at`, `finalized_by`;
- `amendment_id` — diisi bila versi lahir dari amendment;
- `created_at`.

Baris versi tidak memiliki status lifecycle. Status hanya berada di
`medical_records`. Snapshot tidak boleh diubah; koreksi selalu membuat versi
baru.

### 3.2 `medical_record_amendments`

Kolom penting:

- `medical_record_id`, `clinic_id`;
- `base_version_id` — versi yang dikoreksi;
- `amendment_type`: `clinical` atau `administrative`;
- `requested_by`, `requested_at`, `reason`;
- `before`, `after` — snapshot JSON;
- `status`: `requested`, `approved`, `rejected`, `applied`, `cancelled`;
- `pending_key` — unik; berisi ID rekam medis selama amendment aktif, lalu
  `NULL` setelah selesai;
- `reviewed_by`, `reviewed_at`, `review_notes`;
- `applied_version_id`, `applied_at`;
- `signature_path` — placeholder tanda tangan reviewer untuk fase berikutnya.

`pending_key` unik adalah penjaga database agar dua amendment tidak aktif
sekaligus, bahkan bila dua request datang bersamaan.

---

## 4. Service Baru

### 4.1 `MedicalRecordVersionService`

File: `app/Services/MedicalRecordVersionService.php`.

Tanggung jawab:

- membangun snapshot dari record + encounter + resep + vital + lampiran +
  lab + consent + referral;
- menyerialisasi JSON secara kanonik (key diurutkan, UTF-8, timestamp UTC);
- menghitung SHA-256;
- membuat baris versi baru;
- memperbarui `current_version_id`, `version`, dan `content_hash` pada record.

Snapshot memuat:

- identitas record, encounter, clinic, pet, doctor;
- status saat snapshot;
- SOAP, diagnosis, treatment, medicine, notes, outcome;
- biaya dan jadwal kontrol;
- item resep;
- vital;
- hasil lab;
- consent;
- referral;
- manifest lampiran (`attachment_id`, `sha256`, nama, mime, ukuran, waktu).

### 4.2 `MedicalRecordLifecycleService`

File: `app/Services/MedicalRecordLifecycleService.php`.

Method:

- `finalize(record, actor)` — membuat/memakai versi yang sesuai lalu
  menetapkan status `final`;
- `markInReview(record, actor)` — `draft` → `in_review`;
- `returnToDraft(record)` — `draft`/`in_review` → `draft`.

Aturan:

- `void` dan `archived` tidak dapat difinalkan;
- record final tidak dapat dikembalikan menjadi draft; koreksi memakai
  amendment;
- bila `lifecycle_enforcement_enabled=true` dan aktor bukan dokter:
  `ADMIN_CANNOT_FINALIZE` (HTTP 403);
- finalisasi idempoten: konten sama tidak membuat versi duplikat;
- semua perubahan dilakukan dalam transaksi dan memakai row lock.

### 4.3 `MedicalRecordAmendmentService`

File: `app/Services/MedicalRecordAmendmentService.php`.

Method: `request`, `approve`, `apply`, `reject`, `cancel`.

Aturan yang ditegakkan:

| Aturan | Kode |
|---|---|
| Amendment hanya untuk record `final` | `RECORD_NOT_FINAL` |
| Fitur amendment harus aktif | `AMENDMENTS_DISABLED` |
| Alasan wajib | `AMENDMENT_REASON_REQUIRED` |
| Tipe harus clinical/administrative | `INVALID_AMENDMENT_TYPE` |
| Hanya satu amendment aktif | `AMENDMENT_ALREADY_PENDING` |
| Reviewer klinis harus dokter | `CLINICAL_REVIEWER_REQUIRED` |
| Pengaju tidak boleh menyetujui sendiri | `REQUESTER_CANNOT_APPROVE` |
| Harus disetujui sebelum diterapkan | `AMENDMENT_NOT_APPROVED` |
| Versi dasar harus versi aktif | `STALE_VERSION` |
| Hanya pengaju/super admin yang boleh membatalkan | `AMENDMENT_CANCEL_FORBIDDEN` |

Penerapan amendment:

1. mengunci record dan amendment;
2. memeriksa `base_version_id == current_version_id`;
3. memproyeksikan hanya field klinis/administratif yang diizinkan;
4. menyimpan proyeksi;
5. membuat versi baru dengan `amendment_id`;
6. memperbarui pointer versi dan `last_amended_at`;
7. menandai amendment `applied` dan mengosongkan `pending_key`.

Field yang tidak boleh diubah lewat amendment: `id`, `clinic_id`, `pet_id`,
`doctor_id`, `encounter_id`, `booking_id`, `status`, `current_version_id`,
`version`, `content_hash`, dan kolom audit.

### 4.4 `AuditChainService`

File: `app/Services/AuditChainService.php`.

- Baris pertama memakai `prev_hash = 64 nol`.
- `entry_hash = SHA-256(prev_hash + "|" + canonical_json(baris))`.
- Payload mencakup `medical_record_id`, `changed_by`, `action`, `before`,
  `after`, `reason`, IP, user agent, dan `created_at` UTC.
- `MedicalRecordRevisionService` sekarang mengambil row lock pada record,
  membaca hash revisi terakhir, lalu menulis hash baru dalam transaksi.

---

## 5. Alur Status

```
draft ──markInReview──> in_review ──finalize──> final
  ^                         |                    |
  └──── returnToDraft ──────┘                    |
                                                 ├── void (alasan wajib)
                                                 ├── archived (retensi)
                                                 └── amendment → versi baru (tetap final)
```

Larangan:

- `final` tidak dapat diedit langsung saat enforcement aktif;
- `final` tidak dapat kembali menjadi `draft`;
- `void` tidak dapat difinalkan atau diedit;
- `amended` bukan status.

---

## 6. Perubahan Controller

### 6.1 Admin

File: `app/Http/Controllers/Admin/MedicalRecordController.php`.

- `finalize()` diganti dari model ke `MedicalRecordLifecycleService`.
- Saat `lifecycle_enforcement_enabled=true`:
  - admin yang membuat record menghasilkan **draft**, bukan final;
  - admin tidak dapat mengubah record final; diarahkan ke amendment;
  - finalisasi tidak dipanggil.
- Saat flag off, perilaku lama dipertahankan untuk masa transisi.

### 6.2 Dokter

File: `app/Http/Controllers/Doctor/DoctorMedicalRecordController.php`.

- Finalisasi melalui service yang sama.
- Dokter tetap dapat memfinalkan dan menghasilkan versi immutable.

### 6.3 Model

`MedicalRecord::finalize()` tetap ada untuk kompatibilitas, tetapi menolak
aktor non-dokter saat enforcement aktif. Jalur utama aplikasi memakai service.

---

## 7. Command Baru

| Command | Fungsi |
|---|---|
| `medical-records:backfill-versions {--dry-run} {--chunk=200}` | Membuat versi 1 untuk record lama berstatus final/void/archived. |
| `audit:backfill-chain {--dry-run}` | Mengisi hash revisi lama; baris yang sudah berhash tidak ditulis ulang. |
| `audit:verify-chain {--record=} {--json}` | Memverifikasi rantai per rekam medis; exit failure bila ada masalah. |

---

## 8. Trigger Append-Only

Migrasi `000006` membuat dua trigger MySQL:

- `medical_record_revisions_no_update` — menolak UPDATE jika
  `OLD.entry_hash IS NOT NULL`. Baris lama tanpa hash masih boleh diisi sekali
  oleh backfill.
- `medical_record_revisions_no_delete` — menolak DELETE.

Konsekuensi:

- manipulasi SQL langsung terdeteksi/ditolak;
- penghapusan rekam medis ikut tertahan karena revisi tidak boleh dihapus;
- rollback migrasi menghapus trigger terlebih dahulu.

Pada database non-MySQL, trigger tidak dibuat. Untuk deployment production,
grant database aplikasi tetap harus dibatasi (tanpa `DROP`, tanpa akses
superuser) sebagai lapisan kedua.

---

## 9. Konfigurasi

File: `config/medical_records.php`.

```php
'encounters_enabled' => env('MEDICAL_RECORDS_ENCOUNTERS_ENABLED', true),
'lifecycle_enforcement_enabled' => env('MEDICAL_RECORDS_LIFECYCLE_ENFORCEMENT_ENABLED', false),
'amendments_enabled' => env('MEDICAL_RECORDS_AMENDMENTS_ENABLED', false),
```

Keputusan rollout:

- `lifecycle_enforcement_enabled` dan `amendments_enabled` **default OFF**
  sesuai keputusan roadmap (flag klinis default OFF).
- P0.3 sudah menyediakan dan menguji perilaku saat flag ON.
- Sebelum production, flag dinyalakan setelah full suite + UAT, bersamaan
  dengan pelatihan admin bahwa record buatan admin berstatus draft sampai
  dokter memfinalkan.

---

## 10. Hasil Migrasi dan Backfill Database Lokal

Database: `pet_heal`.

Migrasi:

- `2026_11_26_000003` DONE
- `2026_11_26_000004` DONE
- `2026_11_26_000005` DONE
- `2026_11_26_000006` DONE

Backfill versi:

- record tanpa versi sebelum backfill: **14**
- versi dibuat: **14**
- record tanpa `current_version_id` setelah backfill: **0**
- seluruh record lama berstatus `final` (14/14), snapshot dibuat dari kondisi
  saat backfill; hash lama disimpan di `legacy_content_hash`.

Backfill audit chain:

- revisi lama: **0** baris (tabel revisi kosong saat backfill)
- `audit:verify-chain`: **0 masalah, rantai valid**.

Pemeriksaan relasi P0.2 tetap bersih:

- `medical-records:check-links --json` → `total_issues: 0`.

---

## 11. Test yang Dijalankan

### 11.1 Test baru P0.3

File: `tests/Feature/P03LifecycleAndAuditChainTest.php`.

Hasil: **7 passed, 43 assertions**.

Cakupan:

1. finalisasi membuat versi 1 immutable dan idempoten;
2. route admin menghasilkan draft saat enforcement aktif;
3. admin ditolak memfinalkan dengan kode `ADMIN_CANNOT_FINALIZE`;
4. `draft` → `in_review` → `draft`; record final tidak dapat diturunkan;
5. amendment clinical request → approve → apply menghasilkan versi 2;
6. amendment dengan base version basi ditolak `STALE_VERSION`;
7. hash chain terverifikasi, UPDATE dan DELETE langsung ditolak trigger.

### 11.2 Regresi terarah

| Kelompok | Hasil |
|---|---|
| P0.1 characterization + P0.2 encounter + governance + SOAP | **18 passed, 74 assertions** |
| P0.3 | **7 passed, 43 assertions** |
| `Phase4WebAdminTest` + `Phase6IntegrationWorkflowsTest` | **25 passed, 180 assertions** |

### 11.3 Yang **tidak** dijalankan

- Full suite `php artisan test` tidak dijalankan ulang setelah P0.3.
- Alasan: permintaan pengguna agar tidak berulang kali menjalankan test panjang.
- Konsekuensi: sebelum production, full suite wajib dijalankan sekali dan
  hasilnya dicatat di dokumen rilis.

---

## 12. Batasan dan Pekerjaan Lanjutan

Belum termasuk P0.3 (sengaja ditunda):

1. **UI amendment.** Saat ini service dan database siap; belum ada halaman
   admin/dokter untuk mengajukan, menyetujui, atau menerapkan amendment.
2. **Snapshot kredensial.** `medical_record_credential_snapshots` baru akan
   dibuat pada P1.1 bersama `professional_credentials`.
3. **Retensi dan legal hold.** Kolom sudah ada, tetapi command retensi belum
   membaca `legal_hold`; penyempurnaan di P3.
4. **Tanda tangan reviewer.** `signature_path` tersedia, tetapi alur unggah dan
   verifikasi tanda tangan belum dibuat.
5. **Rekonsiliasi proyeksi vs snapshot.** Command khusus untuk mendeteksi
   drift antara kolom lama dan snapshot aktif belum dibuat; saat ini drift
   dicegah oleh service, bukan command.
6. **Grant database production.** Trigger adalah lapisan aplikasi/database,
   tetapi revokasi `UPDATE`/`DELETE` untuk user aplikasi harus dilakukan di
   server production, bukan di migration.

---

## 13. Kriteria Lulus P0.3

| Kriteria | Status |
|---|---|
| Lima status berfungsi; `amended` bukan status | Lulus (test) |
| `current_version_id` menunjuk versi tertinggi dan proyeksi sama | Lulus (test) |
| Amendment klinis hanya disetujui dokter | Lulus (test) |
| Hanya satu amendment aktif | Lulus (test + unique index) |
| Admin tidak dapat memfinalkan saat enforcement | Lulus (service, model, route create; test) |
| Serializer pemilik tidak memuat key sensitif | Sudah dari P0.1/P1, tidak berubah |
| Audit chain terverifikasi; manipulasi SQL ditolak | Lulus (test trigger) |
| Backfill versi record lama | Lulus (14/14 di DB lokal) |

Catatan: baris “Admin tidak dapat memfinalkan” berlaku saat
`lifecycle_enforcement_enabled=true`; flag tetap OFF sampai rollout production
sesuai keputusan roadmap.
