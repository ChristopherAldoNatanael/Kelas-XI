# Audit Menyeluruh Backend PetHeal

**Tanggal:** 2026-11-21
**Ruang lingkup:** seluruh `PetHeal_Backend` — API pasien, web admin, portal dokter, area superadmin, Blade (116 view), middleware, service, model, migrasi, konfigurasi, operasional, dan integrasi ke `PetHeal_Android`.
**Skala:** 306 route (99 API, 207 web/console/health) · 16.364 baris controller (45 file) · 34 service · 7 middleware · 54 tabel · 473 test.

---

## 0. Ringkasan eksekutif

Backend ini **bukan proyek setengah jadi**. Yang terbukti saat audit: otorisasi tenant fail-closed, uang selalu dihitung server, webhook Midtrans terverifikasi, stok obat & stok produk dikunci dengan `lockForUpdate` + rollback, dan seluruh endpoint API punya route + envelope error konsisten. `php artisan test` → **473 lulus / 2.543 assertion / 0 gagal**; `php -l` semua file → 0 error; scan XSS/CSRF/SQLi → 0 celah.

Namun audit akar menemukan **satu regresi nyata berseveritas tinggi** (sisa refactor multi-klinik), **satu seri celah konsistensi alur** antara jalur pasien vs admin vs Pet Shop, dan **sisa fitur/dead code** yang membuat audit berikutnya menyesatkan.

| Domain | Skor | Catatan singkat |
|---|---|---|
| Auth & sesi (admin/dokter/pasien) | 9/10 | OTP berlapis, throttle terpisah, session regenerate, tidak ada eskalasi peran |
| Otorisasi & isolasi tenant | 8/10 | Semua query admin/dokter ber-`clinic_id`; tetapi daftar pasien di admin masih pakai aturan lama (lihat F-01) |
| Booking & antrian | 7/10 | Lock benar, tapi antrian tidak mengecualikan booking batal, dan cancel pasien tanpa lock |
| Rekam medis & resep | 9/10 | SOAP, stok obat dikembalikan saat koreksi resep, `recalculatePaymentState` |
| Pembayaran & refund | 9/10 | Nominal tidak dipercaya klien, signature per-transaksi, refund dibatasi sisa dana |
| Inventaris obat | 9/10 | Mutasi/batch/karantina/dibalik, penjagaan stok negatif menyeluruh |
| Pet Shop | 9/10 | Stok reserve/commit/release, pickup pass terikat order |
| Notifikasi (FCM) | 6/10 | Dikirim sinkron tanpa timeout; atribusi klinik hilang untuk pasien global |
| Dashboard & laporan | 6/10 | Metrik pasien salah (F-01), pendapatan klinik ≠ pendapatan Pet Shop (F-12) |
| Kualitas kode | 8/10 | 0 TODO/dd/var_dump, 0 mass-assignment mentah; sisa dead code (F-15..F-17) |
| Integrasi Android | 8/10 | 0 endpoint tanpa route; paging & label status belum dipakai klien |
| Operasional | 7/10 | Scheduler tidak pernah dijalankan di lokal; queue worker ada tapi belum terpakai |

**Jumlah temuan: 20** (2 Tinggi, 9 Sedang, 9 Rendah).

---

## 1. Metode audit

| Pemeriksaan | Alat / cara | Hasil |
|---|---|---|
| Sintaks | `php -l` seluruh `app/ routes/ config/ database/` | 0 error |
| Route vs method controller | `route:list --json` dibandingkan isi file (skrip) | 306/306 sah, 0 method mengarah ke fungsi tak ada |
| Method public tanpa route | skrip yang sama | 0 (tidak ada endpoint mati di controller) |
| Test | `php artisan test` | 473 lulus / 0 gagal / 0 skip |
| XSS Blade | cari `{!! !!}` di 116 view | 0 |
| CSRF | parser `<form>` non-GET tanpa `@csrf` | 0 |
| SQL injection | `whereRaw/orderByRaw/selectRaw/DB::raw` + `orderBy($input)` | 0 vektor; `orderBy` di `Admin/PaymentController.php:231-244` memakai whitelist |
| RCE / path traversal | `exec/shell_exec/eval/unserialize`, `Http::` dari input, `Storage::delete($input)` | 0 |
| Middleware | cek setiap route `admin/*` vs `doctor/*` | Semua terlindungi; hanya `login/register/activate` publik (memang seharusnya) |
| Foreign key | `information_schema` | 39 tabel ber-FK dari 54 (15 sisanya tabel global: cache/jobs/otp/kategori) |
| Index | `SHOW INDEX` tabel panas | Lengkap, termasuk index komposit booking & pembayaran |
| Rahasia | kolom `server_key/secret`, `Log::*` | Server key Midtrans terenkripsi (`PaymentCredentialVersion.php:50`) |
| Cakupan test | peta URL di 46 file test | Berat di admin/dokter, tipis di API (F-20) |

---

## 2. Peta alur (dari akar)

### 2.1 Empat pintu masuk yang berbeda nasib data

| Alur | Penulis data | `users.clinic_id` hasilnya | Muncul di Admin → Users? |
|---|---|---|---|
| Daftar email + OTP (`/auth/register/*`) | `Api/AuthController.php:246` | `null` | ❌ (F-01) |
| Google / Firebase (`/auth/register`) | `Api/AuthController.php:508` | `null` | ❌ (F-01) |
| Dibuat admin (`admin/users`) | `Admin/UserController.php:117` | `currentClinicId()` | ✅ |
| Dibuat superadmin approve join-request | `Admin/AdminAuthController.php:626+` | `clinic_id` klinik baru | ✅ |

Tiga dari empat alur pasien menghasilkan akun **global** (klien lintas klinik, sesuai `ApplyAuthenticate` PHASE 1), tetapi halaman admin masih memfilter pasien dengan aturan lama → lihat **F-01**.

### 2.2 Alur klinik (admin web)

1. Pendaftaran klinik: `admin/register` (OTP) → `clinic_join_requests` (`type=new_clinic`) → superadmin approve → `Clinic::create` + akun `clinic_admin` dalam satu transaksi ber-`lockForUpdate` (`AdminAuthController.php:597-640`).
2. Multi-klinik: superadmin memilih konteks lewat `admin/switch-clinic` (session `current_clinic_id`), helper `currentClinicId()` (`app/helpers.php:39`) dipakai 165 route admin.
3. Booking: pasien membuat → admin `confirm` → pasien `check-in` (`/bookings/{id}/check-in`) → antrian (`QueueService`) → dokter `start/done` → admin `complete` → pembayaran (`admin/payments/{id}/confirm` atau webhook).
4. Rekam medis: dokter/admin membuat (`MedicalRecord`) → resep memotong stok obat (`MedicineStockService::consumePrescription`) → biaya tambahan ditagih (`/medical-records/{id}/pay`).

