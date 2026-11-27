# P0.1 Baseline, Backup, and Characterization Evidence

Tanggal: 26 November 2026  
Repo: `C:/Kelas XI RPL/UJI KOMPETENSI`  
Commit: `a36bd0cd70431f7a879e22f4c2a75dd2db52972c`  
Status: **bukti P0.1 terkumpul; suite gabungan belum selesai; P0.2 belum dimulai.**

## 1. Lingkungan yang diverifikasi

| Komponen | Nilai |
|---|---|
| OS / shell | Windows, Git Bash |
| PHP | 8.3.31 CLI, ZTS Visual C++ 2019 x64 |
| Laravel | 12.62.0 |
| Database utama | MySQL `pet_heal` |
| Database test | `pet_heal_test` (terpisah) |
| `mysqldump` | 8.0.30 |
| Backup location | `C:/Users/RENI KUSWATI/petheal_p0_backup/` |

Database test tidak dijalankan otomatis oleh `php artisan test`. Migrasi test
dijalankan manual dengan `DB_DATABASE=pet_heal_test php artisan migrate --force`
bila diperlukan.

## 2. Schema snapshot

File:

- `docs/p0/schema.sql` — DDL seluruh tabel, tanpa data.
- `docs/p0/row_counts.json` — jumlah baris per tabel.

Hasil:

- 61 tabel basis data;
- total 1.943 baris;
- snapshot dibuat dari `information_schema` dan `SHOW CREATE TABLE`;
- checksum `schema.sql`: `5a5011e50b800cb4a19b98989b2b8ca223c2d6f6e5978753fe6308ad21512bb9`;
- checksum `row_counts.json`: `5455235dc6c469364ed019ce354c6040ef5aea281b27cf176a0d826805b164e0`.

Tabel yang paling relevan untuk roadmap:

| Tabel | Baris |
|---|---:|
| `medical_records` | 14 |
| `bookings` | 49 |
| `prescription_items` | 6 |
| `medical_record_vitals` | 0 |
| `medical_record_revisions` | 0 |
| `medical_record_attachments` | 0 |
| `lab_results` | 0 |
| `referrals` | 0 |
| `treatment_consents` | 0 |
| `pet_allergies` | 0 |
| `medicines` | 76 |
| `medicine_batches` | 75 |
| `medicine_stock_movements` | 87 |
| `audit_logs` | 358 |

Angka ini menjadi dasar estimasi backfill di roadmap §6.8.

## 3. Backup dan uji restore

Backup penuh:

- file: `C:/Users/RENI KUSWATI/petheal_p0_backup/pet_heal_20261126_190716.sql`
- ukuran: 530 KB
- checksum SHA-256: `3fbdcd8327877ee731699eb3a88ec0d46414305744bab563c16383479e2f663f`
- opsi: `--single-transaction --routines --triggers --no-tablespaces`

Uji restore dilakukan ke database sementara `pet_heal_p0_restore`, bukan ke
database produksi:

1. database sementara dibuat;
2. dump diimpor;
3. jumlah tabel diverifikasi 61;
4. jumlah baris exact dihitung per tabel dan dibandingkan;
5. database sementara dihapus.

Hasil restore: **61 tabel, 1.943 baris exact**, sama dengan database sumber.

Catatan: file backup berisi data pribadi dan rekam medis. File ini **tidak**
boleh di-commit, dikirim lewat chat, atau disimpan di folder publik. Lokasinya
di luar repo dan hanya untuk bukti P0.1.

## 4. Baseline test suite sebelum perubahan

Perintah:

```bash
cd PetHeal_Backend
php artisan test
```

Hasil:

- **572 passed**
- **3.454 assertions**
- exit code `0`
- durasi 724,92 detik

Log lengkap: `p0_test_baseline.log` (di root repo, bukan artefak aplikasi).

Tidak ada test yang gagal, di-skip, atau berakhir karena timeout.

## 5. Characterization test baru

File: `PetHeal_Backend/tests/Feature/P01CharacterizationTest.php`

Perintah:

