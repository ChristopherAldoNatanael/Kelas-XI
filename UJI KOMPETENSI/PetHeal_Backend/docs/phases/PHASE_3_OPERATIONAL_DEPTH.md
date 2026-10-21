# PHASE 3 — Operational Depth

**Tanggal:** 2026-10-20
**Status:** Selesai untuk scope booking slot, invoice center, schedule board, dan command palette
**Roadmap:** `docs/UIUX_ROADMAP.md`

## 1. Tujuan

Fase 3 menutup celah operasional yang paling terasa setelah Fase 1–2:

1. Jadwal dokter bisa dipilih dari slot nyata, bukan tebakan.
2. Tagihan pelanggan dan invoice admin memakai satu sumber data pembayaran.
3. Admin punya papan jadwal harian per dokter/jam dengan aksi status.
4. Pencarian global menemukan booking, hewan, dan pemilik tanpa bocor antar-klinik.

Tidak ada migration atau perubahan skema database di fase ini. Semua fitur
memakai field dan endpoint yang sudah ada.

## 2. Booking slot picker (Android)

**File utama:**

- `PetHeal_Android/app/src/main/java/com/christopheraldoo/petheal/ui/components/BookingSlotPicker.kt:73` (`BookingDateRail`)
- `.../BookingSlotPicker.kt:192` (`BookingTimeSlots`)
- `.../util/DoctorAvailability.kt:15` (`isDoctorWorkingDay`)
- `.../util/DoctorAvailability.kt:29` (`firstDoctorWorkingDate`)
- `.../ui/screens/booking/BookingViewModel.kt:481` (`loadSlots`)
- `.../ui/screens/booking/BookingViewModel.kt:568` (`createBooking`)

**Perilaku:**

- Rail 14 hari dengan status hari praktik/nonaktif. Hari pertama yang dipilih
  otomatis hari kerja dokter, bukan selalu hari ini.
- Slot dikelompokkan per sesi, dengan skeleton loading, retry, empty state,
  hari tutup, dan slot penuh.
- Respons lama tidak menimpa tanggal yang sedang dipilih (guard request).
- Ganti tanggal mengosongkan jam terpilih agar tidak ada kombinasi tanggal/jam
  yang salah.
- `409` dipetakan ke `slot_conflict` di
  `.../data/repository/BookingRepository.kt:90` dan `:137`. Di create flow jam
  yang bentrok dikosongkan lalu slot dimuat ulang; pilihan hewan/layanan/dokter
  tidak ikut hilang.
- Reschedule memakai slot live. Jika API menolak, dialog tetap terbuka dan
  menampilkan pesan; pengguna tidak dipaksa mengulang dari awal. Pada `409`,
  ketersediaan dimuat ulang dan tombol submit dinonaktifkan sampai jam baru
  dipilih (`BookingsScreen.kt` sekitar dialog reschedule).

## 3. Invoice/receipt center

### 3.1 Android — tab `Tagihan`

**File utama:**

- `.../ui/screens/booking/BookingsScreen.kt:99` (`BookingTab`)
- `.../ui/screens/booking/BookingsScreen.kt:381` (`InvoiceCenter`)
- `.../ui/screens/booking/InvoiceCenter.kt:85` (`InvoiceCenter`)
- `.../InvoiceCenter.kt:284` (`InvoiceCard`)
- `.../ui/components/PaymentReceiptSheet.kt:76` (`PaymentReceiptSheet`)
- `.../ui/components/PaymentReceiptImage.kt:51` (`createPaymentReceiptBitmap`)
- `.../util/PaymentReceipt.kt:13` (`PaymentReceipt`)

- Daftar booking punya dua tab: `Jadwal` dan `Tagihan`. Tab tagihan memakai
  `state.allBookings`, sehingga filter tanggal pada tab jadwal tidak
  menyembunyikan invoice.
- Ringkasan: total tagihan, DP berjalan, dan lunas. Filter: semua, belum
  dibayar, DP/parsial, lunas.
- Kartu invoice menghitung sisa dari `remainingAmount` (fallback
  `totalAmount - paidAmount`), bukan rumus baru.
- Aksi bayar membedakan DP awal, sisa DP, dan pelunasan.
- Bukti pembayaran tersedia untuk invoice lunas **dan DP/parsial**: tombol
  `Bukti` di kartu invoice membuka sheet resmi berisi nomor invoice, status,
  hewan, dokter, layanan, jadwal, metode, total, DP, dibayar, dan sisa.