### 2.3 Alur Pet Shop

Katalog publik (read-only) → order (`ProductOrderService::placeOrder`, stok direservasi dalam transaksi) → bayar online (snap token) atau bayar di klinik → admin `accepted → ready_for_pickup → completed` (`ProductOrderService::transition` + tabel transisi) → pickup pass (QR + kode manual) → struk (server) / PDF (perangkat Android).

Aturan yang **hanya ada** di jalur ini dan tidak di jalur booking: `completed` ditolak bila belum lunas (`ProductOrderService.php:213-218`). Lihat **F-05**.

### 2.4 Area superadmin

Route bertanda `EnsureSuperAdmin`: CRUD klinik, approve/reject join-request, switch-clinic, kategori layanan, merchant Pet Shop, kategori produk, template notifikasi, `payment-settings/production-approval`. Semua memeriksa `auth()->user()->role === 'super_admin'` literal (`EnsureSuperAdmin.php:12-16`) — tidak bisa ditembus dengan ubah session.

---

## 3. TEMUAN — Tinggi

### F-01 · Pasien yang mendaftar sendiri tidak terlihat di Admin → Users & statistik dashboard
**Severity:** Tinggi (regresi refactor multi-klinik) · **Domain:** admin web, dashboard

**Bukti**
- Registrasi pasien menulis `clinic_id => null`: `app/Http/Controllers/Api/AuthController.php:246` (OTP), `:436` (ganti email), `:508` (Firebase).
- Admin Users memfilter pasien dengan kolom yang sekarang selalu `null` untuk pasien baru:
  `app/Http/Controllers/Admin/UserController.php:24`
  ```php
  $base = User::query()->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId));
  ```
  Kartu statistik (`total`, `patients`, `new_this_month`) dan daftar (`paginate(20)`) memakai `$base` yang sama.
- Dashboard klinik menghitung pasien dengan cara yang sama:
  `app/Http/Controllers/Admin/DashboardController.php:55` dan `:488`.

**Dampak**
Pasien yang mendaftar dari aplikasi **tidak muncul** di halaman Users, tidak terhitung di "Total Pasien"/"Pasien Baru Bulan Ini", dan hewannya tidak ditemukan oleh pencarian global admin. Yang masih tampil hanya akun lama (di DB contoh: 6 dari 7 pasien masih punya `clinic_id`, sehingga bug tersamarkan saat demo).

**Reproduksi (dibuktikan di DB, transaksi di-rollback)**
Skenario: buat pasien `clinic_id = NULL` + 1 hewan + 1 booking di "Klinik PetHeal Pusat", lalu jalankan query persis dari kode admin.

```
User baru clinic_id: NULL
Tampil di Admin->Users?         TIDAK
Dihitung sbg total pasien?      TIDAK
Ketemu di pencarian admin?      TIDAK
Jika berbasis booking klinik:   YA (benar)
```

Jadi bug ini bukan dugaan: pasien baru benar-benar tidak terlihat, dan definisi berbasis transaksi (`bookings.clinic_id`) langsung memperbaikinya.

**Perbaikan yang disarankan**
Ganti definisi "pasien klinik" dari *kepemilikan akun* menjadi *relasi transaksi*: `Pet`/`User` yang punya `bookings` di klinik itu, atau `medical_records` di klinik itu.
```php
// Admin/UserController.php
$patientIds = Booking::where('clinic_id', $clinicId)->distinct()->pluck('user_id');
$base = User::query()->when($clinicId, fn ($q) =>
    $q->where(fn ($w) => $w->whereIn('id', $patientIds)->orWhere('clinic_id', $clinicId)));
```
Lakukan hal yang sama di `DashboardController:55,488`, lalu tambahkan test regresi: buat user `clinic_id=null` + booking di klinik A → pastikan muncul di daftar & statistik klinik A.

### F-02 · Dokumen aturan tenancy masih menyatakan "1 akun pasien = 1 klinik"
**Severity:** Tinggi (sumber F-01 dan filter serupa di masa depan) · **Domain:** dokumentasi

`docs/TENANCY_RULES.md:8` masih berbunyi:
> `user (pasien) | user | WAJIB 1 id | ... 1 akun = 1 klinik.`

Padahal kode sekarang: `ApiAuthenticate.php` PHASE 1 mengizinkan `clinic_id = NULL` untuk pasien, registrasi menulis `null`, dan pasien boleh booking lintas klinik (`BookingController.php:150-172`). Dokumen yang bertentangan dengan kode adalah penyebab langsung F-01: siapa pun yang menulis kode baru akan menyalin aturan lama.

**Perbaikan:** perbarui tabel di `TENANCY_RULES.md` (pasien = akun global, `clinic_id` NULL, konteks klinik berasal dari transaksi) dan tambahkan catatan "jangan pakai `users.clinic_id` untuk menentukan pasien klinik".

---

## 4. TEMUAN — Sedang

### F-03 · Antrian tetap memanggil pasien yang booking-nya sudah dibatalkan
**Severity:** Sedang · **Domain:** antrian (admin & dokter)

**Bukti**
- `QueueService::callNext()` mengambil `queue_status = waiting` tanpa melihat status booking:
  `app/Services/QueueService.php:302-306`
- `QueueService::board()` juga tidak menyaring booking batal, sehingga pasien batal tetap muncul di kolom "menunggu" dan ikut menghitung `total`:
  `app/Services/QueueService.php:316-325`
- `QueueService::positionOf()` menghitung nomor lebih kecil yang "menunggu" tanpa memfilter status:
  `app/Services/QueueService.php:357-370`
- Kedua jalur pembatalan tidak membersihkan kolom antrian:
  `app/Http/Controllers/Api/BookingController.php:460`
  `app/Http/Controllers/Admin/BookingController.php:274`
  (bandingkan: `QueueService::checkIn` **memang** menolak booking `cancelled`, `:114-116`)

**Dampak**
Pasien yang sudah check-in lalu booking-nya dibatalkan (pasien berubah pikiran / salah input) akan tetap dipanggil petugas, memakan satu giliran, dan menggeser posisi tunggu pasien lain (`positionOf` ikut menghitungnya). Ini kejadian yang sangat mungkin di klinik.

