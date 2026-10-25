# FASE â€” Portal Dokter (Web) + Verifikasi Email untuk Hapus Akun & Ganti Email

> Tanggal: 2026-10-24
> Scope: dashboard web (Laravel) dan aplikasi Android pet owner.
> Keputusan produk: portal dokter hanya di web; Android tetap untuk pemilik hewan.
> OTP memakai email (Gmail SMTP yang sudah ada) â€” tanpa SMS atau layanan berbayar.

## 1. Latar dan tujuan

Sebelum fase ini, dokter hanya berupa data praktik (`doctors`) tanpa akun login.
Admin harus mengelola booking dan rekam medis untuk dokter, dan dokter tidak bisa
mengonfirmasi kunjungan atau menulis hasil pemeriksaan sendiri.

Di sisi pemilik hewan, dua aksi berisiko tinggi berjalan tanpa pembuktian
kepemilikan akun:

1. `DELETE /api/auth/account` langsung menghapus akun, hewan, booking, dan rekam
   medis begitu tombol ditekan.
2. Email akun tidak bisa diganti sama sekali; salah ketik saat pendaftaran tidak
   punya jalur perbaikan.

Fase ini menutup keduanya dengan kode 6 digit yang dikirim ke email, memakai
`OtpService` dan `OtpMail` yang sudah dipakai pendaftaran/reset sandi.

## 2. Keputusan desain

| Topik | Keputusan | Alasan |
| --- | --- | --- |
| Portal dokter | `/doctor` di web, middleware `doctor.auth` | Android tetap satu aplikasi untuk pemilik hewan |
| Akun dokter | baris `doctors` tetap data praktik; login memakai baris `users` baru dengan `role='doctor'` dan `doctors.user_id` | rekam medis & jadwal tidak ikut terhapus saat akses dicabut |
| Pembuatan akun | admin mengirim undangan ke email dokter â†’ dokter membuka halaman aktivasi dan menentukan sandi sendiri | admin tidak pernah tahu/menyimpan sandi dokter |
| Cakupan dokter | dashboard, booking sendiri, rekam medis booking sendiri, cuti/jam khusus sendiri, profil sendiri | tidak ada akses ke pengaturan klinik, pembayaran, audit log, atau CRUD dokter |
| Hapus akun | OTP ke email akun; setelah verifikasi server membuka jendela 15 menit untuk `DELETE /auth/account` | mencegah penghapusan karena HP tidak terkunci |
| Akun tanpa data | tetap boleh dihapus langsung | menjaga alur perbaikan akun Google tanpa klinik tetap bisa dipakai |
| Ganti email | OTP dikirim ke **alamat baru**; email disimpan setelah kode diverifikasi | yang perlu dibuktikan adalah kepemilikan alamat baru |
| Endpoint lama | tidak ada yang dihapus; `DELETE /auth/account` tetap sama | kompatibilitas klien lama |

## 3. Backend

### 3.1 OTP akun

- `app/Services/OtpService.php`: tujuan baru `delete_account`, `email_change`
  (TTL 10/10 menit). Undangan dokter **tidak lagi memakai OTP** â€” lihat Â§10.
- `app/Mail/OtpMail.php` + `resources/views/emails/otp.blade.php` dan versi teks:
  subjek/isi per tujuan aksi akun.
- `app/Http/Controllers/Api/AuthController.php`:
  - `requestDeleteOtp()` / `verifyDeleteOtp()`
  - `requestEmailChangeOtp()` / `verifyEmailChangeOtp()`
  - `deleteAccount()` kini menolak `428 delete_verification_required` bila akun
    punya hewan/booking dan cache verifikasi 15 menit belum ada.
  - alamat email disamarkan (`maskEmail()`) sebelum dikirim ke aplikasi.
- `routes/api.php:85-91`: empat endpoint di bawah `auth:sanctum` dengan throttle
  `account-otp-send` (3/menit, 10/hari per user) dan `account-otp-verify`
  (10/menit per user).
- `app/Http/Middleware/ApiAuthenticate.php:54`: aksi akun sendiri melewati cek
  tenant, sehingga user tanpa klinik/suspended tetap bisa menghapus akun atau
  mengganti email. Bearer token tetap wajib.

### 3.2 Portal dokter

