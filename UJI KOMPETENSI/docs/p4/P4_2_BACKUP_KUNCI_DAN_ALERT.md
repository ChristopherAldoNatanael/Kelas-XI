# P4.2 — Backup Kunci, Runbook Restore, dan Alert Health Check

Status: **SELESAI untuk tooling kunci, alert eksternal, dan runbook**  
Tanggal: 26 November 2026  
Tidak ada proses restore destruktif yang dijalankan pada putaran ini.

## 1. Tujuan

1. Kunci enkripsi lampiran dapat diverifikasi tanpa membocorkan nilainya.
2. Kunci dapat dibackup ke media offline dengan proteksi path dan permission.
3. Kegagalan health check dapat dikirim ke webhook eksternal tanpa data pasien.
4. Tersedia runbook restore database, storage, dan kunci yang dapat diuji.

## 2. Command Baru

### 2.1 Info kunci

```bash
php artisan medical-records:encryption-key-info
php artisan medical-records:encryption-key-info --json
```

Menampilkan:

- sumber kunci: `MEDICAL_RECORDS_ATTACHMENT_KEY` atau `APP_KEY`;
- sidik jari 16 karakter (SHA-256 dari kunci turunan);
- status enkripsi;
- versi format.

Tidak pernah menampilkan material kunci.

### 2.2 Backup kunci offline

```bash
php artisan medical-records:backup-encryption-key \
  --output=D:/backup-offline/petheal-attachment-key-2026-11-26.json \
  --confirm=BACKUP
```

Proteksi:

- `--output` wajib;
- `--confirm=BACKUP` wajib;
- menolak path di dalam folder proyek;
- menolak menimpa tanpa `--force`;
- menulis permission `0600` bila didukung OS;
- hanya mencetak path dan sidik jari, bukan kunci.

Isi berkas:

```json
{
  "type": "petheal-medical-record-attachment-key",
  "version": 1,
  "source": "APP_KEY",
  "fingerprint": "16 karakter",
  "key_material_base64": "...",
  "created_at": "ISO-8601"
}
```

Berkas ini **rahasia**. Jangan commit, unggah, atau simpan di folder proyek.

### 2.3 Health check + alert

```bash
php artisan medical-records:health-check
php artisan medical-records:health-check --json
php artisan medical-records:health-check --no-alert
```

Bila status critical gagal dan webhook dikonfigurasi, command mengirim POST:

```json
{
  "event": "medical_records.health_check_failed",
  "ok": false,
  "generated_at": "ISO-8601",
  "checks": {
    "tables": {"ok": false, "missing": ["..."]},
    "triggers": {"ok": false, "missing": ["..."]},
    "audit_chain": {"ok": false, "checked": 0, "issues": []},
    "retention_missing_until": 0,
    "privacy_overdue": 0,
    "attachments_unencrypted": 0
  }
}
```

Tidak ada diagnosis, nama pasien, isi rekam medis, atau token di payload.

Konfigurasi:

```env
MEDICAL_RECORDS_HEALTH_ALERT_ENABLED=true
MEDICAL_RECORDS_HEALTH_ALERT_WEBHOOK=https://contoh.internal/hooks/petheal
MEDICAL_RECORDS_HEALTH_ALERT_TOKEN=
MEDICAL_RECORDS_HEALTH_ALERT_TIMEOUT=5
```

Token opsional dikirim sebagai `Authorization: Bearer ...` dan tidak dicatat ke
log. Kegagalan webhook hanya menghasilkan `Log::warning` tanpa URL/token.

## 3. Runbook Backup

### 3.1 Urutan backup yang benar

1. **Kunci enkripsi** — backup lebih dulu.
2. **Database** — dump konsisten.
3. **Storage lampiran** — salin setelah dump atau gunakan snapshot.
4. **Verifikasi** — cek sidik jari, jumlah tabel, jumlah lampiran, dan hash
   contoh berkas.
5. **Simpan** — media terenkripsi/offline, terpisah dari server.

Kunci lebih dulu karena ciphertext tanpa kunci tidak dapat dipulihkan.

### 3.2 Contoh backup database

```bash
mysqldump \
  --single-transaction \
  --routines \
  --triggers \
  --events \
  --defaults-extra-file=/path/aman/.my.cnf \
  pet_heal | gzip > petheal-$(date +%F-%H%M).sql.gz
```

Catatan:

- `--triggers` wajib; trigger append-only adalah bagian dari kontrol legal.
- `--single-transaction` untuk InnoDB.
- Jangan menaruh password di command line.
- Simpan dump di media terenkripsi.

### 3.3 Contoh backup storage

```bash
tar -czf petheal-storage-$(date +%F-%H%M).tar.gz \
  -C /path/ke/petheal/storage/app \
  medical-records
```

Yang wajib masuk:

- `storage/app/medical-records` (lampiran);
- `storage/app/private` bila dipakai aplikasi;
- berkas export medis bila ada.

### 3.4 Verifikasi backup

```bash
php artisan medical-records:encryption-key-info --json
gzip -t petheal-YYYY-MM-DD-HHMM.sql.gz
tar -tzf petheal-storage-YYYY-MM-DD-HHMM.tar.gz | head
```

Simpan sidik jari kunci bersama catatan backup. Sidik jari harus sama dengan
environment produksi saat backup dibuat.

## 4. Runbook Restore

### 4.1 Prasyarat

- Aplikasi dalam maintenance mode.
- Backup database, storage, dan kunci tersedia.
- Kunci backup sudah diverifikasi sidik jarinya.
- Restore dilakukan ke environment terpisah lebih dulu bila memungkinkan.
- Tidak menimpa database produksi tanpa persetujuan tertulis.

