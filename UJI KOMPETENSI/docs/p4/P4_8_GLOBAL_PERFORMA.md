# P4.8 — Efisiensi Global Android + Backend (ngrok tetap)

Status: **kode selesai; full regression belum dijalankan**  
Tanggal: 26 November 2026

## 1. Tujuan

Mengurangi rasa lambat di aplikasi Android tanpa mengganti ngrok:

1. Jangan menunggu banyak request berurutan di awal layar.
2. Jangan mengirim relasi berat untuk daftar.
3. Kurangi ukuran JSON di semua endpoint GET.
4. Terapkan lazy pagination pada daftar besar.
5. Batasi pengambilan halaman pada layar yang hanya butuh data terbaru.

## 2. Perubahan Backend

### 2.1 Endpoint daftar rekam medis ringan

```http
GET /api/medical-records/summary?page=1
```

Hanya memuat field kartu:

- id, booking_id, pet_id, doctor_id;
- status;
- diagnosis/treatment dengan gate klinis;
- tanggal kontrol;
- biaya dan status pembayaran tambahan;
- klinik ringkas;
- hewan ringkas;
- dokter ringkas;
- created_at.

Tidak memuat:

- booking lengkap;
- lampiran;
- resep;
- vital / vital_series;
- alergi;
- SOAP.

Endpoint lama tetap utuh untuk detail.

### 2.2 Kompresi JSON global

Middleware:

`App\Http\Middleware\CompressJsonResponse`

- hanya untuk `GET /api/*`;
- hanya bila klien mengirim `Accept-Encoding: gzip`;
- hanya JSON, status 200, minimal 1 KB;
- tidak menimpa response yang sudah punya `Content-Encoding`;
- menambah `Vary: Accept-Encoding`;
- OkHttp Android otomatis men-decompress.

Dampak: payload daftar/dashboard mengecil di semua halaman API, bukan hanya
rekam medis.

## 3. Perubahan Android

### 3.1 Rekam medis

- memakai `/api/medical-records/summary`;
- halaman pertama langsung tampil;
- halaman berikutnya dimuat saat scroll mendekati bawah;
- footer “Memuat riwayat berikutnya...”;
- daftar lama tidak dihapus jika halaman berikutnya gagal.

### 3.2 Booking

- `BookingRepository.getBookingsPage(page)` baru;
- daftar booking memakai halaman pertama dulu;
- halaman berikutnya saat scroll;
- footer “Memuat booking berikutnya...”;
- filter dan sort tetap diterapkan pada data yang sudah terkumpul.

`getBookings(maxPages = 2)` dipakai Home dan Doctors agar tidak lagi mengunduh
seluruh halaman hanya untuk mencari booking yang bisa dinilai.

### 3.3 Cache HTTP global per akun

`AppModule.provideOkHttpClient` sekarang:

- cache disk 50 MB;
- GET `/api/*` disimpan 30 detik untuk rekam medis, booking, dan PDP;
- GET `/api/*` lain disimpan 120 detik;
- response diberi `Vary: Authorization` agar cache terpisah per token/akun;
- semua POST/PUT/PATCH/DELETE yang sukses mengosongkan cache;
- saat offline, GET API boleh memakai cache sampai 7 hari.

Ini membuat tab yang baru dibuka ulang terasa instan tanpa menunggu ngrok,
tetapi tidak menampilkan data akun lain.

### 3.4 Detail tetap lengkap

Saat kartu dibuka, aplikasi tetap memanggil endpoint detail penuh.

## 4. Alur yang Berlaku Sekarang

```text
Buka layar
  -> 1 request halaman pertama (ringan + gzip)
  -> tampilkan daftar
  -> scroll mendekati bawah
  -> 1 request halaman berikutnya
  -> gabungkan tanpa duplikat
```

Bukan lagi:

```text
Buka layar
  -> request halaman 1
  -> tunggu
  -> request halaman 2
  -> tunggu
  -> ... sampai 10 halaman
  -> baru tampil
```

## 5. File Penting

Backend:

- `app/Http/Controllers/Api/MedicalRecordController.php` — `summary()`
- `app/Http/Middleware/CompressJsonResponse.php`
- `bootstrap/app.php` — registrasi middleware API
- `routes/api.php` — route summary
- `tests/Feature/P46MobileMedicalRecordSummaryTest.php`

Android:

- `data/remote/ApiService.kt`
- `data/repository/MedicalRecordRepository.kt`
- `data/repository/BookingRepository.kt`
- `ui/screens/medicalrecord/MedicalRecordsViewModel.kt`
- `ui/screens/medicalrecord/MedicalRecordsScreen.kt`
- `ui/screens/booking/BookingViewModel.kt`
- `ui/screens/booking/BookingsScreen.kt`
- `ui/screens/home/HomeViewModel.kt`
- `ui/screens/doctor/DoctorsViewModel.kt`
- `di/AppModule.kt` — cache HTTP per akun + eviction setelah write

## 6. Pengujian yang Sudah Dijalankan

```bash
DB_DATABASE=pet_heal_test php artisan test \
  tests/Feature/P46MobileMedicalRecordSummaryTest.php
```

Hasil: **1 passed, 20 assertions**.

Cakupan:

- 21 record → 20 + 1;
- pagination benar;
- payload tidak memuat relasi berat;
- response gzip dapat didekode dan berisi 20 item.

Android:

- `compileDebugKotlin` — **BUILD SUCCESSFUL** setelah perubahan summary,
  lazy pagination rekam medis, dan lazy pagination booking.

**Belum dijalankan setelah perubahan terakhir:**

- full test suite backend;
- Android `testDebugUnitTest`;
- `assembleDebug`;
- `lintDebug`;
- pengukuran latency di perangkat.

## 7. Batasan

- Ngrok free tetap menambah latency dasar.
- Cache masih berupa HTTP cache OkHttp, bukan model cache Room per layar.
- Belum ada stale-while-revalidate eksplisit; data lebih lama dari TTL akan
  menunggu network.
- Belum ada ETag/conditional request.
- Belum ada prefetch halaman berikutnya di latar belakang.
- Daftar invoice center belum punya lazy pagination sendiri.
- Notifikasi, pet shop, dan layar lain belum diaudit satu per satu.
- Kompresi bergantung pada klien mengirim `Accept-Encoding: gzip`.
- Ringkasan rekam medis belum menampilkan klinik dari booking lama yang
  `clinic_id` rekam medisnya kosong.
- Full regression belum dijalankan.

## 8. Catatan untuk Agent Berikutnya

1. Jangan mengubah kontrak `/api/medical-records`; perubahan daftar harus
   lewat endpoint summary.
2. Jangan menghapus pagination server; daftar memakai halaman.
3. Jangan membaca semua halaman di repository untuk layar daftar.
4. Jika menambah daftar baru, ikuti pola:
   - endpoint ringan;
   - lazy pagination;
   - footer loading;
   - jangan hapus data lama saat halaman berikutnya gagal.
5. Uji kompresi dengan header `Accept-Encoding: gzip`; Android OkHttp
   men-decompress otomatis.
6. Jalankan full regression sebelum menyatakan selesai.