**Perbaikan**
Ada dua opsi; pilih yang konsisten dengan aturan klinik:
1. Saat membatalkan booking, set `queue_status = 'no_show'` (jejak tetap ada di papan "selesai").
2. Atau tambahkan `->where('status', '!=', 'cancelled')` di `callNext()`, `board()`, dan `positionOf()`.
Opsi 1 lebih informatif untuk audit; opsi 2 lebih kecil risikonya. Minimal salah satu harus ada.

### F-04 · Pembatalan oleh pasien tidak memakai lock/transaksi (race dengan admin)
**Severity:** Sedang · **Domain:** booking

**Bukti**
- Jalur pasien: ambil booking → `update()` langsung, tanpa `DB::transaction`/`lockForUpdate`
  `app/Http/Controllers/Api/BookingController.php:449-467`
- Jalur admin: transaksi + lock
  `app/Http/Controllers/Admin/BookingController.php:270-284`
- Jalur reschedule pasien: sudah transaksi + lock (`BookingController.php:528-556`) → jadi hanya `cancel` yang tertinggal.

**Dampak**
Dua request bersamaan (pasien membatalkan di aplikasi, admin mengonfirmasi di web) dapat menghasilkan booking berstatus `cancelled` **setelah** admin mengonfirmasi — pasien menerima notifikasi "dikonfirmasi" dan "dibatalkan" sekaligus, dan klinik kehilangan slot tanpa jejak urutan kejadian.

**Perbaikan**
Bungkus `cancel` pasien dengan pola yang sama seperti admin (ambil ulang di dalam transaksi dengan `lockForUpdate`) dan cek ulang `status` sebelum menulis.

### F-05 · Booking bisa "selesai" walau belum lunas, aturannya beda dengan Pet Shop
**Severity:** Sedang · **Domain:** booking & pembayaran

**Bukti**
- `Admin\BookingController::complete()` hanya memeriksa status (`pending`/`confirmed`), tidak memeriksa `payment_status`/`remaining_amount`:
  `app/Http/Controllers/Admin/BookingController.php:220-240`
- Pet Shop **menolak** transisi yang sama:
  `app/Services/ProductOrderService.php:213-218`
  ```php
  if ($toStatus === STATUS_COMPLETED && !$locked->isPaid() && !$locked->isPayAtClinic()) { throw ... }
  ```
- Konfirmasi pembayaran manual ada di endpoint terpisah (`Admin/PaymentController.php:300`), jadi pelunasan bergantung pada disiplin operator.

**Dampak**
Booking berstatus `completed` dengan `payment_status = 'pending'` dan `remaining_amount > 0` tetap muncul di laporan "belum dibayar" (`DashboardController.php:107`, `Admin/PaymentController.php:143-146`) sementara booking sudah dihitung selesai — laporan bisa terlihat kontradiktif. Dua modul dengan aturan berbeda untuk konsep yang sama juga menyulitkan pelatihan operator.

**Perbaikan**
Pilih satu aturan dan terapkan di kedua modul. Rekomendasi: izinkan `completed` hanya jika `payment_status in ('paid','dp_paid')` **atau** klinik secara eksplisit mengaktifkan opsi "layani dulu, tagih kemudian" (flag di klinik), dengan konfirmasi eksplisit di UI admin.

### F-06 · Notifikasi pasien global tersimpan tanpa atribusi klinik
**Severity:** Sedang (rendah dampak hari ini, tinggi saat laporan per klinik dibutuhkan) · **Domain:** notifikasi

**Bukti**
`app/Services/FCMService.php:245-250`
```php
$clinicId = \App\Models\User::whereKey($userId)->value('clinic_id');
Notification::create(['user_id' => $userId, 'clinic_id' => $clinicId, ...]);
```
Untuk pasien (akun global) `clinic_id` selalu `null`, padahal klinik yang relevan sudah diketahui dari data notifikasi (`sendBookingStatusUpdate` membawa `booking->clinic_id`).

**Dampak**
Riwayat notifikasi per klinik tidak lengkap; setiap laporan/ekspor notifikasi berbasis klinik akan kehilangan seluruh pasien yang mendaftar sendiri. FK `notifications.clinic_id` = `SET NULL`, jadi tidak ada error — hanya data yang diam-diam kosong.

**Perbaikan**
Teruskan `clinic_id` eksplisit dari pemanggil (booking → `booking->clinic_id`, order → `order->clinic_id`) dan pakai itu alih-alih membaca `users.clinic_id`.

### F-07 · Hapus klinik menghapus riwayat klinis permanen dan meninggalkan pembayaran yatim
**Severity:** Sedang · **Domain:** superadmin, integritas data

**Bukti**
- `app/Services/ClinicDeletionService.php:99-133` menghapus `medical_records`, `bookings`, `doctors`, `services`, `pets`, `vaccinations`, `weight_records` milik klinik (hard delete).
- FK dari DB: `payments.clinic_id → SET NULL`, `medical_records.clinic_id → SET NULL`, `audit_logs.clinic_id → SET NULL`.
- `payments` **tidak** dihapus oleh service, dan `payments.payable_id` adalah polimorfik tanpa FK → setelah booking dihapus, baris pembayaran menunjuk `payable_id` yang tidak ada lagi.

**Dampak**
Setelah satu klinik dihapus: laporan keuangan global tetap memuat nominal (`gross_amount`) tetapi tanpa klinik dan tanpa objek (`payable_id` yatim); jejak audit kehilangan `clinic_id`. Untuk aplikasi kesehatan, penghapusan riwayat medis permanen juga berisiko dari sisi kepatuhan.

**Perbaikan** (pilih satu, dokumentasikan di `docs/TENANCY_RULES.md`)
1. Ubah jadi **soft delete** klinik (`is_active = false` permanen) dan larang hard delete bila ada `payments`/`bookings`.
2. Atau sebelum hapus, arsipkan: `payments` diberi `archived_clinic_name`/`archived_clinic_slug` di kolom snapshot (pola yang sudah dipakai `product_orders.pickup_clinic_name`), dan hapus hanya data operasional.

### F-08 · FCM dikirim sinkron dari dalam request, tanpa timeout, sementara job antrian tidak pernah dipakai
**Severity:** Sedang · **Domain:** notifikasi, performa API

