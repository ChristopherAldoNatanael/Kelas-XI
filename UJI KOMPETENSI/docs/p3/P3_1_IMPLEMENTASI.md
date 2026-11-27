# P3.1 — Laporan Penyakit Menular dan Tenggat 24 Jam

Status: **SELESAI (lapisan data + service)**  
Tanggal: 26 November 2026  
Migration: `2026_11_26_000014_create_notifiable_disease_reports.php`

## 1. Tujuan

P3.1 mencatat kewajiban pelaporan penyakit menular strategis/eksotik:

- indikasi ditemukan,
- tenggat 24 jam,
- jalur pelaporan resmi,
- nomor referensi iSIKHNAS bila digunakan,
- bukti pelaporan,
- verifikasi dokter,
- eskalasi keterlambatan,
- pembatalan beralasan tanpa menghapus riwayat.

**Penting:** PetHeal tidak mengklaim integrasi API iSIKHNAS. Tabel dan service
ini hanya mencatat kewajiban, tenggat, bukti, dan referensi pelaporan yang
dilakukan melalui kanal resmi.

## 2. Skema `notifiable_disease_reports`

| Kolom | Keterangan |
|---|---|
| `clinic_id` | Isolasi tenant. |
| `medical_record_id` | Rekam medis terkait. |
| `encounter_id` | Encounter klinis wajib. |
| `disease_code` | Kode penyakit, dinormalisasi huruf besar. |
| `disease_name` | Nama penyakit. |
| `classification` | `suspected`, `probable`, `confirmed`. |
| `indicated_at` | Saat indikasi ditemukan. |
| `report_deadline_at` | `indicated_at + 24 jam`. |
| `status` | `draft`, `ready`, `reported`, `verified`, `cancelled`. |
| `reported_at` | Waktu pelaporan resmi. |
| `escalated_at` | Waktu eskalasi tenggat terlewati. |
| `reported_by` | Pelapor. |
| `isikhnas_reference` | Nomor/bukti dari sistem resmi. |
| `evidence_path` | Berkas bukti pelaporan. |
| `verified_at`, `verified_by` | Verifikasi dokter. |
| `cancel_reason`, `cancelled_at`, `cancelled_by` | Pembatalan beralasan. |
| `notes` | Catatan. |

Index:

- `(clinic_id, status)`
- `(status, report_deadline_at)`
- `(medical_record_id, disease_code)`

Keterlambatan **tidak** mengubah status. `isLate()` dihitung terpisah dari
`reported_at` dan `report_deadline_at`.

## 3. Service `NotifiableDiseaseService`

### `create(record, data, actor)`

- Wajib encounter.
- `disease_code` wajib, huruf/angka/titik/garis/slash, maksimal 60 karakter,
  dinormalisasi ke huruf besar.
- `disease_name` wajib.
- `classification` harus `suspected`, `probable`, atau `confirmed`.
- `indicated_at` tidak boleh di masa depan.
- Tenggat dihitung dari konfigurasi
  `medical_records.notifiable_report_deadline_hours` (default 24 jam).
- Status awal `draft`.
- Isolasi klinik: `404 NOTIFIABLE_CLINIC_MISMATCH`.

### `markReady()`

- Hanya dari `draft`.
- Status menjadi `ready`.

### `markReported(report, data, actor)`

- Hanya dari `ready`.
- Wajib salah satu: `isikhnas_reference` atau `evidence_path`.
- `reported_at` tidak boleh di masa depan.
- Row dikunci saat transisi untuk mencegah balapan status.
- Status menjadi `reported`.
- Catatan baru ditambahkan, catatan lama tidak ditimpa.
- Error penting:
  - `NOTIFIABLE_EVIDENCE_REQUIRED`
  - `NOTIFIABLE_STATUS_INVALID`
  - `NOTIFIABLE_REPORTED_IN_FUTURE`

### `verify(report, actor)`

- Hanya role `doctor`.
- Hanya dari status `reported`.
- Status menjadi `verified`.
- Error: `NOTIFIABLE_VERIFIER_NOT_DOCTOR`.

### `cancel(report, reason, actor)`

- Wajib alasan.
- Laporan `verified` tidak dapat dibatalkan.
- Tidak menghapus baris.
- Status menjadi `cancelled`, dengan `cancel_reason`, `cancelled_at`,
  `cancelled_by`.