- Migration `2026_10_24_000001_add_user_id_to_doctors_table.php`: `user_id`
  nullable, unik, `nullOnDelete`. Relasi `Doctor::user()` dan `User::doctor()`.
- `DoctorAuth` (+ alias `doctor.auth`) dan perubahan `AdminAuth:26-33`: dokter
  yang membuka `/admin` diarahkan ke `/doctor`, admin yang membuka `/doctor`
  diarahkan ke `/admin`.
- `Doctor\AuthController`: login/logout + aktivasi undangan lewat tautan
  bertoken (membuat `users` dan menautkan `doctors.user_id` dalam satu transaksi,
  audit log). Tanpa kode OTP â€” lihat Â§10.
- Controller portal di `app/Http/Controllers/Doctor/`: dashboard, booking,
  rekam medis, jadwal, profil. Semua query dibatasi `doctor_id` milik user dan
  `clinic_id` dokter; ID milik dokter lain menghasilkan 404.
- Admin `DoctorController`: `invite()` dan `revokeAccount()` (menghapus login,
  mempertahankan profil praktik + rekam medis), serta larangan menghapus dokter
  yang masih punya akun aktif.
- View: `resources/views/doctor/**` (dashboard, booking, rekam medis, jadwal,
  profil, aktivasi) memakai komponen admin yang sama; layout admin punya menu
  khusus dokter dan menyembunyikan command palette.
- Halaman `admin/doctors/show` mendapat panel "Akun Portal Dokter" (status akun,
  tautan login, undangan, cabut akses, status masa berlaku tautan), dan daftar
  dokter menampilkan badge "Portal aktif"/"Belum ada akun".

## 4. Android

- `data/model/Models.kt`: `mailSent` pada `OtpSentResponse`, serta
  `VerifyOtpCodeRequest`, `EmailChangeOtpRequest`, `EmailChangeVerifyOtpRequest`.
- `data/remote/ApiService.kt`: empat endpoint OTP akun.
- `data/repository/AuthRepository.kt`: `requestDeleteAccountOtp()`,
  `verifyDeleteAccountOtp()`, `requestEmailChangeOtp()`, `verifyEmailChangeOtp()`
  dengan pembacaan pesan/kode error yang sama seperti alur OTP lain.
- `ui/screens/profile/ProfileViewModel.kt`: `OtpFlowState` (kirim/verifikasi,
  alamat tersamarkan, masa berlaku, status kirim email, error) dipakai untuk
  hapus akun dan ganti email. Verifikasi hapus akun langsung dilanjutkan ke
  penghapusan agar tidak ada kode terverifikasi yang menggantung.
- `ui/screens/profile/ProfileScreen.kt`:
  - dialog hapus akun menjadi tiga langkah: konsekuensi + centang â†’ ketik
    `HAPUS` â†’ kode email (`OtpInput`), dengan kirim ulang dan pesan Spam/Promosi;
  - `EditProfileScreen` menampilkan aksi "Ubah alamat email" yang membuka dialog
    dua langkah (alamat baru â†’ kode), lalu memperbarui sesi lokal dan snackbar
    sukses tanpa keluar dari layar.
- Tidak ada warna mentah baru; seluruh warna memakai token `ui/theme/Color.kt`.

## 5. Keamanan dan batas

- Kode disimpan sebagai hash, satu kode aktif per email+tujuan, maksimal 5
  percobaan, satu kali pakai, dan payload memuat `user_id` sehingga kode milik
  akun lain tidak bisa dipakai.
- Ganti email menolak alamat yang sama (`422 same_email`) dan alamat yang sudah
  dipakai (`409 email_taken`), termasuk pengecekan ulang saat verifikasi.
- Setelah ganti email, `email_verified_at` diperbarui dan token login lama tetap
  berlaku (sesi tidak diputus).
- Dokter tidak bisa mengubah email, spesialisasi, atau jadwal mingguan sendiri;
  ketiganya tetap dikelola admin.

## 6. Verifikasi

Backend:

- `php -l` bersih untuk seluruh controller/middleware/route/migration baru;
  `php artisan config:clear` dan `view:cache` sukses.
- `route:list --path=doctor` memuat 40 rute (portal + admin dokter);
  `route:list --path=api/auth` memuat empat endpoint OTP baru.