- Pengguna dapat menyimpan PNG 1080×1760 ke Galeri/PetHeal (Android 10+) atau
  lewat pemilih lokasi (Android 9 ke bawah), membagikan gambar + teks, atau
  menyalin teks bukti. Semua angka berasal dari `Booking` backend; tidak ada
  nominal atau receipt mock.
- Setelah pembayaran sukses, `PaymentResultScreen` memuat booking terbaru dan
  otomatis menampilkan sheet bukti sekali; kartu ringkasan tetap tersedia
  untuk membuka ulang bukti. DP menampilkan sisa, pelunasan menampilkan lunas.
- PDF customer belum diunduh langsung dari aplikasi; PDF admin tetap tersedia
  di `admin.payments.receipt`.

### 3.2 Admin — invoice & receipt

**File utama:**

- `PetHeal_Backend/routes/web.php:129` (`admin.payments.receipt`)
- `.../app/Http/Controllers/Admin/PaymentController.php:137` (`receipt`)
- `.../resources/views/admin/payments/receipt_pdf.blade.php:162`
- `.../resources/views/admin/payments/index.blade.php:118`
- `.../resources/views/admin/payments/show.blade.php:20`

- Nomor invoice diturunkan dari booking ID: `INV-{id 5 digit}`. Tidak ada
  kolom baru dan tidak ada nomor yang bisa berbeda antar halaman.
- Halaman daftar berjudul **Invoice & Transaksi**, punya kolom invoice, dan
  aksi unduh PDF per baris.
- Halaman detail menampilkan nomor invoice dan tombol **Unduh Invoice**.
- PDF memakai data booking/payment yang sama: layanan, jadwal, total, dibayar,
  sisa, DP, metode, status, dan identitas klinik.
- Pencarian tetap memakai nama customer/hewan atau ID booking yang sudah ada.

## 4. Admin schedule board

**File utama:**

- `PetHeal_Backend/routes/web.php:97` (`admin.bookings.schedule`)
- `.../app/Http/Controllers/Admin/BookingController.php:57` (`schedule`)
- `.../resources/views/admin/schedule/index.blade.php:27`
- `.../resources/views/admin/schedule/select_clinic.blade.php`
- `.../app/Http/Controllers/Admin/AdminAuthController.php:220` (`switchClinic`)

**Perilaku:**

- Board harian dikelompokkan per dokter; setiap kartu berisi jam, hewan,
  pemilik, layanan, status booking, dan status pembayaran.
- Navigasi tanggal sebelumnya/berikutnya, date picker, filter dokter, tombol
  `Hari ini`, dan ringkasan total/menunggu/terkonfirmasi/selesai.
- Aksi memakai route yang sudah ada: konfirmasi, selesai, batalkan, dan
  detail. Pembatalan memakai modal alasan, bukan `prompt()`.
- `currentClinicId()` wajib ada. Super admin yang masih di system overview
  melihat halaman pilih klinik; setelah memilih, `switchClinic()` kembali ke
  board (`AdminAuthController.php:238`). Ini mencegah board mencampur klinik.
- Navigasi sidebar punya item `Jadwal Klinik`; item `Janji Temu` tidak lagi
  ikut aktif saat berada di board.

## 5. Global command palette

**File utama:**

- `PetHeal_Backend/routes/web.php:78` (`admin.search`)
- `.../app/Http/Controllers/Admin/DashboardController.php:408` (`search`)
- `.../resources/views/layouts/admin.blade.php:245` (trigger)
- `.../layouts/admin.blade.php:297` (modal)
- `.../layouts/admin.blade.php:454` (behavior)

**Perilaku:**

- Trigger di header dan shortcut `Ctrl/Cmd + K`. `Esc` menutup.
- Mencari booking (ID/nama hewan/nama pemilik), hewan, dan pemilik dengan
  debounce 180 ms dan `AbortController` agar respons lama tidak menimpa query
  baru.
- Hasil dikelompokkan, bisa dipilih dengan panah/Enter atau klik, dan memakai
  DOM API (`textContent`) sehingga data pengguna tidak di-render sebagai HTML.