- Laporan cancelled tidak dapat berpindah status lagi.
- Error:
  - `NOTIFIABLE_CANCEL_REASON_REQUIRED`
  - `NOTIFIABLE_VERIFIED_IMMUTABLE`
  - `NOTIFIABLE_CANCELLED`

### `escalateOverdue()`

- Menandai `escalated_at` untuk laporan yang:
  - belum `reported`, `verified`, atau `cancelled`,
  - `report_deadline_at` sudah lewat,
  - `escalated_at` masih NULL.
- Idempoten: laporan yang sudah dieskalasi tidak dihitung lagi.

## 4. Command dan Scheduler

Command:

```bash
php artisan reports:escalate-overdue --dry-run
php artisan reports:escalate-overdue
```

Scheduler di `routes/console.php`:

- berjalan setiap 15 menit,
- `withoutOverlapping()`,
- nama: `escalate-overdue-disease-reports`.

Eskalasi hanya penanda operasional. Pelaporan resmi tetap melalui kanal
berwenang.

## 5. Integrasi Rekam Medis

- `MedicalRecord` mendapat relasi `notifiableDiseaseReports()`.
- `ClinicalEncounter` mendapat relasi `notifiableDiseaseReports()`.
- `computeContentHash()` memuat ringkasan laporan.
- Snapshot versi memuat detail laporan.
- Snapshot revisi memuat ringkasan laporan.
- Schema snapshot dinaikkan ke `1.5`.

## 6. Pengujian

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P03LifecycleAndAuditChainTest.php \
  tests/Feature/P23TemplatesReferralsExportsInpatientTest.php \
  tests/Feature/P31NotifiableDiseaseReportTest.php
```

Hasil:

- **16 passed, 143 assertions**
- `P31NotifiableDiseaseReportTest`: **5 passed, 38 assertions**
  - Laporan dibuat `draft` dengan tenggat tepat 24 jam.
  - Kode dinormalisasi menjadi huruf besar.
  - Klasifikasi tidak valid, indikasi di masa depan, dan rekam tanpa
    encounter ditolak.
  - Siklus `draft → ready → reported → verified` berjalan.
  - Pelaporan tanpa referensi/bukti ditolak.
  - Verifikasi oleh admin ditolak; dokter berhasil.
  - Laporan terlambat menghasilkan `isLate() = true` tetapi statusnya tetap
    `reported`.
  - Pembatalan tanpa alasan ditolak; laporan cancelled tidak dapat menjadi
    ready.
  - Laporan verified tidak dapat dibatalkan.
  - Command eskalasi hanya menandai laporan lewat tenggat dan idempoten.
- `php -l` bersih.
- `php artisan schedule:list` menampilkan command eskalasi.
- Migration berhasil di `pet_heal_test` dan `pet_heal`.

**Catatan jujur:** full test suite tidak dijalankan ulang pada putaran ini.

## 7. Data pada DB `pet_heal`

- Migration `000014`: **Ran**
- Total tabel: **87**
- `notifiable_disease_reports`: 0
- Eskalasi kering: 0 laporan lewat tenggat.

Tidak ada backfill karena belum ada data lama.

## 8. Batasan yang Masih Terbuka

- Belum ada route/controller/UI untuk membuat atau memproses laporan.
- Belum ada unggah bukti pelaporan dan signed URL.
- Belum ada integrasi API iSIKHNAS; ini memang di luar klaim.
- Belum ada master kode penyakit resmi di aplikasi; kode masih diisi operator.
- Belum ada notifikasi push/email ke dokter atau dinas saat tenggat dekat.
- Eskalasi baru berupa penanda `escalated_at`; belum ada workflow eskalasi
  berjenjang.
- Belum ada laporan rekap kepatuhan tenggat per klinik.
- Belum ada trigger database untuk melarang perubahan laporan verified;
  aturan masih di service.
- Belum ada mekanisme koreksi laporan verified selain membuat laporan baru.

## 9. Keputusan Penting

- Tenggat dihitung server-side dari `indicated_at + 24 jam`.
- Keterlambatan dihitung terpisah dari status.
- Bukti atau referensi resmi wajib sebelum status `reported`.
- Hanya dokter yang memverifikasi.
- Laporan tidak dihapus; pembatalan memakai alasan yang tercatat.
- Laporan verified immutable.
- Tidak ada klaim integrasi iSIKHNAS otomatis.
- Eskalasi idempoten dan berjalan terjadwal.
