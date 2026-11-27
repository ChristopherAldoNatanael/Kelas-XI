# P3.3 — Privacy Request, Retensi, dan Legal Hold

Status: **SELESAI (lapisan data + service)**  
Tanggal: 26 November 2026  
Migration: `2026_11_26_000016_create_privacy_requests_and_legal_hold_release.php`

## 1. Tujuan

P3.3 menutup empat kebutuhan:

1. Permintaan PDP pemilik tercatat dengan SLA dan jejak penanganan.
2. Permintaan hapus tidak pernah menghapus rekam medis final; penolakan
   beralasan dicatat.
3. `retention_until` dihitung dari kebijakan klinis/finansial.
4. Legal hold menahan arsip dan pelepasannya memerlukan dua admin berbeda.

## 2. Skema

### 2.1 `privacy_requests`

| Kolom | Keterangan |
|---|---|
| `user_id` | Pemilik data. |
| `clinic_id` | Klinik terkait, nullable. |
| `request_type` | `access`, `correction`, `erasure`, `restriction`, `portability`, `objection`. |
| `status` | `received`, `verified`, `in_progress`, `completed`, `rejected`. |
| `description` | Uraian permintaan. |
| `requested_at`, `due_at` | Waktu permintaan dan SLA internal. |
| `verified_at`, `completed_at` | Jejak verifikasi/penyelesaian. |
| `handled_by` | Petugas terakhir. |
| `response_path` | Berkas jawaban. |
| `rejection_reason` | Alasan penolakan yang sah. |

Index:

- `(user_id, status)`
- `(status, due_at)`
- `(clinic_id, status)`

SLA internal default: **14 hari**, diatur lewat
`medical_records.privacy_request_due_days`. Nilai ini bukan pengganti
tenggat hukum yang berlaku dan wajib ditinjau legal.

### 2.2 `medical_records` (additive)

Kolom baru untuk pelepasan legal hold:

- `legal_hold_release_requested_at`
- `legal_hold_release_requested_by`
- `legal_hold_release_reason`
- `legal_hold_release_approved_at`
- `legal_hold_release_approved_by`

Kolom legal hold lama tetap dipertahankan.

### 2.3 `consents` (additive)

- `privacy_request_id` nullable → tautan ke permintaan PDP terkait.

## 3. Service

### 3.1 `PrivacyRequestService`

- `create(subject, data, actor)`
  - Hanya pemilik data atau admin.
  - Jenis permintaan divalidasi.
  - Uraian wajib.
  - Waktu permintaan tidak boleh di masa depan.
  - `due_at = requested_at + SLA hari`.
  - Status awal `received`.
  - Audit `privacy_request.created`.
- `verify(request, actor)`
  - Hanya admin.
  - Dari `received` → `verified`.
- `start(request, actor)`
  - Hanya admin.
  - Dari `verified` → `in_progress`.
- `complete(request, data, actor)`
  - Dari `verified`/`in_progress` → `completed`.
  - `response_path` opsional.
- `reject(request, reason, actor)`
  - Wajib alasan.
  - Status `rejected`, `completed_at` diisi.
- `erasureBlockReason(subject)`
  - Mengembalikan alasan bila rekam medis masih:
    - dalam legal hold, atau
    - final/archived dengan retensi belum berakhir.
  - Tidak menghapus data.

Semua transisi menulis `audit_logs`.

### 3.2 `RetentionService`

- `retentionUntil(record)`
  - Dasar: `finalized_at` atau `created_at`.
  - Pembayaran `not_required`/`paid`: retensi klinis default **5 tahun**.
  - Selain itu: retensi finansial default **10 tahun**.
- `applyRetention(record)`
  - Mengisi `retention_until`.
- `canArchive(record)`
  - Status `final`, bukan legal hold, dan retensi sudah lewat.
- `archive(record, actor)`
  - Menolak legal hold (`RETENTION_LEGAL_HOLD`).
  - Menolak status selain final.
  - Menolak retensi yang belum berakhir.
  - Mengubah status menjadi `archived`; **tidak menghapus** data.
  - Audit `medical_record.archived`.

### 3.3 `LegalHoldService`

- `set(record, reason, actor)`
  - Hanya admin.
  - Alasan wajib.
  - Isolasi klinik.
  - Mengisi `legal_hold`, alasan, pelaku, waktu.
  - Audit `medical_record.legal_hold.set`.
- `requestRelease(record, reason, actor)`
  - Hanya admin.
  - Wajib legal hold aktif.
  - Alasan wajib.
  - Tidak melepas hold; hanya mencatat permohonan.
  - Audit `medical_record.legal_hold.release_requested`.
