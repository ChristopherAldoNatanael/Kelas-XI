# P4.3 — Hardening Performa dan Pagination

Status: **SELESAI untuk indeks daftar rekam medis, verifikasi indeks, dan uji pagination**  
Tanggal: 26 November 2026  
Migration: `2026_11_26_000018_add_medical_record_performance_indexes.php`

## 1. Masalah yang Ditangani

Daftar rekam medis admin dan dokter memakai filter yang berulang:

- `clinic_id`;
- `doctor_id`;
- `status`;
- `extra_payment_status`;
- `created_at` untuk urutan dan rentang tanggal.

Sebelum P4.3, `medical_records` hanya memiliki indeks tunggal `clinic_id`,
`doctor_id`, dan beberapa indeks gabungan lain. Query dengan filter klinik +
urutan tanggal masih membutuhkan filesort/pemindaian tambahan.

Pagination API dan halaman admin/dokter sebenarnya sudah ada sebelum P4.3:

- API pemilik: 20 per halaman;
- admin: 20 per halaman;
- dokter: 15 per halaman.

P4.3 memastikan pagination itu tetap bekerja dan indeks pendukungnya tersedia.

## 2. Indeks Baru

| Nama indeks | Kolom | Dipakai untuk |
|---|---|---|
| `idx_mr_clinic_created` | `clinic_id`, `created_at` | daftar per klinik, urutan tanggal |
| `idx_mr_doctor_created` | `doctor_id`, `created_at` | daftar dokter, urutan tanggal |
| `idx_mr_clinic_status_created` | `clinic_id`, `status`, `created_at` | filter status + klinik |
| `idx_mr_clinic_payment_created` | `clinic_id`, `extra_payment_status`, `created_at` | ringkasan pembayaran tambahan |

Nama dibuat pendek agar berada di bawah batas 64 karakter MySQL.

Indeks lama tetap dipertahankan. Tidak ada kolom yang dihapus atau diubah.

## 3. Bukti EXPLAIN

Query:

```sql
EXPLAIN SELECT id
FROM medical_records
WHERE clinic_id = ?
ORDER BY created_at DESC
LIMIT 20;
```

Hasil pada DB `pet_heal`:

- `type`: `ref`
- `key`: `idx_mr_clinic_created`
- `Extra`: `Backward index scan; Using index`
- `rows`: 11 pada data uji saat ini

Artinya MySQL memakai indeks baru dan tidak memerlukan filesort untuk urutan
tanggal.

## 4. Verifikasi Health Check

`medical-records:health-check` sekarang juga memeriksa indeks performa:

```bash
php artisan medical-records:health-check
```

Keluaran baru:

```text
| Indeks performa | OK | lengkap |
```

Indeks yang hilang membuat status critical gagal dan dapat memicu webhook alert
P4.2. Daftar indeks wajib ada di:

`MedicalRecordHealthCheck::REQUIRED_INDEXES`

## 5. Pagination yang Diuji

Test `P43PerformancePaginationTest` membuat 21 rekam medis lalu memanggil:

```http
GET /api/medical-records
GET /api/medical-records?page=2
```

Hasil yang diverifikasi:

- `success = true`;
- `per_page = 20`;
- `current_page = 1` pada halaman pertama;
- `last_page = 2`;
- `total = 21`;
- halaman pertama berisi 20 item;
- halaman kedua berisi 1 item;
- `current_page = 2` pada halaman kedua.

Kontrak JSON lama tidak berubah. Tidak ada field baru di objek rekam medis.

## 6. Hasil Pengujian

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P43PerformancePaginationTest.php
```

Hasil: **2 passed, 15 assertions**.

Pemeriksaan indeks juga tercakup di test health check P4.1.

`php -l` bersih untuk migration, command, dan test baru.

**Catatan jujur:** full test suite tidak dijalankan ulang. Pengukuran beban
nyata (p95 latency, jumlah query per request, APM) belum dilakukan.

## 7. Batasan

- Indeks hanya untuk `medical_records`; tabel lain belum dianalisis pada P4.3.
- Belum ada pengukuran latency sebelum/sesudah dengan data produksi.
- Belum ada query logging lambat otomatis.
- Belum ada APM/tracing.
- Ringkasan admin masih menjalankan tiga query agregat per halaman.
- Filter pencarian nama hewan memakai `whereHas` + `LIKE`; indeks biasa tidak
  banyak membantu untuk pola `%kata%`.
- Belum ada pagination server-side untuk audit log atau daftar lampiran.
- Belum ada cursor pagination.
- Belum ada arsip/partisi tabel untuk data bertahun-tahun.
- Belum ada uji beban konkuren.
- Indeks tambahan menambah biaya tulis; belum diukur pada beban tulis tinggi.
- Full suite belum dijalankan ulang.

## 8. Keputusan Penting

- Indeks mengikuti pola filter nyata, bukan menambah indeks tanpa arah.
- Nama indeks pendek dan eksplisit.
- Tidak mengubah kontrak API atau struktur respons.
- Indeks wajib masuk health check agar tidak hilang diam-diam saat deploy.
- Pagination tetap 20/20/15 agar perilaku UI tidak berubah.
- Tidak ada perubahan data atau backfill pada P4.3.