- Smoke HTTP lokal: login admin, `/admin/doctors` dan `/admin/doctors/{id}` 200,
  undangan membuat baris `otp_codes` tujuan `doctor_invite`, aktivasi membuat
  user `role=doctor` dan menautkan `doctors.user_id`, seluruh halaman `/doctor/*`
  200, dokter diarahkan keluar dari `/admin`, admin diarahkan keluar dari
  `/doctor`, booking/rekam medis milik dokter lain 404.
- Cuti dokter: simpan dan hapus pengecualian berhasil; data uji dibersihkan.
- Cabut akses: user login terhapus, baris dokter dan relasi tetap ada.
- Alur API hapus akun: `DELETE` sebelum verifikasi â†’ 428; kirim kode â†’ 200
  (email tersamarkan); kode salah â†’ 400; kode benar â†’ 200; `DELETE` â†’ 200 dan
  user + hewan terhapus.
- Alur API ganti email: kirim kode â†’ 200; email sama â†’ 422; email terpakai â†’ 409;
  verifikasi â†’ 200 dan `users.email` + `email_verified_at` diperbarui.
- `/`, `/admin/login`, `/doctor/login`, dan `/api/health` (ngrok) = 200.
- `php artisan test`: **179 passed / 1032 assertions**. Test baru
  `tests/Feature/AccountOtpFlowTest.php` (6 test / 40 assertion) mencakup
  larangan hapus tanpa kode, hapus setelah kode, hapus akun kosong, ganti email
  ke alamat baru, penolakan alamat sama/terpakai, dan kode salah yang tidak
  mengubah email. `Phase6SecurityMatrixTest` diperbarui agar menguji aturan
  portal dokter yang baru (dokter berprofil diarahkan ke `/doctor`, dokter tanpa
  profil keluar ke `/doctor/login`, panel admin tetap tertutup).
- Database test memerlukan `DB_DATABASE=pet_heal_test php artisan migrate --force`
  untuk kolom `doctors.user_id`.

Android:

- `.\gradlew.bat assembleDebug` â†’ **BUILD SUCCESSFUL**.
- Belum diuji di emulator/perangkat (`adb` tidak tersedia pada sesi ini);
  perilaku dialog diverifikasi lewat kompilasi dan pembacaan state.

## 7. Sisa

- Uji manual di perangkat: hapus akun (akun berisi data dan akun kosong),
  ganti email, kirim ulang kode, dan pesan ketika email gagal terkirim.
- Belum ada halaman riwayat aktivitas login dokter; audit log admin sudah
  mencatat undangan, aktivasi, dan pencabutan akses.
- Kode OTP tidak kedaluwarsa lebih cepat saat alamat email berubah lagi; kode
  baru untuk tujuan yang sama menimpa kode lama.

## 8. Audit isolasi antar klinik (2026-10-24)

Pertanyaan "apakah dokter tiap klinik bisa bocor data?" dijawab dengan audit
kode + test negatif dua klinik.

**Hasil audit** â€” semua akses portal memakai `DoctorPortalController::doctor()`
dan selalu memfilter `doctor_id` + `clinic_id` milik sesi:

| Area | Bukti |
| --- | --- |
| Booking (list/detail/transisi) | `DoctorBookingController.php:157-172` |
| Rekam medis (list/create/store/show/edit/update) | `DoctorMedicalRecordController.php:38-40,61-64,105-109,172-175,200-203` |
| Jadwal (list/store/destroy) | `DoctorScheduleController.php:38-40,90-94,143-146` |
| Dashboard & statistik | `DoctorDashboardController.php:25-27,52-54,73-75` |
| Service jadwal (slot, konflik) | `DoctorScheduleService.php:30-34,104-107,196-200` |
| Profil & sandi | `DoctorProfileController.php:21,31-32,73` (target selalu `Auth::user()->doctor`) |

Tidak ada `clinic_id`/`doctor_id` yang diambil dari request untuk memilih baris
data; `store()` rekam medis memvalidasi `booking_id` dengan
`Rule::exists(...)->where('doctor_id')->where('clinic_id')`
(`DoctorMedicalRecordController.php:88-93`). Layout dokter tidak memuat menu
admin (`layouts/admin.blade.php:10-13,78-125`), dan panel admin menolak role
dokter (`AdminAuth`).

**Hardening tambahan** (defense in depth, tanpa mengubah alur normal):