**Bukti**
- `app/Services/FCMService.php:53` dan `:144` — `Http::...->post(...)` tanpa `->timeout()` (bandingkan `MidtransService.php:39,96`, `MidtransAccountGateway.php:19,73,92,153`, `MidtransPartnerGateway.php:76,119,140` yang semuanya sadar timeout).
- Dipanggil langsung di jalur request: `Api/BookingController.php:372,475,571`, `Admin/BookingController.php:195,236,281`, `Doctor/DoctorBookingController.php:177`.
- Job antrian tersedia tetapi tidak pernah di-dispatch: `app/Jobs/SendFcmNotification.php` (`grep -rn "SendFcmNotification" app/ routes/` → 0 pemanggil).

**Dampak**
Waktu respons `POST /bookings`, `POST /bookings/{id}/cancel`, dan aksi admin bergantung pada latensi Google FCM; bila FCM menggantung, request ikut menggantung (data sudah tersimpan, jadi pengguna bisa menekan tombol dua kali).

**Perbaikan**
`SendFcmNotification::dispatch(...)` setelah data ter-commit (job sudah `ShouldQueue`), dan tetap tambahkan `->timeout(5)->retry(1)` di `FCMService` sebagai jaring pengaman. Pastikan `start-queue.bat` dijalankan bersama server.

### F-09 · Riwayat Booking & Rekam Medis terpotong 20 item (API paginasi, klien tidak memaginasi)
**Severity:** Sedang · **Domain:** integrasi Android

**Bukti**
- `app/Http/Controllers/Api/BookingController.php:60` → `paginate(20)` + `pagination.last_page` di respons.
- `app/Http/Controllers/Api/MedicalRecordController.php:102` → `paginate(20)`.
- Klien tidak pernah mengirim `page`: `PetHeal_Android/.../ApiService.kt:264` (`getBookings()`) dan `:314` (`getMedicalRecords()`). Paging hanya ada untuk Pet Shop (`ApiService.kt:430`).

**Dampak**
Pasien dengan >20 booking/rekam medis kehilangan akses ke data lama tanpa indikator apa pun; Invoice Center membaca daftar yang sama sehingga tagihan lama juga hilang.

**Perbaikan**
Tambahkan `@Query("page")` + infinite scroll di Android (jalur yang benar), atau sementara naikkan `per_page` dan sediakan parameter `page` di klien.

### F-10 · Slot jam yang sudah lewat hari ini masih bisa dibooking di server
**Severity:** Sedang · **Domain:** booking

**Bukti**
- `app/Services/DoctorScheduleService.php:130-170` hanya memeriksa cuti, hari praktik, rentang jam, dan kelipatan slot — tidak membandingkan dengan waktu sekarang.
- Validasi request: `'booking_date' => 'required|date|after_or_equal:today'` di `BookingController.php:181` dan `:509`; endpoint slot `DoctorController.php:223-265` juga tidak menandai slot lampau.
- Klien menambal sendiri: `PetHeal_Android/.../util/BookingSlotAvailability.kt:36`.

**Dampak**
Meski memakai aplikasi resmi: pengguna membuka form 09:55, memilih slot 10:00, menekan "Booking" 10:03 → booking tercatat untuk waktu yang sudah lewat. Request yang dimanipulasi bisa membuat booking kapan saja.

**Perbaikan**
Di `validateSlot()`, tolak bila `Carbon::parse("$date $time")->isPast()` (toleransi 5 menit) → kembalikan pesan `slot_unavailable` yang sudah dikenal klien.

### F-11 · Timezone aplikasi UTC untuk domain WIB
**Severity:** Sedang · **Domain:** booking, antrian, validasi tanggal

**Bukti**
- `config/app.php:68` → `'timezone' => 'UTC'`.
- Check-in membandingkan tanggal: `BookingController.php:410-416` (`today()->toDateString()`).
- Antrian memakai tanggal UTC: `QueueService.php:103`, `:300`, `:318`.
- Validasi `after_or_equal:today`: `BookingController.php:181,509`; `DoctorController.php:226`.

**Dampak** (antara 00:00–06:59 WIB, tanggal UTC = kemarin)
1. Pasien dengan booking "hari ini" **tidak bisa check-in** (dianggap bukan hari jadwal).
2. `booking_date` kemarin masih lolos validasi.
3. Nomor antrian yang dibuat dini hari masuk ke `queue_date` hari sebelumnya, sehingga tidak muncul di papan hari ini.

**Perbaikan**
`APP_TIMEZONE=Asia/Jakarta` di `.env` + `'timezone' => env('APP_TIMEZONE', 'UTC')` di `config/app.php`, lalu `php artisan config:clear`. Tidak ada perubahan format kolom; seluruh perbandingan `today()`/`now()` ikut benar.

---

## 5. TEMUAN — Rendah

### F-12 · Tiga definisi "pendapatan" berbeda dalam satu aplikasi, tidak ada total gabungan
**Severity:** Rendah · **Domain:** dashboard & laporan

**Bukti**
1. Kartu **"Pendapatan"** dashboard klinik hanya menjumlahkan rekam medis:
   `app/Http/Controllers/Admin/DashboardController.php:62-90`
   (`SUM(cost) + SUM(treatment_cost) + SUM(medicine_cost)` dari `medical_records`).
2. Angka **"terkumpul"** di tempat lain memakai pembayaran booking:
   `app/Http/Controllers/Admin/DashboardController.php:118-125`
   (`SUM(bookings.paid_amount) - SUM(booking_refunds.amount)`).
3. Pet Shop punya angka ketiga di halaman terpisah:
   `app/Http/Controllers/Admin/PetShopDashboardController.php:42` (`SUM(total_amount)` order selesai).

**Dampak**
Untuk klinik yang sama, tiga halaman menampilkan tiga angka berbeda yang semuanya diberi label "pendapatan"/"terkumpul". Booking layanan tanpa rekam medis (mis. grooming, vaksin tanpa SOAP) tidak masuk angka (1) padahal uangnya masuk ke (2); penjualan Pet Shop hanya ada di (3). Pemilik klinik tidak punya satu angka pun yang bisa dipercaya sebagai total.

**Perbaikan**
Tetapkan satu definisi resmi (rekomendasi: uang yang benar-benar diterima = `bookings.paid_amount` + `medical_records.extra_payment_paid_amount` + `product_orders` lunas − refund) dan tampilkan sebagai kartu "Total pendapatan diterima", dengan angka (1)/(2)/(3) sebagai rincian, bukan judul.

