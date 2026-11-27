# P4.1 — Enkripsi Lampiran At-Rest dan Health Check

Status: **SELESAI (enkripsi lampiran baru + migrasi berkas lama + monitoring read-only)**  
Tanggal: 26 November 2026  
Migration: `2026_11_26_000017_add_attachment_encryption_version.php`

## 1. Tujuan

1. Lampiran rekam medis baru tidak lagi tersimpan sebagai plaintext di disk.
2. Berkas lama tetap dapat dibaca dan dapat dienkripsi tanpa mengubah path.
3. Integritas isi asli tetap dapat dibuktikan lewat SHA-256 plaintext.
4. Tersedia pemeriksaan operasional read-only untuk tabel, trigger, rantai
   audit, retensi, PDP, dan status enkripsi.

## 2. Skema

`medical_record_attachments` mendapat kolom additive:

| Kolom | Arti |
|---|---|
| `encryption_version` | `NULL` = berkas lama plaintext; `1` = AES-256-GCM format PHRM1. |

Tidak ada perubahan pada `path`, `sha256`, atau `mime_type`.

## 3. Format Enkripsi

`MedicalRecordFileEncryptionService`:

```
PHRM1\0 | IV 12 byte | TAG 16 byte | ciphertext
```

- Algoritma: **AES-256-GCM**.
- Header `PHRM1` dipakai sebagai additional authenticated data.
- IV acak 12 byte per berkas.
- Tag autentikasi 16 byte.
- Kunci:
  - `MEDICAL_RECORDS_ATTACHMENT_KEY` bila diisi; atau
  - turunan SHA-256 dari `APP_KEY`.
- Kunci tidak pernah ditulis ke log, database, atau response.

Berkas tanpa header `PHRM1` dianggap **legacy plaintext** dan dikembalikan apa
adanya. Ini mencegah arsip lama terputus saat migrasi.

## 4. Alur Unggah

`MedicalRecordAttachmentService`:

- PDF:
  - isi asli dibaca,
  - SHA-256 dihitung dari plaintext,
  - ciphertext ditulis ke disk,
  - `encryption_version = 1`.
- Gambar:
  - tetap diproses/di-re-encode ke JPEG oleh `ImageService`,
  - hasil JPEG dibaca,
  - ciphertext ditulis menimpa berkas,
  - `size_bytes` dan `sha256` memakai plaintext.
- Bila enkripsi dimatikan:
  - perilaku lama dipertahankan,
  - `encryption_version` tetap `NULL`.

## 5. Alur Unduh

Route signed lama tidak berubah:

`GET /medical-records/{record}/attachments/{attachment}`

Controller:

1. memeriksa keberadaan berkas;
2. menulis audit unduhan;
3. membaca melalui `MedicalRecordAttachmentService::read()`;
4. mendekripsi + memverifikasi tag GCM;
5. mengirim isi plaintext dengan MIME dan nama asli.

Autentikasi GCM gagal → berkas dianggap rusak/berubah, bukan dikirim sembarang.

## 6. Migrasi Berkas Lama

Command:

```bash
php artisan medical-records:encrypt-attachments --dry-run
php artisan medical-records:encrypt-attachments
```

Perilaku:

- hanya memproses `encryption_version` NULL/0;
- tidak mengubah path;
- tidak menghapus berkas;
- mengisi `sha256` plaintext bila kosong;
- baris yang sudah memiliki header PHRM1 hanya diperbarui versinya;
- berkas hilang dilewati dan dilaporkan;
- command mengembalikan failure bila ada baris yang gagal.

Scheduler: setiap Senin 03:15, `withoutOverlapping()`.

## 7. Health Check

Command:

```bash
php artisan medical-records:health-check
php artisan medical-records:health-check --json
```

Pemeriksaan:

- tabel wajib: `medical_records`, `medical_record_versions`,
  `medical_record_revisions`, `medical_record_attachments`, `audit_logs`,
  `privacy_requests`;
- trigger append-only revisi dan audit;
- rantai hash revisi (`AuditChainService::verify()`);
- jumlah `retention_until` kosong;
- jumlah PDP terbuka dan lewat SLA;
- jumlah lampiran belum terenkripsi.

Status critical = tabel + trigger + rantai audit. Bila salah satu gagal,
command mengembalikan exit code non-zero. Retensi/PDP/enkripsi ditampilkan
sebagai informasi operasional, bukan kegagalan critical.

Scheduler: setiap hari 03:00.

`AuditChainService::verify()` ditambahkan agar health check tidak menjalankan
command lain secara bersarang dan dapat dipakai ulang oleh test/monitoring.

## 8. Penerapan DB `pet_heal`

- Migration `000017`: **Ran**.
- `medical-records:encrypt-attachments --dry-run`: **0** lampiran belum
  terenkripsi.
- `medical-records:health-check`:
  - tabel wajib **OK**;
  - trigger append-only **OK**;
  - rantai audit **OK** (0 baris, 0 masalah);
  - retensi kosong **0**;
  - PDP terbuka **0**;
  - PDP lewat SLA **0**;
  - lampiran belum terenkripsi **0**.

## 9. Pengujian

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P41AttachmentEncryptionAndHealthCheckTest.php
```

Hasil:

- **3 passed, 18 assertions**
  - PDF baru tersimpan dengan header PHRM1.
  - Plaintext tidak muncul di berkas tersimpan.
  - SHA-256 sama dengan plaintext.
  - Unduhan signed mengembalikan isi asli.
  - Berkas legacy plaintext tetap terbaca.
  - Command migrasi mengubah berkas lama menjadi terenkripsi.
  - Health check JSON valid dan exit code 0.

Regresi lampiran:

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/MedicalRecordSoapAndAttachmentsTest.php \
  tests/Feature/MedicalRecordGovernanceTest.php \
  tests/Feature/P41AttachmentEncryptionAndHealthCheckTest.php
```

Hasil: **12 passed, 48 assertions**.

`php -l` bersih untuk semua file baru/berubah.

**Catatan jujur:** full test suite tidak dijalankan ulang pada putaran ini.

## 10. Batasan yang Masih Terbuka

- Enkripsi hanya pada isi lampiran; metadata (nama asli, caption, path) belum
  dienkripsi.
- Kunci masih diturunkan dari `APP_KEY` bila env khusus tidak diisi. Rotasi
  kunci dan re-enkripsi massal belum didukung.
- Belum ada KMS/HSM atau envelope encryption.
- `sha256` tidak diverifikasi ulang otomatis setiap unduhan.
- Berkas yang sudah terenkripsi tidak dapat dipulihkan bila kunci hilang.
- Backup database dan storage belum diuji restore penuh pada putaran ini.
- Health check tidak mengirim email/Slack/Telegram; alert eksternal belum ada.
- Health check hanya MySQL (information_schema).
- Trigger audit baru memeriksa UPDATE/DELETE; belum ada prosedur retensi audit.
- Belum ada dashboard monitoring.
- Enkripsi tidak berlaku untuk foto hewan, foto profil, atau lampiran petshop.
- Full suite belum dijalankan ulang.

## 11. Keputusan Penting

- AES-256-GCM, bukan enkripsi buatan sendiri.
- Header PHRM1 memisahkan berkas baru dan legacy tanpa migrasi mendadak.
- Hash integritas tetap hash plaintext.
- Kunci tidak pernah masuk database/log/response.
- Migrasi berkas bersifat idempotent dan tidak menghapus apa pun.
- Health check read-only.
- Critical health = skema + trigger + rantai audit.