- `DoctorAuth.php:51-59` â€” akun dengan `clinic_id` berbeda dari baris `doctors`
  tertaut langsung di-logout dan ditolak.
- `DoctorPortalController.php:26-31` â€” cek yang sama diulang di controller agar
  route baru yang lupa middleware tetap aman.
- `DoctorAuthController.php:67-73` â€” login menolak kombinasi akunâ€“profil beda
  klinik dengan pesan yang jelas.

**Test baru** `tests/Feature/DoctorPortalIsolationTest.php` (7 test / 51
assertion, dua klinik + dua dokter + dua pemilik hewan):

1. Dashboard/list hanya memuat data klinik sendiri.
2. Booking lawan klinik: detail/confirm/complete/cancel â†’ 404, status tidak
   berubah.
3. Rekam medis lawan klinik: show/edit/create/update â†’ 404; `booking_id` asing
   ditolak validasi dan tidak membuat baris baru.
4. Pengecualian jadwal lawan klinik: delete â†’ 404 dan baris tetap ada.
5. Update profil hanya menyentuh akun/profil sendiri; `email`, `specialization`,
   `clinic_id`, `available_days`, `is_active` diabaikan.
6. Ganti sandi hanya akun sendiri (tidak ada parameter target user).
7. Tautan akunâ€“profil beda klinik ditolak di gerbang portal (`assertGuest`).

**Verifikasi akhir**: `php artisan test` **186 passed / 1083 assertions**;
`php -l` bersih; `php artisan config:clear` sukses; `/`, `/admin/login`,
`/doctor/login`, `/api/health` lokal dan ngrok semuanya 200.

## 9. Perbaikan alur undangan dokter (2026-10-24)

Temuan: email undangan sebelumnya hanya berisi 6 digit kode tanpa tombol atau
tautan. Halaman aktivasi hanya bisa dibuka dari URL yang tampil di panel admin,
sehingga dokter penerima email wajar bingung "kode ini dimasukkan ke mana?".

Alur yang sekarang (3 langkah, sama di email / panel admin / halaman aktivasi):

1. Admin membuka `/admin/doctors/{id}` â†’ **Kirim Undangan**. Email
   "Undangan akun dokter â€” {klinik}" terkirim ke alamat dokter.
2. Dokter membuka email dan mengeklik tombol **Aktivasi Akun Dokter**. Halaman
   `/doctor/activate?email=...` terbuka dengan email sudah terisi terkunci,
   lalu dokter memasukkan 6 digit kode dari email tersebut.
3. Dokter membuat kata sandi (dengan toggle tampilkan sandi) â†’ akun aktif â†’
   masuk lewat `/doctor/login`.

Perubahan:

- `app/Mail/OtpMail.php:127-168` â€” email dokter menyertakan `ctaUrl`, label
  tombol, dan hint; `app/Services/OtpService.php:126-132` meneruskan email
  penerima untuk membentuk tautan.
- `resources/views/emails/otp-admin.blade.php:65-86` + versi teks â€” tombol CTA,
  hint, dan tautan cadangan. Email admin biasa tetap tanpa CTA (opsional).
- `resources/views/doctor/auth/activate.blade.php` â€” stepper 1-2-3, email
  terkunci dari tautan + "Bukan email ini?", hint cek Spam/Promosi, autofocus
  digit pertama, toggle tampilkan kata sandi.
- `resources/views/admin/doctors/show.blade.php:152-197` â€” panduan 3 langkah,
  tautan aktivasi cadangan + tombol **Salin**, tombol undangan berfungsi sebagai
  kirim ulang.
- `app/Http/Controllers/Admin/DoctorController.php:223-229` â€” flash message
  mengarahkan dokter mengeklik tombol di email, bukan menyalin URL.

Verifikasi: `tests/Feature/DoctorInviteMailTest.php` (3 test / 9 assertion),
`php artisan test` **189 passed / 1092 assertions**, `view:cache` sukses,
`/doctor/activate?email=...` lokal & ngrok = 200.

## 10. Revisi: undangan dokter tanpa OTP (2026-10-24)

> Menggantikan alur kode OTP undangan pada Â§3.2, Â§5, dan Â§9. OTP tetap dipakai
> untuk aksi akun pet owner dan verifikasi admin, tetapi **tidak** untuk
> undangan dokter.