### F-13 · Aritmetika uang memakai float PHP (199 cast) dengan toleransi epsilon
**Severity:** Rendah (tetap risikonya nyata untuk pembulatan) · **Domain:** pembayaran

**Bukti**
- 199 pemakaian `(float)` di `app/`; contoh pembanding nominal dengan toleransi manual:
  `Admin/PaymentController.php:324` (`$amount > $remaining + 0.001`),
  `RefundService.php:57,62` (`<= 0.009`, `- 0.009 > $refundable`),
  `PaymentController.php:181-207`.
- Kolom DB sudah `decimal(10,2)`, jadi yang tidak konsisten adalah lapisan PHP-nya.

**Dampak**
Selama nominal selalu rupiah bulat risikonya kecil, tetapi setiap pembulatan DP 50% (`total * 0.5`) dan pembagian komisi adalah titik di mana selisih 1 rupiah bisa muncul dan sulit direproduksi.

**Perbaikan**
Normalisasi ke integer rupiah di batas masuk/keluar (`(int) round($x)`) dan simpan sebagai int pada objek domain; atau pakai `bcmath`. Sediakan test dengan nominal ganjil (mis. 33.333) untuk DP 50%.

### F-14 · Kolom `clinic_payment_accounts.server_key` tersisa sebagai teks biasa
**Severity:** Rendah (belum dieksploitasi, tapi berbahaya bila terisi) · **Domain:** keamanan pembayaran

**Bukti**
- Migrasi: `database/migrations/2026_11_14_100001_create_clinic_payment_account_tables.php:50` → `$table->text('server_key')->nullable();` tanpa enkripsi.
- Yang benar-benar dipakai adalah versi terenkripsi: `app/Models/PaymentCredentialVersion.php:50` (`'server_key' => 'encrypted'`) via `PaymentConfigurationService.php:78`.

**Dampak**
Kolom mati tetapi berada di tabel yang sama dengan kredensial; siapa pun yang nanti menulis ke kolom itu akan menyimpan Server Key Midtrans dalam bentuk terbuka (ikut terbaca pada dump database/backup).

**Perbaikan**
Hapus kolom pada migrasi baru (`dropColumn`) setelah memastikan tidak ada pembacaan, atau ubah cast menjadi terenkripsi bila masih dibutuhkan sebagai cadangan.

### F-15 · `PaymentSplitCalculator` (komisi klinik) tidak tersambung ke transaksi
**Severity:** Rendah · **Domain:** pembayaran, dead code

**Bukti**
- `app/Services/PaymentSplitCalculator.php` hanya dipakai `tests/Unit/PaymentSplitCalculatorTest.php` (6 test lulus); `grep -rn "PaymentSplitCalculator" app/ routes/` → 0 pemakaian produksi.
- `commission_percent` disimpan dan dibaca untuk tampilan: `MerchantConfigurationService.php:42,64`, `PaymentService.php:52`, `Admin/PetShopMerchantController.php:49,68`.
- `payments.split_settings` hanya **diteruskan** bila sudah ada: `app/Services/MidtransPartnerGateway.php:64-65`; tidak ada kode yang menghasilkannya.

**Dampak**
Komisi platform yang diatur superadmin tidak pernah benar-benar memotong transaksi; yang ada hanya angka konfigurasi. Ini juga membuat pembaca kode mengira fitur split sudah aktif.

**Perbaikan**
Panggil kalkulator saat membuat `Payment` (`PaymentService.php:122,227,364,574`) dan tulis `split_settings`, **atau** hapus kelas + test dan tandai fitur "belum diimplementasikan" di dokumentasi merchant.

### F-16 · `PatientScope` adalah dead code untuk aturan yang paling sensitif
**Severity:** Rendah · **Domain:** kebersihan kode

**Bukti**
`app/Services/PatientScope.php` berisi definisi "pasien global vs staf terkurung klinik", tetapi `grep -rn "PatientScope::" app/ routes/` → 0 referensi. Aturan yang sama ditulis ulang di `app/Http/Middleware/ApiAuthenticate.php` (PHASE 1/3/7) dan di `BookingController.php:150-172`, `DoctorController.php` (`doctorScope`).

**Dampak**
Dua sumber kebenaran untuk aturan tenancy — salah satu akan tertinggal saat aturan berubah (persis penyebab F-01/F-02).

**Perbaikan**
Pilih satu: jadikan `ApiAuthenticate`/controller memanggil `PatientScope`, atau hapus kelasnya.

### F-17 · Route & konfigurasi mati
**Severity:** Rendah · **Domain:** kebersihan kode

**Bukti**
- `sanctum/csrf-cookie` terdaftar otomatis (`vendor/laravel/sanctum/src/SanctumServiceProvider.php:74`) padahal API memakai Bearer token (`ApiAuthenticate.php`) dan web memakai form sesi Blade — tidak ada klien cookie/SPA.
- Dua endpoint sehat: `/up` (`bootstrap/app.php:11`) dan `/api/health` (`routes/api.php:53`).
- Config mati: `config/services.php:66` `'server_key' => env('FCM_API_KEY')` (komentar "Legacy - not used"), sementara `.env` masih berisi `FCM_API_KEY=your-fcm-api-key-here`.

**Dampak**
Menambah permukaan serangan/endpoint yang tidak perlu dan membingungkan saat debug notifikasi ("kenapa API key salah tapi notifikasi jalan?" — karena memang tidak dipakai).

**Perbaikan**
Hapus route/kunci tersebut, atau beri komentar tegas di `.env.example` bahwa nilainya diabaikan.

### F-18 · Endpoint publik di luar throttle
**Severity:** Rendah · **Domain:** keamanan/kuota

**Bukti** `routes/api.php`
- `:53` `GET /api/health` (tanpa throttle)
- `:69` `GET /api/payment-methods`
- `:73` `GET /api/services`
- `:74` `GET /api/services/{id}`

Grup publik lain sudah dibatasi (`throttle:public-catalog`, `throttle:webhook`, `throttle:auth`).

**Dampak**
Katalog layanan bisa di-scrape tanpa batas dan tanpa tercatat; satu-satunya pembatas adalah kapasitas server.

**Perbaikan**
Bungkus dengan `throttle:public-catalog` (sudah ada limiternya di `AppServiceProvider.php`).

### F-19 · `APP_DEBUG=true` pada tunnel publik + drift dokumentasi `.env`
**Severity:** Rendah · **Domain:** operasional/keamanan