- `approveRelease(record, actor)`
  - Hanya admin.
  - Wajib ada permohonan.
  - **Approver harus berbeda dari pemohon**.
  - Baru setelah itu `legal_hold = false`.
  - Audit `medical_record.legal_hold.release_approved`.

Error penting:

- `LEGAL_HOLD_ALREADY_SET`
- `LEGAL_HOLD_NOT_ACTIVE`
- `LEGAL_HOLD_REASON_REQUIRED`
- `LEGAL_HOLD_RELEASE_REASON_REQUIRED`
- `LEGAL_HOLD_RELEASE_NOT_REQUESTED`
- `LEGAL_HOLD_RELEASE_DUAL_APPROVAL_REQUIRED`
- `LEGAL_HOLD_NOT_AUTHORIZED`
- `LEGAL_HOLD_CLINIC_MISMATCH`

## 4. Command dan Scheduler

| Command | Fungsi |
|---|---|
| `medical-records:refresh-retention` | Hitung ulang `retention_until`; mendukung `--dry-run`. |
| `medical-records:archive` | Arsipkan rekam medis yang memenuhi retensi; mendukung `--dry-run`; melewati legal hold. |
| `privacy:list-overdue` | Menampilkan permintaan PDP lewat SLA (read-only). |

Scheduler:

- `refresh-retention` setiap tanggal 1 pukul 02:00.
- `archive` setiap tanggal 1 pukul 02:30.
- Keduanya `withoutOverlapping()`.

## 5. Penerapan pada DB `pet_heal`

- Migration `000016`: **Ran**.
- Total tabel: **88**.
- `medical-records:refresh-retention`: 14 rekam medis diperiksa,
  **14 `retention_until` diperbarui**.
- `medical-records:archive --dry-run`: 14 rekam medis final tanpa legal hold,
  **0 akan diarsipkan** (retensi belum habis).
- `privacy:list-overdue`: **0**.
- `privacy_requests`: **0**.
- `retention_null`: **0**.

## 6. Pengujian

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P33PrivacyRetentionLegalHoldTest.php
```

Hasil:

- **5 passed, 48 assertions**
  - Siklus permintaan PDP: `received → verified → in_progress → completed`.
  - Pemilik tidak boleh memverifikasi permintaannya sendiri.
  - Audit `privacy_request.completed` tercatat.
  - Penolakan wajib alasan.
  - Permintaan hapus terdeteksi terhalang retensi/legal hold.
  - Retensi finansial memakai 10 tahun, bukan 5 tahun.
  - Arsip ditolak saat legal hold.
  - Arsip berhasil setelah retensi habis dan hold dilepas.
  - Audit arsip tercatat.
  - Legal hold tanpa alasan ditolak.
  - Dokter tidak boleh mengelola legal hold.
  - Pemohon pelepasan tidak boleh menyetujui sendiri.
  - Admin kedua dapat menyetujui pelepasan.
  - Command refresh retensi dan daftar keterlambatan berjalan.
- `php -l` bersih.
- `php artisan schedule:list` menampilkan command retensi.

**Catatan jujur:** full test suite tidak dijalankan ulang pada putaran ini.

## 7. Batasan yang Masih Terbuka

- Belum ada UI untuk permintaan PDP, retensi, atau legal hold.
- API pemilik `POST/GET /api/privacy-requests` ditambahkan pada P3.4.
- Belum ada unggah/unduh `response_path` yang aman.
- Belum ada notifikasi tenggat SLA.
- Belum ada dashboard permintaan PDP.
- `privacy:list-overdue` hanya read-only; belum ada eskalasi otomatis.
- Belum ada mekanisme koreksi data PDP yang mengubah rekam medis final;
  koreksi klinis tetap lewat amendment.
- Permintaan hapus tidak pernah mengeksekusi delete. Ini disengaja.
- `erasureBlockReason` hanya memeriksa hewan milik user; belum mencakup data
  lain di luar rekam medis.
- Belum ada legal hold pada tabel lain selain `medical_records`.
- Belum ada laporan audit khusus legal hold.
- Retensi `audit_logs` dan versi belum dipisahkan dari retensi rekam medis.

## 8. Keputusan Penting

- Permintaan PDP tidak pernah menjadi jalur pintas untuk menghapus rekam medis.
- Permintaan hapus saat retensi/legal hold harus ditolak dengan alasan.
- SLA internal 14 hari dapat dikonfigurasi, tetapi bukan nasihat hukum.
- Retensi finansial lebih panjang dari retensi klinis.
- Arsip bukan delete.
- Legal hold menahan arsip.
- Pelepasan legal hold wajib dua admin berbeda.
- Semua transisi sensitif masuk `audit_logs` append-only.