**Alasan**: dokter yang sudah mengeklik tautan aktivasi masih diminta menyalin
6 digit kode dari email ke halaman yang sama. Itu langkah ganda tanpa manfaat â€”
tautan email sudah membuktikan kepemilikan alamat email.

**Alur baru**:

1. Admin membuka `/admin/doctors/{id}` â†’ **Kirim Undangan**. Server membuat
   token acak 64 karakter, menyimpan **hash SHA-256** di
   `doctors.invite_token` + masa berlaku 60 menit, lalu mengirim email
   `DoctorInviteMail` berisi tombol **Buat Kata Sandi Portal Dokter**.
2. Dokter mengeklik tombol â†’ `/doctor/activate/{token}` menampilkan email
   terkunci (dari profil dokter) dan form kata sandi. Tidak ada input kode.
3. Submit â†’ akun `users` (`role=doctor`, `clinic_id` sama) dibuat, `user_id`
   ditautkan, token dihapus dalam transaksi yang sama, lalu dokter diarahkan ke
   layar sukses `/doctor/welcome` (sekali tampil) dan menekan **Buka Dashboard
   Dokter** untuk masuk.

**Keamanan**:

- Token asli hanya ada di email; database menyimpan hash. Tidak bisa dipakai
  ulang (dihapus saat aktivasi) dan kedaluwarsa 60 menit.
- Kirim ulang undangan merotasi token; tautan lama langsung mati.
- Penghapusan/akses dicabut juga mengosongkan token.
- Aktivasi menolak email yang sudah dipakai akun lain dan klinik nonaktif.
- `/doctor/activate` tanpa token hanya menampilkan panduan (bukan form), jadi
  email tidak bisa ditebak lewat `?email=`.

**File**:

- Migration `2026_10_24_000002_add_invite_token_to_doctors_table.php`
  (`invite_token` unik nullable + `invite_token_expires_at`).
- `app/Mail/DoctorInviteMail.php` + `resources/views/emails/doctor-invite.blade.php`
  dan versi teks â€” email khusus undangan tanpa kode.
- `app/Http/Controllers/Admin/DoctorController.php` â€” `invite()` membuat token,
  mengirim email, mencatat audit; `revokeAccount()` mengosongkan token.
- `app/Http/Controllers/Doctor/DoctorAuthController.php` â€” `showActivationNotice()`
  (`/doctor/activate`), `showActivationForm()` + `activate()` (`/activate/{token}`),
  `welcome()` (layar sukses), helper `findInvitedDoctor()` dengan row lock.
- `routes/web.php` â€” rute `doctor.activate.token` (GET) dan `doctor.activate.post`
  (POST `/activate/{token}`, throttle `otp-verify`), plus `doctor.welcome`
  (GET `/doctor/welcome`, di dalam middleware `doctor.auth`).
- `resources/views/doctor/auth/activate.blade.php` (form sandi saja),
  `activate-notice.blade.php` (panduan bila tautan tidak ada), dan
  `welcome.blade.php` (konfirmasi aktivasi + CTA dashboard).
- `resources/views/admin/doctors/show.blade.php` â€” panduan 2 langkah + status
  "Tautan undangan aktif sampai â€¦" + tombol kirim ulang.
- OTP `doctor_invite` dihapus dari `OtpService`/`OtpMail` (tidak ada kode mati).

**Verifikasi**: `tests/Feature/DoctorInviteMailTest.php` **9 test / 82 assertion**
(email tanpa OTP + URL aktivasi; aktivasi hanya dengan sandi; layar sukses +
flash sekali pakai; tautan sekali pakai; tautan kedaluwarsa ditolak; konfirmasi
sandi wajib cocok; kirim ulang merotasi token; ganti email dokter membatalkan
tautan lama; halaman panduan/login; undangan ter-scope klinik). `php artisan test`
**195 passed / 1165 assertions**; `view:cache` sukses; `/doctor/activate` 200,
`/doctor/activate/{token palsu}` 302, `/doctor/welcome` tanpa sesi 302 ke login
(lokal & ngrok); migration dijalankan di `pet_heal` dan `pet_heal_test`.

