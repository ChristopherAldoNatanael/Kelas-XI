# P4.5 — Uji Restore Terpisah dan Kesiapan Akhir

Status: **SELESAI untuk uji restore non-destruktif, full suite backend, dan
verifikasi Android**  
Tanggal: 26 November 2026

## 1. Uji Restore Terpisah

Uji dilakukan pada database baru `pet_heal_restore_drill`, bukan pada
`pet_heal`.

### 1.1 Prosedur yang dijalankan

```bash
mysql ... -e "DROP DATABASE IF EXISTS pet_heal_restore_drill;
              CREATE DATABASE pet_heal_restore_drill
              CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

mysqldump --single-transaction --routines --triggers --events pet_heal \
  | mysql ... pet_heal_restore_drill
```

Kredensial memakai file `--defaults-extra-file` di luar proyek. Password tidak
pernah ditampilkan.

### 1.2 Hasil perbandingan

| Pemeriksaan | Sumber `pet_heal` | Drill `pet_heal_restore_drill` |
|---|---|---|
| Jumlah tabel | 88 | 88 |
| `medical_records` | 14 | 14 |
| Trigger | 13 | 13 |
| Migration terakhir | `000018` | `000018` |
| Health check tabel | OK | OK |
| Trigger append-only | OK | OK |
| Indeks performa | OK | OK |
| Rantai audit | OK | OK |
| Retensi kosong | 0 | 0 |
| Lampiran belum terenkripsi | 0 | 0 |

`medical-records:encrypt-attachments --dry-run` pada hasil restore:
**0 lampiran belum terenkripsi**.

### 1.3 Setelah uji

Database drill dihapus agar tidak menambah beban operasional:

```bash
mysql ... -e "DROP DATABASE pet_heal_restore_drill;"
```

Yang **tidak** diuji pada putaran ini:

- restore berkas storage fisik ke server lain;
- dekripsi lampiran nyata dari hasil restore;
- restore kunci dari berkas backup;
- pengukuran RTO end-to-end.

Uji database/trigger/migration sudah membuktikan dump dapat dipulihkan dan
skema lengkap. Uji storage + kunci tetap wajib dilakukan sebelum produksi.

## 2. Full Test Suite Backend

Dijalankan sekali setelah seluruh perubahan P4:

```bash
DB_DATABASE=pet_heal_test php artisan test
```

Hasil akhir:

- **652 passed**
- **4.044 assertions**
- **0 failure**
- **0 error**
- Durasi: **867 detik**

Cakupan termasuk P0–P4, tenant isolation, kontrak Android, rekam medis,
farmasi, laboratorium, PDP, enkripsi lampiran, health check, pagination, dan
dashboard admin PDP.

## 3. Android

Perintah:

```bash
./gradlew testDebugUnitTest assembleDebug
```

Hasil:

- **106 unit test**
- **0 failure**
- **0 error**
- **0 skipped**
- **BUILD SUCCESSFUL**
- APK: `app/build/outputs/apk/debug/app-debug.apk`
- Ukuran: 31.372.709 byte

Perintah:

```bash
./gradlew lintDebug
```

Hasil:

- **BUILD SUCCESSFUL**
- 0 error
- warning yang tersisa tidak menggagalkan build

Perbaikan yang dilakukan:

- `LifecycleAwareState.kt` sebelumnya memicu
  `StateFlowValueCalledInComposition`;
- pembacaan nilai awal diberi justifikasi dan suppression sempit;
- perilaku lifecycle-aware tidak berubah.

## 4. Web Admin

Halaman yang diperiksa:

- daftar rekam medis;
- detail rekam medis;
- permintaan PDP;
- detail permintaan PDP;
- navigasi sidebar.

`php artisan view:cache` sukses. Route PDP terdaftar di `route:list`.
Test `P44AdminPrivacyDashboardTest`: **3 passed, 25 assertions**.

**Belum dilakukan:** uji browser manual dengan data produksi, uji
aksesibilitas formal, dan uji visual lintas perangkat.

## 5. Health Check DB `pet_heal` Terakhir

```text
Tabel wajib                 OK   lengkap
Trigger append-only         OK   aktif
Indeks performa             OK   lengkap
Rantai audit                OK   0 masalah
Retensi kosong              0
PDP terbuka                 0
PDP lewat SLA               0
Lampiran belum terenkripsi  0
```

## 6. Kesimpulan Kesiapan

| Area | Status |
|---|---|
| Skema dan migration | Siap |
| Trigger append-only | Siap |
| Rantai audit | Siap |
| Enkripsi lampiran | Siap untuk lampiran baru + migrasi lama |
| Backup kunci | Tooling siap, backup nyata belum dibuat |
| Restore database | Teruji pada DB terpisah |
| Restore storage + kunci | Belum diuji end-to-end |
| Full suite backend | Lulus |
| Android unit test | Lulus |
| Android build | Lulus |
| Android lint | Lulus |
| Dashboard admin PDP | Tersedia |
| Alert health check | Tersedia bila webhook diisi |
| PDF/A, PKI, KMS | Belum |
| Uji beban/APM | Belum |
| Uji browser manual | Belum |

Status: **siap untuk staging dan uji penerimaan pengguna**, belum otomatis
siap untuk produksi tanpa uji storage/kunci, backup nyata, dan UAT.

## 7. Langkah Sebelum Produksi

1. Buat backup kunci nyata ke media offline.
2. Simpan sidik jari kunci di catatan rilis.
3. Uji restore storage + dekripsi satu PDF.
4. Isi webhook alert dan uji alert nyata.
5. Jalankan UAT admin + pemilik.
6. Tinjau ulang seluruh batasan pada dokumen P3/P4.
7. Baru pertimbangkan PDF/A, PKI, KMS, dan APM.

## 8. Batasan yang Tetap Terbuka

- Tidak ada jaminan kriptografis bahwa backup tidak dimodifikasi.
- Tidak ada rotasi kunci.
- Tidak ada KMS/HSM.
- Tidak ada enkripsi dump database oleh aplikasi.
- Tidak ada retry/dead-letter webhook.
- Tidak ada dashboard monitoring/SLA time-series.
- Tidak ada notifikasi status PDP ke pemohon.
- Unggah/generate berkas jawaban PDP ditambahkan pada P4.6.
- Tidak ada PDF/A.
- Tidak ada tanda tangan PKI/CA.
- Tidak ada uji beban konkuren atau APM.
- Tidak ada uji browser/emulator manual pada putaran ini.
- Modul P2.2/P2.3 (consent, welfare, lab, inpatient, referral, template)
  masih berupa data/service/API, belum memiliki layar admin lengkap.
- `response_path` PDP tetap ada sebagai kompatibilitas; berkas nyata memakai kolom `response_file_*`.