```bash
php artisan test --filter=P01CharacterizationTest
```

Hasil:

- **5 passed**
- **20 assertions**
- durasi 10,24 detik

Yang dikunci:

1. kontrak JSON pemilik sama persis dengan fixture Android lama;
2. pemilik tidak dapat membaca rekam medis hewan pengguna lain;
3. admin dan dokter tidak dapat melewati batas klinik;
4. index, per-hewan, dan per-booking mengembalikan rekam medis yang sama;
5. satu booking tetap satu rekam medis dan `finalize()` mengisi hash.

Test ini **tidak** menguji arsitektur target. Test ini hanya merekam perilaku
yang sudah berjalan agar refactor berikutnya tidak diam-diam mengubah kontrak.

## 6. Fixture kontrak Android

File: `docs/p0/android_fixtures/medical_record_owner_v1.json`

Fixture merekam:

- 36 key level atas pada payload pemilik;
- key objek `doctor`, `booking`, dan `clinic`;
- catatan bahwa field baru wajib additive;
- catatan bahwa STR/SIP masih terkirim pada versi ini dan menjadi temuan audit.

Checksum: `84fa2e5d896e4eee6e07e5793613f4e6ac34eaf895ada7dfcabd8f7fb03306e3`.

Kontrak ini harus tetap lulus sampai P3 mengganti serializer secara eksplisit.
Perubahan yang menghapus atau mengganti nama field lama dilarang.

## 7. Audit tenant

Hasil lengkap: `docs/p0/TENANT_AUDIT.md`.

Ringkasan:

- alur admin, dokter, dan API pemilik yang ada sudah ter-scope;
- tidak ditemukan kebocoran lintas klinik yang dapat direproduksi;
- tujuh risiko dicatat, termasuk status draf yang bisa muncul di API pemilik,
  STR/SIP pada payload pemilik, dan belum adanya policy terpusat.

## 8. Verifikasi suite penuh setelah test baru

Perintah yang dijalankan:

```bash
cd PetHeal_Backend
php artisan test
```

Suite penuh dihentikan atas permintaan pemilik proyek sebelum selesai, karena
setiap putaran memakan waktu sekitar 12 menit. Sampai titik penghentian tidak
ada test yang gagal. Karena suite tidak selesai, angka gabungan 577 **tidak**
diklaim sebagai hasil yang sudah diverifikasi.

Yang sudah diverifikasi secara terpisah:

- baseline 572 test lulus, 3.454 assertions, exit code `0`;
- characterization test 5 test lulus, 20 assertions, exit code `0`.

Konsekuensinya: P0.1 boleh dianggap cukup untuk melanjutkan pekerjaan dokumen,
tetapi belum menjadi bukti suite gabungan yang lengkap. Sebelum rilis produksi,
suite penuh tetap harus dijalankan sekali tanpa dihentikan.

## 9. Yang tidak diubah pada P0.1

- Tidak ada migrasi baru.
- Tidak ada perubahan skema.
- Tidak ada perubahan route.
- Tidak ada perubahan controller, model, service, atau view.
- Tidak ada perubahan kontrak Android.
- Tidak ada flag klinis yang dinyalakan.

Perubahan yang ada hanya:

- `docs/p0/` (bukti);
- `tests/Feature/P01CharacterizationTest.php` (test karakterisasi).

## 10. Gate P0.1

P0.1 dinyatakan lulus hanya bila seluruh poin berikut benar:

- [x] snapshot skema tersimpan;
- [x] row count tersimpan;
- [x] backup penuh dibuat;
- [x] restore diuji dan jumlah baris exact sama;
- [x] audit tenant selesai;
- [x] characterization test lulus;
- [x] fixture Android tersimpan;
- [x] baseline suite lulus;
- [ ] suite penuh dengan characterization test lulus (dihentikan atas permintaan; lihat §8);
- [x] tidak ada perubahan skema/UI.

Setelah kotak terakhir tercentang, P0.2 boleh dimulai. Sebelum itu, tidak ada
tabel encounter, versi, amendment, atau UI baru yang boleh dibuat.