**Bukti**
- `.env`: `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://127.0.0.1:8000`, `ASSET_URL` sama.
- `AGENTS.md:14` menyatakan `.env` berisi `APP_URL=https://envious-reselect-darn.ngrok-free.dev`.
- API aman: seluruh 500 disanitasi (`bootstrap/app.php:104-118`). Halaman **web** (admin/portal dokter) tidak melalui sanitasi tersebut.
- Gambar tetap benar karena `UseRequestAssetOrigin.php:30-70` menyelaraskan origin aset dengan host request.

**Dampak**
Bila tunnel dibuka saat presentasi lalu terjadi error di halaman admin, stack trace + query + path server tampil di browser. Selain itu, tautan yang dibangun **tanpa** request (job antrian, scheduler, email) akan berisi `http://127.0.0.1:8000`.

**Perbaikan**
`APP_DEBUG=false` saat mendemokan lewat tunnel; samakan `AGENTS.md` dengan `.env`, atau isi `.env` dengan URL ngrok dan andalkan `UseRequestAssetOrigin` untuk lokal.

### F-20 · Cakupan test terkonsentrasi di web admin/dokter, tipis di API
**Severity:** Rendah · **Domain:** kualitas

**Bukti** (peta 46 file test, 80 URL unik)

| Prefiks | Panggilan test |
|---|---|
| `/admin/doctors` | 47 |
| `/admin/register` | 28 |
| `/admin/medicines` | 27 |
| `/admin/join-requests` | 24 |
| `/doctor/profile` | 21 |
| `/admin/payments` | 13 |
| `/api/*` (semua) | ≤ 5 |

**Dampak**
Tiga temuan di laporan ini (F-05, F-09, F-10) berada di jalur API yang hampir tidak punya test, sehingga regresi tidak akan tertangkap `php artisan test` yang sekarang.

**Perbaikan**
Prioritaskan test API untuk: `POST /bookings` (slot lampau, bentrok, DP), `POST /bookings/{id}/cancel` (race), `GET /bookings?page=2`, serta `POST /pet-shop/orders` (stok kurang).

---

## 6. Yang sudah benar (diverifikasi, bukan asumsi)

Bagian ini penting agar perbaikan tidak menyentuh hal yang sudah matang.

### 6.1 Otorisasi & isolasi tenant
- `ApiAuthenticate.php` fail-closed: staf tanpa `clinic_id` ditolak, klinik nonaktif ditolak, aksi akun-sendiri (hapus akun, OTP ganti email) tetap diizinkan; `X-Clinic-Slug` hanya penanda konteks, bukan sumber otorisasi.
- Admin web: setiap query list/show/update/delete memakai `currentClinicId()`; superadmin dikecualikan lewat `EnsureSuperAdmin` (route terpisah, bukan parameter).
- Portal dokter: seluruh query memakai `doctor_id` **dan** `clinic_id` (`DoctorMedicalRecordController.php:58-59,69-70,151-153`, `DoctorBookingController.php:44-45,60-61,86-87,163-164`).
- API pasien: kepemilikan data selalu lewat relasi (`$request->user()->bookings()->find(...)`, `pets()->find(...)`, `where('user_id', ...)`) di `BookingController`, `MedicalRecordController`, `PaymentController`, `PetController`, `NotificationController:38`.
- Tidak ada eskalasi peran dari web: `Admin/UserController` hanya membuat `role = 'user'` (`:117`) dan `canManageAccount()` menolak mengubah staf/dokter lain (`:153-160`).

### 6.2 Uang & pembayaran
- Nominal dari klien selalu ditolak bila tidak sama dengan nilai server: `PaymentController.php:181-207`.
- Harga/diskon Pet Shop dihitung ulang dari DB; baris dengan varian sama digabung agar stok tidak bisa dilewati (`ProductOrderService.php:43-56,100-155`).
- Webhook: signature SHA512 memakai kredensial **per transaksi** (`MidtransWebhookController.php:308-368`), `gross_amount` dicocokkan (`:140,199,271`), notifikasi dicatat sebelum diproses.
- Pelunasan & konfirmasi manual memakai row lock dan batas sisa tagihan (`Admin/PaymentController.php:300-350`).
- Refund hanya untuk booking `cancelled`, dibatasi `paid - refunded` (`RefundService.php:47-84`).
- Kredensial Midtrans per klinik disimpan terenkripsi dan berversi (`PaymentConfigurationService.php:60-100`).

### 6.3 Stok & inventaris
- Reservasi stok dalam transaksi + `lockForUpdate` (`ProductOrderService.php:70-74`), rilis saat batal/kedaluwarsa (`ProductStockService`).
- Mutasi obat: cek saldo, batch, karantina, pembalikan, dan larangan stok negatif (`MedicineStockService.php:50-130`, `MedicineMutationService.php:37-300`).
- Koreksi resep mengembalikan stok lama sebelum memotong ulang (`Admin/MedicalRecordController.php:426-439`).

### 6.4 Konsistensi API
- Envelope 401/422/404/500 dipusatkan (`bootstrap/app.php:59-118`).
- 306 route → 306 method valid; 0 endpoint mati di controller.
- Filter daftar admin memakai whitelist (`Admin/PaymentController.php:231-244`) — tidak ada vektor `ORDER BY`.

### 6.5 Operasional yang sudah ada (jangan dihapus)
- Scheduler terdaftar: `petshop:expire-orders` (5 menit), `payments:reconcile` (15 menit), `reminders:send` (08:00), prune OTP (`routes/console.php:12-38`).
- `start-queue.bat` tersedia; `public/storage` symlink & `public/build` (Vite) sudah ada.
- Log 5,5 MB, channel `stack` bawaan — tidak ada log rahasia (token/password/server key tidak pernah dicetak).

---

## 7. Skema database & integritas data

**54 tabel, 39 di antaranya ber-FK.** Tabel tanpa FK adalah tabel global/infrastruktur (`clinics`, `otp_codes`, `payment_methods`, `service_categories`, `product_categories`, `sessions`, `cache*`, `jobs*`, `migrations`, `personal_access_tokens`, `password_reset_tokens`, `app_settings`) — wajar.

Index panas yang terverifikasi ada:

| Tabel | Index penting |
|---|---|
| `bookings` | `(clinic_id,doctor_id,booking_date,booking_time)`, `(doctor_id,booking_date,booking_time)`, `(user_id,status)`, `(booking_date,status)`, `(clinic_id,status,booking_date)`, `(clinic_id,queue_date,queue_status)`, unik `(clinic_id,queue_date,queue_number)` |
| `payments` | unik `provider_order_id`, `payment_code`, `(payable_type,payable_id)`, `(clinic_id,status)`, `(payment_type,status)`, `routing_source` |
| `product_orders` | unik `order_number`, `(user_id,created_at)`, `(clinic_id,status)`, `(clinic_id,payment_status)` |
| `notifications` | `(user_id,read_at)`, `(user_id,created_at)`, `type` |
| `otp_codes` | unik `(email,purpose)`, `expires_at` |
| `product_variants` | unik `sku`, `(clinic_product_id,is_active)` |
| `service_requests` | `(clinic_id,status)`, `(user_id,status)`, `(clinic_id,doctor_id,status)` |

Catatan integritas:

1. **Cascade klinik** — `bookings`, `product_orders`, `clinic_products`, `doctors`, `services` = `CASCADE`; `payments`, `medical_records`, `notifications`, `audit_logs`, `users` = `SET NULL`. Lihat **F-07** untuk konsekuensinya.
2. **Polimorfik tanpa FK** — `payments.payable_id`, `payment_events`, `audit_logs.model_id`: tidak ada jaminan referensial. Saat ini konsisten, tetapi tidak ada pembersih untuk baris yatim (rekomendasi: command `payments:prune-orphans` opsional).
3. **Uang** — seluruh kolom nominal `decimal(10,2)`; tidak ditemukan kolom uang bertipe float di DB. Masalah hanya di lapisan PHP (**F-13**).
4. **Soft delete** — dipakai pada produk & obat; index unik memperhitungkan hal ini pada `product_variants.sku` (tidak bentrok saat arsip), tidak ada konflik unik yang ditemukan.

---

## 8. Keamanan (peta ringkas)

| Kategori | Status | Bukti / catatan |
|---|---|---|
| Injeksi SQL | ✅ Tidak ditemukan | Semua `*Raw` memakai konstanta; `orderBy` dari input memakai whitelist (`Admin/PaymentController.php:231-244`) |
| XSS | ✅ | 0 pemakaian `{!! !!}` di 116 view |
| CSRF | ✅ | 0 form non-GET tanpa `@csrf` |
| Broken access control | ⚠️ Lihat F-01 | Isolasi per-query benar; yang salah adalah *definisi* pasien klinik |
| Race condition | ⚠️ Lihat F-03, F-04 | Mayoritas jalur sudah `lockForUpdate`; dua jalur tertinggal |
| Brute force | ✅ | login 8/menit per email+IP; admin login 5/menit; OTP maks 5 percobaan per kode (`OtpService.php:183-189`) |
| Enumerasi akun | ✅ | Reset sandi tidak membocorkan keberadaan email |
| Manajemen sesi | ✅ | `session()->regenerate()` pada login (`AdminAuthController.php:86`, `DoctorAuthController.php:78`), `invalidate()` saat logout |
| Rahasia tersimpan | ✅ (kecuali F-14) | Server key Midtrans `encrypted` (`PaymentCredentialVersion.php:50`) |
| Kebocoran lewat log | ✅ | Tidak ada `Log::` yang mencetak token/password/server key |
| Upload file | ✅ | `image|mimes:jpeg,png,jpg,webp|max:4096` + `ImageService::process`; lampiran rekam medis memakai whitelist MIME (`MedicalRecordAttachmentService::ALLOWED_MIMES`) |
| SSRF / RCE / path traversal | ✅ | 0 temuan |
| Debug mode | ⚠️ F-19 | API tersanitasi; web belum |
| Rate limit katalog publik | ⚠️ F-18 | 4 endpoint tanpa throttle |

---

## 9. Kinerja & operasional

**Yang sudah dioptimasi**
- Cache katalog dokter 15 menit dengan kunci berisi scope klinik + versi, dan invalidasi terjaga: `DoctorController.php:91-96` + bump versi di `Admin/DoctorController.php:23` dan saat pasien memberi ulasan (`DoctorController.php:352`).
- Cache dashboard & statistik pembayaran dengan invalidasi per klinik (`PaymentStatusService.php:19-20`, `Admin/PaymentController.php:368-369`).
- Eager loading konsisten pada daftar admin (`Admin/BookingController.php:35`, `Admin/UserController.php:38`, `Admin/MedicalRecordController`).
- Aggregat memakai `withCount`/`withAvg` untuk menghindari query per baris (`DoctorController.php:100-103`).

**Yang masih berisiko**
1. **FCM sinkron** (F-08) — satu-satunya panggilan jaringan tanpa timeout di jalur request.
2. **Scheduler tidak pernah berjalan di lokal**: `routes/console.php` menjadwalkan tiga command, tetapi tidak ada `start-scheduler.bat`; `README.md:343` hanya menyebut cron Linux. Akibatnya `petshop:expire-orders` (pelepas stok kedaluwarsa) dan `payments:reconcile` tidak berjalan saat demo di Windows.
3. **Filter admin tanpa validasi**: `Admin/BookingController.php:39-46` memakai `$request->input('status')` dan `('date')` langsung ke `where`/`whereDate`; nilai aneh hanya menghasilkan daftar kosong (tidak berbahaya), tetapi form yang salah ketik tidak memberi pesan.
4. **Pertumbuhan `personal_access_tokens`**: token Sanctum dibuat tanpa masa berlaku (`AuthController.php:178`) dan tidak dicabut saat login baru.

---

## 10. Matriks 20 temuan