### 4.2 Restore database

```bash
php artisan down
mysql --defaults-extra-file=/path/aman/.my.cnf pet_heal_restore < petheal-YYYY-MM-DD-HHMM.sql
```

Untuk dump terkompresi:

```bash
gunzip -c petheal-YYYY-MM-DD-HHMM.sql.gz | \
  mysql --defaults-extra-file=/path/aman/.my.cnf pet_heal_restore
```

### 4.3 Restore storage

```bash
tar -xzf petheal-storage-YYYY-MM-DD-HHMM.tar.gz -C /path/ke/petheal/storage/app
```

Pastikan permission folder sesuai user aplikasi.

### 4.4 Restore kunci

Isi `MEDICAL_RECORDS_ATTACHMENT_KEY` dari backup offline, atau pulihkan
`APP_KEY` jika kunci diturunkan dari `APP_KEY`.

```bash
php artisan config:clear
php artisan medical-records:encryption-key-info --json
```

Sidik jari **harus sama** dengan sidik jari saat backup dibuat. Jika berbeda,
lampiran tidak akan dapat didekripsi.

### 4.5 Verifikasi pasca-restore

Wajib lulus sebelum aplikasi dibuka:

```bash
php artisan migrate:status
php artisan medical-records:health-check
php artisan medical-records:encrypt-attachments --dry-run
```

Yang diperiksa manual:

1. Jumlah baris `medical_records`, `medical_record_versions`,
   `medical_record_revisions`, `medical_record_attachments`, `audit_logs`,
   `privacy_requests` sesuai backup.
2. Trigger append-only ada.
3. Rantai audit valid.
4. Satu lampiran PDF dapat diunduh dan isinya benar.
5. Satu lampiran lama non-PHRM1 masih terbaca.
6. Status rekam medis final tidak berubah.
7. `retention_until` terisi.
8. Tidak ada pesan error dekripsi di log.

### 4.6 Setelah verifikasi

```bash
php artisan up
```

Catat:

- waktu mulai dan selesai;
- RPO aktual (selisih backup terakhir);
- RTO aktual;
- jumlah berkas dan baris;
- sidik jari kunci;
- penyimpangan yang ditemukan.

## 5. Target Operasional

| Properti | Target usulan |
|---|---|
| RPO database | ≤ 24 jam |
| RPO storage | ≤ 24 jam |
| RPO kunci | setiap perubahan kunci |
| RTO restore teruji | ≤ 4 jam |
| Retensi backup harian | 30 hari |
| Retensi backup bulanan | 12 bulan |
| Uji restore | minimal 1 kali per 6 bulan |

Angka ini usulan operasional, bukan kewajiban hukum spesifik.

## 6. Uji Restore yang Wajib Dilakukan Nanti

Uji berikut **belum dijalankan** dan harus dikerjakan di environment terpisah:

1. Restore database kosong dari dump.
2. Jalankan `migrate:status` dan `migrate` bila perlu.
3. Restore storage.
4. Pasang kunci backup.
5. Jalankan `medical-records:health-check`.
6. Unduh satu lampiran terenkripsi.
7. Bandingkan hash plaintext dengan `sha256` di database.
8. Jalankan `medical-records:encrypt-attachments --dry-run` → harus 0.
9. Catat RPO/RTO aktual.

Tanpa uji ini, backup belum dapat disebut terverifikasi.

## 7. Pengujian Otomatis pada Putaran Ini

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P41AttachmentEncryptionAndHealthCheckTest.php \
  tests/Feature/P42KeyBackupAndAlertTest.php
```

Hasil: **7 passed, 37 assertions**.

Cakupan:

- info kunci hanya menampilkan sidik jari/sumber;
- backup menolak tanpa konfirmasi;
- backup menolak path di dalam proyek;
- backup menulis berkas dengan sidik jari yang benar;
- health check gagal mengirim webhook;
- webhook memakai Bearer token;
- payload webhook tidak memuat data pasien.

**Catatan jujur:** full test suite tidak dijalankan ulang. Restore penuh tidak
dijalankan karena bersifat destruktif dan membutuhkan environment terpisah.

## 8. Batasan

- Belum ada penjadwalan backup otomatis dari aplikasi.
- Belum ada enkripsi dump database oleh aplikasi.
- Belum ada verifikasi checksum berkas backup secara otomatis.
- Belum ada rotasi kunci dan re-enkripsi massal.
- Belum ada KMS/HSM.
- Sidik jari bukan bukti kriptografis bahwa berkas backup tidak dimodifikasi.
- Webhook alert belum punya retry/backoff atau dead-letter queue.
- Alert belum membedakan critical vs warning.
- Belum ada dashboard alert.
- `Log::warning` hanya lokal; belum ada integrasi Sentry/OpenTelemetry.
- Backup kunci tetap bergantung pada keamanan media offline.
- Jika `APP_KEY` berubah tanpa backup kunci lama, lampiran lama tidak dapat
  dipulihkan.
- Runbook ini belum diuji pada server produksi.

## 9. Keputusan Penting

- Kunci tidak pernah dicetak ke console/log.
- Backup kunci wajib eksplisit (`--confirm=BACKUP`) dan di luar proyek.
- Data pasien tidak pernah masuk payload alert.
- Health check tetap read-only.
- Restore harus diverifikasi dengan health check dan unduhan nyata.
- Backup kunci dilakukan sebelum database/storage.