**Catatan build**: tombol aktivasi sempat tampak kosong karena `public/build`
masih hasil build 22 Oktober yang belum memuat kelas `bg-emerald-950`,
`hover:bg-emerald-900`, `active:scale-[0.99]`, dan `shadow-emerald-950/20`.
Setelah `npm run build` (`app-EkV3V1n0.css`, 117,19 kB) tombol tampil normal.
**Selalu jalankan `npm run build` lalu `php artisan view:cache` setelah mengubah
kelas Tailwind di Blade**, karena aset produksi tidak ikut ter-update otomatis.

**Revisi visual**: `activate.blade.php`, `welcome.blade.php`, dan
`activate-notice.blade.php` ditulis ulang mengikuti bahasa desain halaman
login (satu keluarga produk): latar krem `#F4F1ED`, panel/aksen hijau hutan
`#1B2A1E`, ikon SVG inline, komponen `eyebrow`/`btn-submit`/`error-box` yang
sama. Sisi kiri form aktivasi kini memakai stepper 3 langkah (buka tautan â†’
sandi â†’ dashboard); layar sukses memakai satu kartu dengan ringkasan akun dan
pintasan dashboard. Gradien emerald-teal, blur orb, dan kartu kartunis yang
gaya-nya beda dari produk telah dihapus. Salinan kunci (label tombol, judul)
tidak berubah agar test tetap konsisten.

**Revisi kedua (2026-10-25)**: layar sukses yang terpotong di viewport pendek
diperbaiki dengan `margin:auto` pada kartu (bukan center-terpotong) + kartu
dipadatkan + media query `max-height:720px`. Tambah halaman dokter ikut
dipolish: `doctor/schedule/index.blade.php` (papan jadwal mingguan 7 hari,
radio mode dengan kartu terpilih, grup jam ter-disable saat cuti penuh) dan
`doctor/profile/edit.blade.php` (identitas bertata letak, chip hari praktik,
pratinjau foto langsung). Build: `app-D_NZ1Ylg.css`. Verifikasi:
`DoctorInviteMailTest` + `DoctorPortalIsolationTest` 16 test / 133 assertion;
`php artisan test` **195 passed / 1165 assertions**; smoke 200 lokal & ngrok.




## Revisi ketiga (2026-10-25) - Ganti kata sandi dokter via kode email

Keputusan: dokter yang benar-benar lupa sandi tidak mungkin mengisi "kata
sandi saat ini", jadi kolom itu dihapus dan diganti verifikasi kepemilikan
email.

- Tujuan OTP baru `OtpService::PURPOSE_DOCTOR_PASSWORD` (`doctor_password`,
  TTL 10 menit) - terpisah dari `admin_password` agar kode tidak bisa dipakai
  silang. `isDashboardPurpose()` mencakupnya sehingga `OtpMail` memakai
  template staf dengan label "Portal Dokter" / "Kode verifikasi" / peran
  "dokter".
- Alur dua langkah di `DoctorProfileController`:
  1. `sendPasswordOtp` - validasi sandi baru (min 8 + konfirmasi), simpan
     **hash** di sesi (`doctor_password_pending`, terikat `user_id`,
     kedaluwarsa mengikuti TTL), lalu kirim kode ke email akun. Endpoint yang
     sama dipakai untuk kirim ulang tanpa mengisi ulang sandi.
  2. `updatePassword` - hanya menerima 6 digit kode, verifikasi lewat
     `OtpService`, lalu menyimpan hash yang sudah disiapkan. Kode di-consume,
     sesi di-regenerate, audit log `doctor.password.change`.
- `cancelPasswordOtp` menghapus kode + state sesi.
- Route: `POST /doctor/profile/password/otp` (`throttle:doctor-otp-send`),
  `POST /doctor/profile/password/otp/cancel`,
  `PUT /doctor/profile/password` (`throttle:doctor-password-verify`).
- UI `doctor/profile/edit.blade.php`: langkah 1 = sandi baru + konfirmasi +
  penjelasan "sandi saat ini tidak perlu"; langkah 2 = banner email/tenggat,
  6 kotak kode (auto-pindah, backspace, tempel), tombol simpan, kirim ulang
  dengan hitung mundur, dan batalkan.
- Verifikasi: `php -l` file terkait, `view:cache` + lint compiled, render
  `OtpMail` dokter OK, `npm run build` (`app-DdWd86Ev.css`),
  `DoctorPasswordOtpTest` 6 test / 55 assertion,
  `DoctorPortalIsolationTest` 8 test / 61 assertion, suite
  **202 passed / 1230 assertions**.