- Semua query memakai `clinic_id` dari `currentClinicId()`. Jika super admin
  belum memilih klinik, endpoint mengembalikan pesan untuk memilih klinik dan
  **tidak** mengembalikan data lintas klinik.
- Saat query kosong, palette menampilkan aksi cepat ke Papan Jadwal, Semua
  Booking, dan Invoice.

## 6. Verifikasi

Backend:

- `php -l` untuk `PaymentController.php`, `BookingController.php`,
  `DashboardController.php`, `AdminAuthController.php`, dan `routes/web.php`:
  tidak ada syntax error.
- `php artisan view:cache`: sukses.
- `php artisan route:list --path=admin`: route `admin.schedule`,
  `admin.search`, dan `admin.payments.receipt` terdaftar.
- `npm run build`: sukses (CSS 116.04 kB / gzip 17.77 kB; JS 258.46 kB /
  gzip 89.86 kB).
- HTTP via ngrok setelah login:
  - `/` → `200`
  - `/admin/schedule` → `200` (board tampil; picker klinik tampil bila belum
    ada konteks klinik)
  - `/admin/payments` → `200`
  - `/admin/payments/9/receipt` → `200`, `application/pdf`, 25.259 byte
- Isolasi pencarian di klinik 1:
  - `q=MeowCare` → 0 booking / 0 hewan / 0 pemilik
  - `q=happypaws` → 0 / 0 / 0
  - `q=chr` → 5 booking / 4 hewan / 3 pemilik
- Test baru `tests/Feature/Phase3OperationalDepthTest.php`: 4 test / 20 assertion
  (picker super admin, board clinic-scoped, palette clinic-scoped, invoice PDF
  + isolasi receipt lintas klinik).
- Regression admin: `Phase4WebAdminTest` + `Phase5SuperAdminTest` → 26 test /
  170 assertion, semua lolos.
- Full suite: `php artisan test` → **121 passed (613 assertions)**, exit `0`.

Android:

- `.\gradlew.bat assembleDebug` → `BUILD SUCCESSFUL` setelah penambahan bukti
  pembayaran PNG, share image, dan copy text.
- Alur bukti Android memakai data `Booking` yang sudah dimuat layar; tidak ada
  endpoint atau skema baru. Renderer PNG memakai canvas native supaya hasil
  simpan/bagikan tidak menangkap tombol aplikasi.

## 7. Batasan dan sisa

- Unduhan PDF customer belum tersedia; bukti Android berupa PNG 1080×1760,
  share image, dan teks. PDF admin sudah berfungsi.
- Command palette membatasi 5 hasil per kategori; belum ada pagination atau
  hasil dokter/layanan. Ini disengaja agar cepat dan tidak menjadi halaman
  pencarian kedua.
- Schedule board belum drag-and-drop atau realtime; aksi memakai workflow
  booking yang sudah ada.
- Tidak ada policy layer baru. Kontrol akses memakai `admin.auth` yang sudah
  ada; isolasi data ditegakkan lewat `currentClinicId()` dan picker klinik.
- Fase 4 (membership, SaaS billing, chat/telemedicine) belum dimulai dan tetap
  menunggu keputusan produk + migration.

## 8. File yang disentuh

| Area | File |
| --- | --- |
| Slot picker | `PetHeal_Android/.../ui/components/BookingSlotPicker.kt`, `.../util/DoctorAvailability.kt` |
| Booking Android | `.../ui/screens/booking/BookingViewModel.kt`, `BookingsScreen.kt`, `.../data/repository/BookingRepository.kt` |
| Invoice Android | `.../ui/screens/booking/InvoiceCenter.kt` |
| Invoice admin | `Admin/PaymentController.php`, `routes/web.php`, `admin/payments/index.blade.php`, `show.blade.php`, `receipt_pdf.blade.php` |
| Schedule admin | `Admin/BookingController.php`, `AdminAuthController.php`, `admin/schedule/index.blade.php`, `select_clinic.blade.php`, `layouts/admin.blade.php`, `lang/{id,en}/menu.php` |
| Command palette | `Admin/DashboardController.php`, `layouts/admin.blade.php`, `routes/web.php` |
| Test | `tests/Feature/Phase3OperationalDepthTest.php` |

## 9. Next step

Fase 3 selesai. Jangan mulai Fase 4 sebelum ada keputusan produk untuk harga
membership, aturan kuota, billing SaaS, dan cakupan chat/telemedicine.
