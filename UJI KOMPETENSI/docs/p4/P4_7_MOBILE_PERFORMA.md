# P4.7 — Efisiensi Daftar Rekam Medis Android (ngrok tetap dipakai)

Status: **kode selesai; pengujian penuh ditunda**  
Tanggal: 26 November 2026

## 1. Penyebab lambat

1. Android memakai ngrok free sebagai base URL. Setiap request menambah
   latency jaringan.
2. `MedicalRecordRepository.getMedicalRecords()` mengambil **semua halaman**
   secara berurutan (maksimal 10 halaman × 20 record) sebelum daftar tampil.
3. Setiap halaman memakai serializer lengkap: booking, klinik, pet, dokter,
   resep, vital, seluruh `vital_series`, lampiran, dan alergi.
4. Layar menampilkan skeleton sampai seluruh halaman selesai.
5. Timeout OkHttp 15/30 detik membuat satu request lambat menahan proses.

## 2. Perubahan

### 2.1 Endpoint daftar ringan

Endpoint baru:

```http
GET /api/medical-records/summary?page=1
```

Hanya mengirim field yang dipakai kartu daftar:

- id, booking_id, pet_id, doctor_id;
- status;
- diagnosis/treatment (tetap mengikuti gate klinis);
- next_visit_date;
- biaya dan status pembayaran tambahan;
- clinic ringkas;
- pet ringkas (id, nama, foto, spesies);
- doctor ringkas (id, nama);
- created_at.

Tidak mengirim:

- booking lengkap;
- lampiran;
- resep;
- vital dan `vital_series`;
- alergi;
- SOAP lengkap.

Endpoint lama `/api/medical-records` **tidak diubah** dan tetap dipakai untuk
detail.

### 2.2 Lazy pagination Android

- Halaman pertama dimuat saat layar dibuka.
- Halaman berikutnya dimuat hanya saat pengguna mendekati akhir daftar.
- Tidak ada lagi loop 10 halaman di awal.
- Footer “Memuat riwayat berikutnya...” muncul saat halaman berikutnya diambil.
- Jika halaman berikutnya gagal, daftar yang sudah tampil tidak dihapus.

State baru:

- `isLoadingMore`;
- `currentPage`;
- `lastPage`;
- `totalRecords`;
- `hasMore`.

### 2.3 Detail tetap lengkap

Saat kartu dibuka, aplikasi memanggil endpoint detail lama
`/api/medical-records/{id}` sehingga SOAP, lampiran, resep, dan vital tetap
lengkap.

## 3. Dampak

- Request awal turun dari maksimal 10 request berurutan menjadi **1 request**.
- Ukuran payload daftar jauh lebih kecil karena relasi berat tidak dikirim.
- Ngrok tetap dipakai; perbaikan tidak bergantung pada penggantian tunnel.
- Dengan data sedikit (1 halaman), aplikasi hanya melakukan satu request.

## 4. Pengujian

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P46MobileMedicalRecordSummaryTest.php
```

Hasil: **1 passed, 16 assertions**.

Cakupan:

- 21 record → halaman pertama 20, halaman kedua 1;
- `per_page = 20`, `last_page = 2`, `total = 21`;
- payload tidak memuat `booking`, `vitals`, `attachments`, `prescription`;
- field kartu tersedia.

Android:

- `compileDebugKotlin`: **BUILD SUCCESSFUL**.

**Catatan jujur:** full suite backend, Android unit test, APK build, dan lint
belum dijalankan ulang setelah perubahan ini karena pengujian penuh diminta
ditunda.

## 5. Batasan

- Masih memakai ngrok free; latency dasar tunnel tetap ada.
- Belum ada pengukuran latency sebelum/sesudah di perangkat nyata.
- Daftar belum memakai cache disk/ETag.
- Ringkasan kartu belum memuat nama klinik dari booking lama yang
  `medical_records.clinic_id`-nya kosong.
- Detail tetap satu request penuh saat membuka record.
- Belum ada skeleton bertahap per kartu.
- Belum ada kompresi Brotli khusus aplikasi; bergantung web server/ngrok.
- Full regression belum dijalankan.