| ID | Severity | Judul singkat | Lokasi utama |
|---|---|---|---|
| F-01 | **Tinggi** | Pasien global tak muncul di Admin Users & statistik | `Admin/UserController.php:24`, `Admin/DashboardController.php:55,488` |
| F-02 | **Tinggi** | Dokumen tenancy masih "1 akun = 1 klinik" | `docs/TENANCY_RULES.md:8` |
| F-03 | Sedang | Antrian memanggil booking yang sudah batal | `QueueService.php:302,316,357` |
| F-04 | Sedang | Cancel pasien tanpa lock (race dengan admin) | `Api/BookingController.php:449-467` |
| F-05 | Sedang | Booking bisa selesai walau belum lunas | `Admin/BookingController.php:220-240` |
| F-06 | Sedang | Notifikasi pasien global tanpa atribusi klinik | `FCMService.php:245-250` |
| F-07 | Sedang | Hapus klinik menghapus riwayat & menyisakan payment yatim | `ClinicDeletionService.php:99-133` |
| F-08 | Sedang | FCM sinkron tanpa timeout; job antrian menganggur | `FCMService.php:53,144` |
| F-09 | Sedang | Paging `/bookings`, `/medical-records` tidak dipakai klien | `BookingController.php:60`, `ApiService.kt:264,314` |
| F-10 | Sedang | Slot lampau masih bisa dibooking | `DoctorScheduleService.php:130-170` |
| F-11 | Sedang | Timezone UTC untuk domain WIB | `config/app.php:68` |
| F-12 | Rendah | Tiga definisi "pendapatan" berbeda, tanpa total gabungan | `Admin/DashboardController.php:62-90,118-125`, `PetShopDashboardController.php:42` |
| F-13 | Rendah | Aritmetika uang float + epsilon | `Admin/PaymentController.php:324`, `RefundService.php:57-62` |
| F-14 | Rendah | Kolom `server_key` plaintext tak terpakai | migrasi `2026_11_14_100001:50` |
| F-15 | Rendah | Split komisi tidak tersambung | `PaymentSplitCalculator.php` |
| F-16 | Rendah | `PatientScope` dead code | `Services/PatientScope.php` |
| F-17 | Rendah | Route/config mati (`sanctum/csrf-cookie`, `FCM_API_KEY`) | `routes/api.php`, `config/services.php:66` |
| F-18 | Rendah | 4 endpoint publik tanpa throttle | `routes/api.php:53,69,73,74` |
| F-19 | Rendah | `APP_DEBUG=true` di tunnel + drift `AGENTS.md` | `.env`, `AGENTS.md:14` |
| F-20 | Rendah | Cakupan test API tipis | `tests/` |

---

## 11. Rencana perbaikan berurut dampak

### P0 — sebelum demo (perbaikan kecil, dampak besar)
| # | Item | Perkiraan | Risiko perubahan |
|---|---|---|---|
| 1 | **F-01** definisi pasien klinik = relasi transaksi (Users + Dashboard) | 40 baris | Rendah; hanya mengubah filter baca |
| 2 | **F-02** perbarui `TENANCY_RULES.md` | 5 baris | Nol |
| 3 | **F-11** `APP_TIMEZONE=Asia/Jakarta` | 2 baris + `config:clear` | Rendah; periksa ulang tampilan jam |
| 4 | **F-10** tolak slot lampau di `validateSlot` | 8 baris | Rendah; pesan sudah dikenal klien |
| 5 | **F-03** antrian mengecualikan booking batal | 6 baris | Rendah |

### P1 — kualitas & konsistensi
6. **F-04** lock pada cancel pasien (pola sama seperti admin) — 10 baris.
7. **F-08** FCM via queue + `timeout(5)` — 15 baris + pastikan worker hidup.
8. **F-05** aturan `completed` disamakan dengan Pet Shop (atau flag klinik) — 12 baris + konfirmasi UI.
9. **F-09** paging di Android (atau `per_page` naik sementara) — sisi klien.
10. **F-06** teruskan `clinic_id` eksplisit ke `storeNotification` — 10 baris.

### P2 — kebersihan & kesiapan produksi
11. **F-13** normalisasi uang ke integer rupiah + test nominal ganjil.
12. **F-14** `dropColumn('server_key')` pada `clinic_payment_accounts`.
13. **F-15 / F-16** sambungkan atau hapus `PaymentSplitCalculator` & `PatientScope`.
14. **F-17 / F-18** hapus route mati, throttle 4 endpoint publik.
15. **F-19** `APP_DEBUG=false` saat tunnel, sinkronkan `AGENTS.md`.
16. **F-20** tambah test API: booking (slot lampau/bentrok/DP), cancel, paging.
17. **F-12** satu definisi "Total pendapatan diterima" (booking + tindakan + Pet Shop − refund), rincian tetap ada.
18. **F-07** kebijakan hapus klinik: soft delete atau snapshot arsip.

---

## 12. Lampiran — cakupan pembacaan

**Controller diperiksa (45 file / 16.364 baris):** `Api/{Auth,Booking,MedicalRecord,Payment,MidtransWebhook,PetShopOrder,PetShop,PetShopClinic,PublicClinic,Doctor,Pet,ServiceRequest,Notification,Dashboard,Vaccination,WeightRecord,Health,DeviceToken,Service,PaymentMethod}` · `Admin/{AdminAuth,Booking,MedicalRecord,Medicine,MedicineStock,Payment,PaymentSettings,PetShopProduct,PetShopOrder,PetShopPickup,PetShopDashboard,PetShopMerchant,Doctor,DoctorSchedule,Clinic,Service,ServiceRequest,User,Dashboard,Queue,NotificationSettings,ServiceCategory}` · `Doctor/{DoctorAuth,DoctorBooking,DoctorMedicalRecord,DoctorQueue,DoctorSchedule,DoctorProfile,DoctorServiceRequest}`.

**Service diperiksa (34):** `PaymentService`, `PaymentStatusService`, `PaymentConfigurationService`, `PaymentReconciliationService`, `PaymentSplitCalculator`, `RefundService`, `ProductOrderService`, `ProductStockService`, `PickupPassService`, `ReceiptService`, `QueueService`, `ServiceRequestService`, `DoctorScheduleService`, `PrescriptionService`, `MedicineStockService`, `MedicineMutationService`, `MedicalRecordAttachmentService`, `FCMService`, `FirebaseService`, `OtpService`, `ImageService`, `ClinicDeletionService`, `ClinicDiscoveryService`, `ClinicRatingService`, `MerchantConfigurationService`, `MidtransService`, `MidtransAccountGateway`, `MidtransPartnerGateway`, `PaymentAccountResolver`, `PatientScope`, `PaymentGatewayException`, `PaymentGatewayInterface`, `PaymentRoutingException`, `ResolvedPaymentAccount`.

**Middleware (7):** `ApiAuthenticate`, `AdminAuth`, `DoctorAuth`, `EnsureSuperAdmin`, `SetLocale`, `CacheStaticAssets`, `UseRequestAssetOrigin`.

**Lainnya:** `routes/api.php`, `routes/web.php`, `routes/console.php`, `bootstrap/app.php`, `app/helpers.php`, `config/{app,session,logging,services,petshop}.php`, 54 tabel via `information_schema`, 116 view Blade (scan XSS/CSRF), 46 file test.

**Catatan metode:** audit ini berbasis pembacaan kode + eksekusi nyata (lint, test, query skema). **Tidak** mencakup uji beban, uji tembus jaringan, atau penilaian UI visual — itu di luar cakupan backend.
