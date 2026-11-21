# Audit Kode OTP & Verifikasi Email — PetHeal (Web + Android)

**Tanggal audit:** 21 November 2026
**Ruang lingkup:** semua alur yang mengirim **kode OTP** atau **tautan verifikasi** ke alamat email
nyata (termasuk Gmail), di dashboard web, portal dokter, API aplikasi Android, dan aplikasi Android itu sendiri.
**Pemicu:** laporan user — "kode OTP tidak muncul".

Dokumen ini bukan daftar dugaan. Setiap butir punya bukti berupa `path:line`, keluaran perintah,
atau baris log yang benar-benar ada di mesin ini.

---

## 1. Jawaban singkat: kenapa kode OTP tidak muncul

Ada **dua lapis** penyebab, dan keduanya perlu dibedakan supaya tidak salah perbaikan.

**Lapis 1 — riwayat (sudah lewat).** Sampai 16 November 2026 server benar-benar gagal mengirim email
karena Gmail menolak kredensial SMTP. Bukti persisnya:

```
storage/logs/laravel.log:11912  535-5.7.8 Username and Password not accepted ... petheal.klinik@gmail.com
storage/logs/laravel.log:23596  535-5.7.8 Username and Password not accepted
storage/logs/laravel.log:30320  Connection could not be established with host "smtp.gmail.com:587"
```

Pesan itu berasal dari `OtpService::send()` (`app/Services/OtpService.php:122`) yang menangkap
exception lalu hanya menulis `Log::warning`. Artinya: kode **terbit** di database, email **tidak keluar**,
dan sebagian layar tetap berkata "kode sudah dikirim".

**Lapis 2 — kondisi hari ini (sudah normal di sisi server).** Saya uji ulang jalur email sekarang:

| Uji | Perintah | Hasil |
|---|---|---|
| Koneksi + TLS + AUTH Gmail (tanpa mengirim email) | probe SMTP manual | `STARTTLS: 220`, `AUTH LOGIN` → **`235 2.7.0 Accepted`** |
| Kirim email lewat Laravel (mailable asli) | `Mail::to(...)->send(new OtpMail(...))` | **diterima transport, tanpa exception** |
| Perintah diagnosa baru | `php artisan petheal:mail-test <email>` | **BERHASIL: server email menerima dan mengantar pesan** |
| Apakah kode benar-benar terbit saat user melapor? | `php artisan petheal:otp-status christopher.aldo1@smk.belajar.id` | **1 baris, status AKTIF**, terbit 21 Nov 2026 09:43, percobaan salah 0/5 |

Kesimpulan yang bisa dipertanggungjawabkan: **permintaan OTP user sampai ke server, kode terbit,
dan SMTP sekarang menerima pengiriman.** Jadi sisa penyebab yang mungkin, berurutan:

1. **Email masuk folder Spam / Promosi / "Semua email"** — paling sering untuk pengirim baru.
2. **Domain penerima menahan email dari luar.** Alamat yang dipakai user berdomain
   `smk.belajar.id` (Google Workspace sekolah). Kebijakan Workspace sekolah sangat sering
   mengarantina email dari domain luar sebelum masuk inbox.
3. **Kode kedaluwarsa sebelum dipakai.** Masa berlaku hanya 10 menit untuk pendaftaran
   (`app/Services/OtpService.php:50-58`).
4. **Kode lama batal karena "kirim ulang".** Setiap permintaan baru menimpa kode sebelumnya
   (`app/Services/OtpService.php:100` — `updateOrInsert`), jadi kode di email pertama menjadi salah.

**Lapis 3 — temuan susulan dari uji nyata di inbox user (sudah diperbaiki).** Setelah laporan
pertama, user melaporkan hal yang sangat spesifik: **email uji netral masuk, tetapi email OTP dan email
undangan dokter tidak** — pengirim dan jalur sama. Dua perbaikan dikerjakan berurutan:

1. Kode dihapus dari teks pratinjau tersembunyi kedua template OTP (BK-17). Ini memang pola phishing,
   tetapi ternyata **bukan** pemicu utamanya.
2. Uji A/B terarah (satu variabel per email) membuktikan pemicunya **subjek**:

   | Uji | Subjek | Isi | Hasil |
   |---|---|---|---|
   | E1 | `Kode OTP pendaftaran PetHeal` | isi netral tanpa kode | **tidak masuk** |
   | E2 | `Uji pengiriman email PetHeal` | template OTP asli (kode `000000`) | **masuk** |

   Jadi filter Gmail/Workspace penerima menahan **subjek bermuatan "Kode OTP"**, bukan isi email.
   Seluruh subjek sekarang berpola `PetHeal: <tindakan>` tanpa kata "OTP" (BK-13).

**Cara memastikan sampai atau tidak, tanpa menebak:** jalankan `petheal:mail-test` untuk tiga template
(`check`, `otp`, `doctor`). Kalau `check` masuk tetapi `otp` tidak, masalahnya 100% di template/filter,
bukan di SMTP. Langkah lengkapnya di §7.

---

## 2. Peta lengkap alur OTP & verifikasi email

Ada **tujuh tujuan (purpose)** kode, semuanya lewat satu service yang sama
(`app/Services/OtpService.php`) dan satu mailable (`app/Mail/OtpMail.php`).

Pemisahan `purpose` bukan hiasan: kode untuk satu tujuan **tidak bisa** dipakai di tujuan lain,
sehingga kode "ganti kata sandi admin" tidak bisa dipakai di aplikasi pet owner, dan sebaliknya.

| # | Purpose | Konstanta | TTL | Dipakai di |
|---|---|---|---|---|
| 1 | Pendaftaran pet owner | `PURPOSE_REGISTER` (`:22`) | 10 mnt | Android |
| 2 | Reset kata sandi pet owner | `PURPOSE_RESET` (`:23`) | 15 mnt | Android |
| 3 | Hapus akun | `PURPOSE_DELETE_ACCOUNT` (`:29`) | 10 mnt | Android |
| 4 | Ganti email | `PURPOSE_EMAIL_CHANGE` (`:30`) | 10 mnt | Android |
| 5 | Pendaftaran admin klinik | `PURPOSE_ADMIN_REGISTER` (`:37`) | 10 mnt | Web |
| 6 | Ganti kata sandi admin | `PURPOSE_ADMIN_PASSWORD` (`:38`) | 10 mnt | Web |
| 7 | Ganti kata sandi dokter | `PURPOSE_DOCTOR_PASSWORD` (`:44`) | 10 mnt | Portal dokter |

### 2.1 Web — dashboard admin

| Alur | Route | Controller | Kirim email |
|---|---|---|---|
| Daftar admin klinik, langkah 1 (kirim kode) | `routes/web.php:54` `POST /admin/register` | `AdminAuthController::requestJoinOtp` (`:131`) | `:219` terbit, `:220` kirim |
| Daftar admin, langkah 2 (verifikasi kode) | `routes/web.php:55` `POST /admin/register/verifikasi` | `AdminAuthController::verifyJoinOtp` (`:240`) | — |
| Daftar admin, kirim ulang | `routes/web.php:56` `POST /admin/register/kirim-ulang` | `AdminAuthController::resendJoinOtp` (`:458`) | `:481` terbit, `:482` kirim |
| Batalkan permintaan | `AdminAuthController::cancelJoinOtp` (`:498`) | kode di-`consume` (`:503`) | — |
| Ganti kata sandi admin (kirim kode) | `routes/web.php:98` `POST /admin/settings/password/otp` | `AdminAuthController::sendPasswordOtp` (`:877`, panggil `:887` via `PasswordOtpFlow`) | `app/Services/PasswordOtpFlow.php:42` |
| Ganti kata sandi admin (simpan) | `routes/web.php:100` `POST /admin/settings/password` | `AdminAuthController::updatePassword` | — |
| Batalkan ganti sandi admin | `routes/web.php:99` | `cancelPasswordOtp` | — |

Catatan penting: pesan sukses/gagal sudah jujur. Kalau email gagal, yang tampil adalah **warning**,
bukan "berhasil" (`AdminAuthController.php:231` dan `:488`).

### 2.2 Web — portal dokter

| Alur | Route | Controller | Kirim email |
|---|---|---|---|
| Ganti kata sandi dokter (kirim kode) | `routes/web.php:351` `POST /doctor/profile/password/otp` | `DoctorProfileController::sendPasswordOtp` (`:112`) | `PasswordOtpFlow::start` |
| Gagal kirim → hentikan alur | — | `DoctorProfileController.php:118` `if (!$result['sent'])` → flash `error` | — |
| Simpan sandi baru | `routes/web.php:355` `PUT /doctor/profile/password` | `DoctorProfileController::updatePassword` | — |
| Batalkan | `routes/web.php:353` | `cancelPasswordOtp` | — |

### 2.3 API — dipakai aplikasi Android

| Alur | Route | Controller | Kirim email |
|---|---|---|---|
| Daftar, minta kode | `routes/api.php:45` `POST /api/auth/register/request-otp` | `Api\AuthController::requestRegisterOtp` (`:215`) | `:250` terbit, `:251` kirim, `:252` `$mailSent` |
| Daftar, kirim ulang | `routes/api.php:46` | `resendRegisterOtp` (`:270`) | `:285` terbit, `:286` kirim |
| Daftar, verifikasi → akun dibuat | `routes/api.php:50` | `verifyRegisterOtp` (`:307`) | — |
| Lupa sandi, minta kode | `routes/api.php:31` | `forgotPassword` (`:650`) | `:659` terbit, `:660` kirim (**hasil kirim sengaja diabaikan**, lihat BK-05) |
| Lupa sandi, cek kode | `routes/api.php:39` | `verifyResetCode` (`:673`) | — |
| Lupa sandi, simpan sandi baru | `routes/api.php:40` | `resetPassword` | — |
| Hapus akun, minta kode | `routes/api.php:97` | `requestDeleteOtp` (`:823`) | `:835` terbit, `:838` kirim |
| Ganti email, minta kode | `routes/api.php:98` | `requestEmailChangeOtp` | idem |
| Verifikasi hapus akun / ganti email | `routes/api.php:102-103` | `verifyDeleteOtp`, `verifyEmailChangeOtp` | — |

### 2.4 Aplikasi Android — layar yang terlibat

| Layar | File | Perilaku OTP |
|---|---|---|
| Form daftar | `ui/screens/auth/RegisterScreen.kt:64`, `:134-137` | setelah kode diminta, pindah ke layar verifikasi |
| ViewModel daftar | `ui/screens/auth/RegisterViewModel.kt:42-70` | panggil `requestRegisterOtp` |
| Layar verifikasi kode | `ui/screens/auth/OtpVerificationScreen.kt:71-100` | input 6 digit, hitung mundur kirim ulang |
| ViewModel verifikasi | `ui/screens/auth/OtpVerificationViewModel.kt:45-105` | `verify()` + `resend()` + hitung mundur |
| Lembar lupa sandi | `ui/screens/auth/ResetPasswordSheet.kt:143-205` | email → kode → sandi baru, ada kirim ulang |
| Hapus akun | `ui/screens/profile/ProfileScreen.kt:344-360`, `ProfileViewModel.kt:190-203` | sudah menampilkan peringatan bila email gagal |
| Ganti email | `ui/screens/profile/ProfileScreen.kt:1100-1125`, `ProfileViewModel.kt:274-292` | idem |
| Repository | `data/repository/AuthRepository.kt:302-338` (daftar), `:475-520` (akun), `:689-725` (reset) | pemanggil endpoint |
| Model respons | `data/model/Models.kt:115-122` | `OtpSentResponse.mailSent` (`mail_sent`) |

**Kunci desain yang sudah benar:** layar daftar memakai `otp_spam_hint`
(`app/src/main/res/values/strings.xml:33`) — "Tidak menemukannya? Cek folder Spam atau Promosi,
lalu kirim ulang." Jadi masalahnya bukan aplikasi tidak memberi petunjuk, melainkan
(email tidak pernah keluar / email ditahan penerima).

### 2.5 Verifikasi email yang bukan OTP (tetap satu keluarga)

| Alur | Pengirim | Bukti |
|---|---|---|
| Undangan aktivasi dokter (tautan token 60 menit) | `Admin\DoctorController::invite` → `Mail::to()->send(new DoctorInviteMail(...))` | `routes/web.php:152`, `app/Http/Controllers/Admin/DoctorController.php:240-244` |
| Pengajuan klinik disetujui | `ClinicRegistrationApprovedMail` | `app/Mail/ClinicRegistrationApprovedMail.php` |
| Pengajuan klinik ditolak | `ClinicRegistrationRejectedMail` | `app/Mail/ClinicRegistrationRejectedMail.php` |
| Notifikasi permintaan admin baru ke super admin | `AdminAuthController.php:769` `Mail::to($joinRequest->email)->send($mailable)` | — |

Semua pengiriman ini **sinkron** (`Mail::send`, bukan `Mail::queue`). Jadi `QUEUE_CONNECTION=database`
di `.env` **tidak** menahan email OTP: tidak perlu `queue:work` agar OTP terkirim.
(Ini penting karena tanpa penjelasan ini orang mudah menyimpulkan "antrean tidak jalan".)

### 2.6 Verifikasi dokter (undangan portal) — audit terpisah

Dokter **tidak** memakai kode OTP; verifikasinya berupa tautan sekali pakai. Karena itu masalahnya
bisa berbeda dari OTP, dan diperiksa sendiri di sini.

**Alur lengkap:**

1. Admin membuka data dokter → tombol undang → `POST admin/doctors/{id}/invite`
   (`routes/web.php:152`).
2. `DoctorController::invite()` membuat token acak 64 karakter, menyimpan **hash SHA-256**-nya di
   `doctors.invite_token` + masa berlaku 60 menit (`app/Http/Controllers/Admin/DoctorController.php:234-238`).
   Undangan baru otomatis membatalkan tautan lama.
3. Tautan dibuat dengan `route('doctor.activate.token')` (`:240`) lalu dikirim lewat `DoctorInviteMail` (`:243`).
4. Dokter membuka `/doctor/activate/{token}` → `DoctorAuthController::showActivationForm`
   (`app/Http/Controllers/Doctor/DoctorAuthController.php:245-251`).
5. Dokter mengisi kata sandi → `activate()` membuat akun `doctor`, mengosongkan token (sekali pakai),
   lalu login otomatis (`DoctorAuthController.php:140-200`).

**Titik rawan yang diperiksa:**

| Titik rawan | Status | Bukti |
|---|---|---|
| Email gagal terkirim tetapi UI mengaku sukses | Aman — gagal = warning + `Log::warning` | `DoctorController.php:243-259` |
| Template email undangan menyembunyikan kode | Tidak berlaku — template tidak memuat kode OTP, hanya tautan | `resources/views/emails/doctor-invite.blade.php:19-21` |
| Tautan kedaluwarsa karena email tertahan di Spam > 60 menit | Risiko nyata; solusi: kirim ulang undangan (token lama batal) | `DoctorController.php:234-238` |
| Tautan menunjuk `127.0.0.1` bila undangan dikirim lewat CLI | Risiko pemakaian (BK-16) | `DoctorController.php:240` |
| Email dokter sama dengan akun pet owner | **Gagal permanen** (BK-15) | `DoctorAuthController.php:160` |
| Klinik dinonaktifkan setelah undangan terkirim | Aktivasi ditolak dengan pesan jelas | `DoctorAuthController.php:149-151` |
| Dua submit bersamaan membuat dua akun | Dicegah `lockForUpdate` dalam transaksi | `DoctorAuthController.php:245-251` |
| Token bisa dipakai dua kali | Tidak — token dikosongkan saat aktivasi | `DoctorAuthController.php:172-176` |

**Cara membuktikan email undangan sampai:**

```bash
php artisan petheal:mail-test alamat-dokter@gmail.com --template=doctor
```

Email akan berisi tombol "Buat Kata Sandi Portal Dokter" dengan tautan contoh yang **sengaja tidak
valid** (token acak yang tidak ada di database), jadi jangan diklik untuk aktivasi sungguhan —
tujuannya hanya membuktikan template dan jalur pengirimannya bekerja.

---

## 3. Anatomi teknis kode OTP

| Aspek | Implementasi | Bukti |
|---|---|---|
| Tabel | `otp_codes` — `email`, `purpose`, `code_hash`, `payload`, `attempts`, `expires_at`, `consumed_at` | `database/migrations/2026_10_19_000001_create_otp_codes_table.php:11-27` |
| Unik | satu kode aktif per `email`+`purpose` (`unique(['email','purpose'])`) | migration `:26` |
| Penyimpanan kode | hash (`Hash::make`), bukan plaintext | `OtpService.php:100` |
| Penerbitan | `random_int(0,999999)` + padding 6 digit | `OtpService.php:94-96` |
| Kode baru menimpa kode lama | `updateOrInsert` | `OtpService.php:100` |
| Verifikasi | cek ada → belum dipakai → belum kedaluwarsa → attempts < 5 → `Hash::check` | `OtpService.php:162-203` (hash di `:187`) |
| Percobaan salah | `attempts` dinaikkan, pesan menyebut sisa percobaan | `OtpService.php:188-198` |
| Habis pakai | baris ditandai `consumed_at` (tidak dihapus, supaya payload masih bisa dipakai kirim ulang) | `OtpService.php:229` |
| Normalisasi email | trim + lowercase | `OtpService.php:237` |
| Pembersihan | baris kedaluwarsa dipangkas saat terbit kode baru | `OtpService.php:259` |
| Email | satu template untuk pet owner + satu untuk staf dashboard; ada versi teks | `app/Mail/OtpMail.php:36-46`, `resources/views/emails/otp.blade.php`, `emails/otp-text.blade.php` |
| Anti-spam (template) | tanpa gambar remote, tanpa tautan | `app/Mail/OtpMail.php:22-24` |

**Batas laju (throttle)** — dicek di `app/Providers/AppServiceProvider.php`:

| Limiter | Batas | Bukti |
|---|---|---|
| `otp-send` (registrasi app) | 3/menit + 20/hari per email+IP | `AppServiceProvider.php:102-109` |
| `otp-verify` | 10/menit per email+IP | `:111-117` |
| `password-reset` | 3/menit per email+IP | `:87-89` |
| `password-reset-verify` | 10/menit | `:94-96` |
| `admin-otp-send` / `doctor-otp-send` / `account-otp-send` | per akun login | `:119`, `:136`, `:152` |

Konsekuensi yang sering mengejutkan user: menekan "kirim ulang" 3× dalam semenit akan
langsung kena batas dan kode ke-4 tidak dikirim. Aplikasi menampilkan pesan dari server,
tetapi user bisa mengira "tidak ada email".

---

## 4. Jalur pengiriman email (SMTP) dan deliverability

Konfigurasi aktif (`php artisan petheal:mail-test` menampilkan ini apa adanya, sandi disamarkan):

```
mailer      : smtp
host        : smtp.gmail.com:587
scheme      : smtp
username    : p***@gmail.com
password    : terisi (16 karakter App Password setelah spasi dibuang)
pengirim    : petheal.klinik@gmail.com
APP_URL     : http://127.0.0.1:8000
```

Fakta pendukung:

- `.env` berisi `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`,
  `MAIL_FROM_ADDRESS="petheal.klinik@gmail.com"` dan `MAIL_FROM_NAME="PetHeal Clinic"`.
- App Password Gmail berformat benar: 16 karakter alfanumerik setelah spasi dibuang.
- Karena pengirim adalah alamat `@gmail.com`, tanda tangan **SPF dan DKIM otomatis valid**
  (ditandatangani Google). Ini kondisi terbaik untuk masuk inbox.
- Tidak ada gambar remote dan tidak ada tautan di email OTP → dua pemicu spam paling umum
  sudah dihindari.

Dua hal yang perlu diketahui dan sudah ditangani:

1. **`MAIL_ENCRYPTION` tidak dibaca Laravel 12.** `config/mail.php` versi ini hanya punya kunci
   `scheme` (`config/mail.php:40-50`); kunci `encryption` sudah tidak ada. Selama ini aman hanya
   karena port 587 + STARTTLS dinegosiasikan otomatis oleh transport Symfony. Sudah ditambahkan
   `MAIL_SCHEME=smtp` di `.env` dan `.env.example` agar niatnya eksplisit (BK-02).
2. **`APP_URL` masih `127.0.0.1`.** Untuk OTP ini tidak berpengaruh (email tidak berisi tautan),
   tetapi untuk **undangan dokter** tautan dibuat dengan `route('doctor.activate.token')`
   (`app/Http/Controllers/Admin/DoctorController.php:240`). Saat dibuat lewat browser, host
   mengikuti permintaan (domain ngrok) sehingga tautannya benar; saat dibuat lewat CLI/tinker,
   tautan memakai `APP_URL` dan akan menunjuk `127.0.0.1`. Aturan praktisnya: kirim undangan
   dokter dari browser, bukan dari tinker.

### 4.1 Ngrok vs localhost — apa yang benar-benar terpengaruh

Pertanyaan yang muncul saat troubleshooting: "dulu pakai ngrok aman, sekarang jadi localhost — apakah itu
penyebab OTP tidak terkirim?". Jawabannya **tidak**, dan ini alasannya (semua bisa diperiksa ulang):

| Hal | Dipengaruhi host/URL? | Bukti |
|---|---|---|
| Pengiriman email OTP | **Tidak.** Email dikirim dari proses PHP ke `smtp.gmail.com:587`; host permintaan tidak pernah ikut | `app/Services/OtpService.php:122-140`, `config/mail.php:40-50` |
| Kode terbit atau tidak | Tidak — bergantung request sampai ke server atau tidak | `petheal:otp-status` |
| Aplikasi Android bisa menghubungi backend | **Ya.** Kalau `BACKEND_BASE_URL` mati/salah, permintaan tidak sampai, jadi tidak ada kode maupun email | `PetHeal_Android/local.properties:21` |
| Tautan undangan dokter di email | **Ya, tapi hanya saat dibuat dari CLI** (lihat BK-16) | `DoctorController.php:240` |
| CSS/JS dashboard saat dibuka lewat ngrok | **Ya.** `ASSET_URL` masih `http://127.0.0.1:8000`, jadi browser mengambil aset dari localhost perangkatnya sendiri → halaman tampil tanpa gaya | `.env:45`, `layouts/admin.blade.php:38` |
| Debug/HTTPS paksa pada host tunnel | Ya, ditangani otomatis | `AppServiceProvider.php:37-39`, `:52-56` |

**Bukti kondisi saat audit ini:** tunnel hidup dan meneruskan ke `http://localhost:8000`
(`ngrok http 8000` → `https://envious-reselect-darn.ngrok-free.dev`, `/api/health` = HTTP 200 dalam 0,57 s),
dan `local.properties` masih memakai URL ngrok tersebut (berkasnya tidak berubah sejak 4 Juni 2026).
Artinya tidak ada yang "pindah ke localhost" di jalur aplikasi → server.

**Yang justru perlu dibereskan:** `APP_URL`/`ASSET_URL` disamakan dengan domain tunnel saat demo
lewat ngrok, karena itulah bagian yang benar-benar rusak kalau masih localhost:

```bash
php artisan petheal:tunnel-url --dry-run   # lihat perubahan
php artisan petheal:tunnel-url             # tulis APP_URL + ASSET_URL, lalu config:clear
```

Perintah itu hanya mengubah dua baris URL (kredensial tidak disentuh) dan ikut membandingkan
`BACKEND_BASE_URL` Android supaya ketahuan kalau aplikasi menunjuk tunnel yang berbeda.

---

## 5. Temuan audit (BK-01 … BK-22)

Severity: **Tinggi** = membuat user tidak bisa menyelesaikan alur, **Sedang** = menyesatkan /
menyulitkan, **Rendah** = kebersihan.

### BK-01 — Kredensial SMTP pernah dicabut (Tinggi, sudah beres di sisi kredensial)
**Bukti:** `storage/logs/laravel.log:11912`, `:23596` (`535-5.7.8 Username and Password not accepted`),
`:30320` (`getaddrinfo for smtp.gmail.com failed`).
**Dampak:** tidak ada email keluar sama sekali; kode tetap terbit sehingga user menunggu kosong.
**Status:** kredensial sekarang **diterima** (`235 Accepted`, lihat §1). Tidak ada perubahan kode
yang bisa memperbaiki ini — perbaikannya ada di Google Account (App Password) dan sudah terjadi.
**Pencegahan yang ditambahkan:** `php artisan petheal:mail-test` beserta pesan pemulihan yang
menyebut arti kode 535 (BK-06).

### BK-02 — `MAIL_ENCRYPTION` adalah kunci mati di Laravel 12 (Sedang)
**Bukti:** `config/mail.php:40-50` tidak punya kunci `encryption`; `.env` tetap mengisinya.
**Dampak:** konfigurasi terlihat "TLS aktif" padahal yang dipakai adalah STARTTLS otomatis.
Kalau suatu saat port diubah atau mailer diganti, tidak ada yang memberi peringatan.
**Perbaikan:** `MAIL_SCHEME=smtp` ditambahkan di `.env` (baris 35-36) dan `.env.example` (91-92),
plus `petheal:mail-test` sekarang mencetak `scheme` sehingga terbaca di layar.

### BK-03 — Android menyembunyikan `mail_sent = false` pada pendaftaran (Tinggi → sudah diperbaiki)
**Bukti sebelum:** `data/repository/AuthRepository.kt:309-313` hanya mengembalikan `data`,
padahal backend mengirim `mail_sent` (`app/Http/Controllers/Api/AuthController.php:258`).
Akibatnya `RegisterViewModel.kt:45-50` selalu berpindah ke layar OTP — user diminta menunggu
email yang sudah pasti tidak datang.
**Sesudah:** repository mengembalikan `OtpSentResponse` utuh; `RegisterViewModel` menahan user
di form dan menampilkan pesan server bila `mailSent == false`.
**Uji:** `tests/Feature/OtpMailDeliveryTest.php::test_register_otp_endpoint_reports_when_the_email_could_not_be_sent`.

### BK-04 — Android menyembunyikan `mail_sent = false` pada "kirim ulang" (Tinggi → sudah diperbaiki)
**Bukti sebelum:** `AuthRepository.resendRegisterOtp` (`:323-334`) mengembalikan `Unit`,
`OtpVerificationViewModel.kt:76-92` selalu memasang `resendSent = true` → snackbar
"Kode baru sudah dikirim" walau email gagal.
**Sesudah:** hasil kirim dibawa ke ViewModel; bila `mail_sent = false` yang muncul adalah pesan
gagal, bukan pesan berhasil.

### BK-05 — `forgot-password` sengaja mengabaikan hasil kirim (Sedang, keputusan sadar)
**Bukti:** `app/Http/Controllers/Api/AuthController.php:657-665` — `$sent` tidak dipakai, respons
selalu sukses dengan pesan generik.
**Alasan dipertahankan:** ini perlindungan **anti-enumeration**. Kalau server menjawab
"email gagal dikirim" hanya untuk akun yang ada, siapa pun bisa memakai endpoint ini untuk
menebak alamat mana yang terdaftar di PetHeal. Untuk aplikasi kesehatan, kebocoran itu
lebih mahal daripada ketidaknyamanan sesekali.
**Kompensasi:** kegagalan tetap tercatat (`OtpService.php:135` → `Log::warning`), layar reset
punya hitung mundur + tombol kirim ulang (`ResetPasswordSheet.kt:163-176`), dan tim bisa
memastikan jalur email sehat dengan `petheal:mail-test` sebelum demo.

### BK-06 — Tidak ada alat diagnosa email (Sedang → sudah ditambahkan)
**Bukti sebelumnya:** satu-satunya cara tahu email gagal adalah membaca `storage/logs/laravel.log`.
**Perbaikan (baru):**
- `app/Console/Commands/TestMailDelivery.php` → `php artisan petheal:mail-test <email>`
  mengirim email uji nyata (tanpa kode), mencetak konfigurasi aktif dengan sandi disamarkan,
  dan menerjemahkan error SMTP menjadi langkah perbaikan.
- `app/Console/Commands/OtpStatus.php` → `php artisan petheal:otp-status <email>`
  memisahkan dua kemungkinan: "permintaan tidak sampai" vs "email tidak diterima",
  dengan menampilkan status kode (AKTIF / kedaluwarsa / sudah dipakai), sisa waktu, dan
  jumlah percobaan salah.
- Template email uji: `app/Mail/MailDeliveryCheck.php`, `resources/views/emails/mail-check.blade.php`,
  `emails/mail-check-text.blade.php` (gaya visual sama dengan email OTP, tegas menyatakan
  tidak berisi kode).
- Uji: `tests/Feature/OtpMailDeliveryTest.php` (7 tes, 21 asersi).

### BK-07 — "Kirim ulang" membatalkan kode di email sebelumnya (Sedang, by design, perlu diketahui)
**Bukti:** `OtpService::issue()` memakai `updateOrInsert` (`OtpService.php:100`) dengan indeks unik
`(email, purpose)` (migration `:26`).
**Dampak:** user yang menerima dua email lalu memakai kode dari email pertama akan ditolak
("kode salah, sisa percobaan …") walau kodenya pernah valid.
**Status:** perilaku dipertahankan (satu kode aktif = permukaan serangan lebih kecil).
**Mitigasi:** pesan verifikasi menyebut sisa percobaan dan menyuruh minta kode baru
(`OtpService.php:188-198`), dan layar Android memberi jeda kirim ulang (`otp_resend_wait`).

### BK-08 — Login admin & dokter tidak memakai OTP/2FA (Tinggi, **belum** dikerjakan)
**Bukti:** `app/Http/Controllers/Admin/AdminAuthController.php:53-93` — hanya email + kata sandi,
lalu `Auth::login()`; tidak ada langkah kode.
**Dampak:** satu kata sandi bocor = akses penuh ke data klinik. Padahal infrastruktur OTP-nya
sudah ada dan sudah terbukti dipakai untuk ganti sandi.
**Status:** sengaja tidak saya ubah pada audit ini karena mengubah alur login menyentuh
kebiasaan operasional (dan akun demo). Masuk daftar rekomendasi prioritas 1 di §11.

### BK-09 — Pesan API `forgot-password` berbahasa Inggris (Rendah)
**Bukti:** `app/Http/Controllers/Api/AuthController.php:663` —
`'If your email is registered, you will receive a password reset code.'`
**Dampak:** aplikasi berbahasa Indonesia, pesan ini muncul apa adanya bila ditampilkan.
**Status:** belum diubah karena pesan ini tidak ditampilkan di jalur sukses aplikasi
(`ResetPasswordSheet.kt:145-158` hanya memakai `Result.Error`), tetapi tetap dicatat agar
konsisten saat nanti diekstrak ke katalog bahasa.

### BK-10 — Peringatan "email mungkin terlambat" ditulis langsung di Kotlin (Rendah → sudah dirapikan)
**Bukti sebelum:** `ui/screens/profile/ProfileScreen.kt:350-356` dan `:1117-1121` memuat teks
Indonesia hardcoded.
**Perbaikan:** dipindah ke `strings.xml:32` (`otp_mail_delayed_hint`) dan dipakai lewat
`stringResource(...)` di kedua tempat.

### BK-11 — Email bisa ditahan domain penerima (Sedang, di luar kendali server)
**Bukti:** alamat uji user berdomain sekolah (`smk.belajar.id`); email dari domain luar sering
dikarantina kebijakan Workspace. Server sendiri sudah menerima email (`235 Accepted`, lalu
`MailDeliveryCheck` terkirim).
**Mitigasi:** email uji menyertakan instruksi "tandai bukan spam"; pesan OTP aplikasi sudah
menyuruh cek Spam/Promosi (`strings.xml:33`). Untuk penerima berdomain sekolah/kantor,
whitelist pengirim di sisi admin domain adalah satu-satunya jalan pasti.

### BK-12 — Tidak ada verifikasi nomor telepon / WhatsApp sebagai jalur cadangan (Rendah)
**Bukti:** semua verifikasi identitas memakai email; `phone` disimpan tetapi tidak pernah
diverifikasi (`app/Http/Controllers/Api/AuthController.php:214-247`).
**Status:** dicatat sebagai keterbatasan cakupan, bukan bug.

### BK-13 — Subjek email berpola "Kode OTP …" ditahan filter Gmail (Tinggi → sudah diperbaiki)
**Bukti eksperimen (21 Nov 2026).** Dua email dikirim ke alamat yang sama, dari pengirim dan jalur
yang sama, dengan satu variabel berbeda:

| Uji | Subjek | Isi | Hasil di Gmail |
|---|---|---|---|
| E1 | `Kode OTP pendaftaran PetHeal` | template "email uji" — **tanpa** kode, tanpa tombol | **tidak masuk** |
| E2 | `Uji pengiriman email PetHeal` | **template OTP asli**, berisi kotak kode `000000` | **masuk** |
| E3 | `Uji pengiriman email PetHeal` | template undangan dokter (ada tautan) | menunggu konfirmasi user |

Karena isi OTP lengkap lolos (E2) sementara subjek bermuatan "Kode OTP" ditahan (E1), pemicunya
**subjek**, bukan isi email dan bukan SMTP/host.

**Sesudah:** seluruh subjek diverifikasi memakai pola `PetHeal: <tindakan>` dan tidak lagi memuat
kata "OTP" (`app/Mail/OtpMail.php:55-140`):

| Purpose | Subjek baru |
|---|---|
| register | `PetHeal: pendaftaran akun Anda` |
| reset | `PetHeal: atur ulang kata sandi akun Anda` |
| delete_account | `PetHeal: konfirmasi hapus akun` |
| email_change | `PetHeal: konfirmasi email baru akun Anda` |
| admin_register | `PetHeal: pendaftaran admin — <nama klinik>` |
| admin_password | `PetHeal: kata sandi admin — <nama klinik>` |
| doctor_password | `PetHeal: kata sandi portal dokter — <nama klinik>` |

**Uji regresi:** `OtpMailDeliveryTest::test_no_verification_email_uses_a_code_otp_subject`
(7 purpose, memastikan subjek tidak memuat "OTP" dan diawali "PetHeal").

### BK-17 — Kode OTP ditulis di teks tersembunyi (preheader) (Sedang → sudah diperbaiki)
**Bukti sebelum:** `resources/views/emails/otp.blade.php:20-25` dan `emails/otp-admin.blade.php:26-30`
menaruh kode di `<div style="display:none">`. Bukan pemicu utama (lihat BK-13), tetapi tetap harus
dihapus karena: teks tak terlihat berisi kode adalah pola phishing, dan kode ikut tampil di pratinjau
notifikasi layar kunci ponsel.
**Sesudah:** kedua preheader berisi teks netral tanpa kode.
**Uji:** `OtpMailDeliveryTest::test_otp_email_never_repeats_the_code_in_hidden_text` (+ versi admin)
— memastikan kode hanya muncul **sekali** di HTML email.

### BK-14 — Alat uji hanya memakai template "email uji" → tidak bisa mengisolasi template (Sedang → sudah diperbaiki)
**Bukti:** `petheal:mail-test` sebelumnya hanya mengirim `MailDeliveryCheck`. Karena masalah bisa
berada di template (BK-13) bukan SMTP, menguji template lain-lain membuat masalah tetap tersembunyi.
**Sesudah:** `petheal:mail-test` menerima `--template=check|otp|doctor` dan memakai mailable asli
alur masing-masing (OTP, undangan dokter).
**Uji:** 3 tes di `OtpMailDeliveryTest` (otp / doctor / template tak dikenal → gagal).

### BK-15 — Undangan dokter gagal permanen bila email dokter sudah jadi akun pet owner (Sedang, menunggu keputusan produk)
**Bukti:** `app/Http/Controllers/Doctor/DoctorAuthController.php:160` (`if (User::where('email', $email)->exists())`)
dan `app/Http/Controllers/Admin/DoctorController.php:227` melakukan hal sama. Dokter yang juga pernah
daftar sebagai pengguna aplikasi (peran `user`) dengan email Gmail yang sama tidak bisa mengaktifkan
portal dokter dengan email itu selamanya.
**Dampak:** dokter terpaksa ganti email (yang mungkin tidak dia mau pakai) atau admin menolak undangan.
**Status:** sengaja belum diubah — menautkan akun pet owner ke akun dokter menyentuh model data/peran;
membutuhkan keputusan produk (opsi: layanan konversi akun, atau ubah email dokter, atau izinkan
`doctor` menimpa `user` dengan syarat tertentu). Dicatat di §9.

### BK-16 — Tautan undangan dokter memakai `APP_URL` saat dibuat lewat CLI (Rendah, aturan pemakaian)
**Bukti:** `app/Http/Controllers/Admin/DoctorController.php:240` memakai `route('doctor.activate.token')`.
Saat dijalankan lewat HTTP, host mengikuti permintaan (domain ngrok) → benar. Saat dibuat lewat
CLI/tinker (tanpa request), host memakai `APP_URL` (`http://127.0.0.1:8000`) → tautan rusak di ponsel.
**Status:** aturan pemakaian: kirim undangan dokter dari browser, bukan dari tinker. (Tidak ada kode
yang diubah.)

---

### BK-18 — Semua email verifikasi memakai satu pengirim Gmail biasa (Tinggi → sudah diperbaiki)
**Bukti:** seluruh 7 tujuan OTP dan tautan aktivasi dokter dikirim lewat `MAIL_MAILER`
(`smtp.gmail.com` dengan `petheal.klinik@gmail.com`). Pengirim Gmail pribadi tidak punya reputasi
pengirim untuk pesan berisi kode, sehingga filter penerima mudah menahannya (lihat BK-13).
**Sesudah:** email verifikasi dipisah ke jalur Brevo (`App\Support\VerificationMailer`,
mailer `brevo` di `config/mail.php`) dengan alamat pengirim yang diverifikasi di akun Brevo.
Email non-verifikasi dan notifikasi push Firebase tidak berubah.
**Fallback:** kalau `BREVO_SMTP_USERNAME`/`BREVO_SMTP_PASSWORD` kosong, jalur kembali ke
`MAIL_MAILER` — aplikasi tidak pernah gagal kirim karena setup Brevo belum lengkap.
**Uji:** `tests/Feature/VerificationMailerTest.php` (5 tes, 19 asersi).
**Panduan setup:** `docs/EMAIL_BREVO.md`.

### BK-19 — Memilih mailer lewat `$mailable->mailer()` tidak berpengaruh (Tinggi → sudah diperbaiki)
**Bukti:** `Illuminate\Mail\Mailer::sendMailable()` menjalankan
`$mailable->mailer($this->name)->send($this)`, sehingga nilai mailer yang dipasang di dalam
mailable **ditimpa** mailer yang sedang aktif. Akibatnya semua email verifikasi tetap keluar lewat
`MAIL_MAILER` (Gmail) meski konfigurasi Brevo sudah terisi — tanpa error, tanpa log.
**Sesudah:** jalur dipilih saat mengirim di `App\Support\VerificationMailer::send()`
(`Mail::mailer(...)`), bukan di mailable.
**Uji:** `VerificationMailerTest::test_send_uses_the_selected_mailer_and_reports_success`.

### BK-20 — Kredensial SMTP Brevo ditolak (535), kegagalan tidak terlihat (Tinggi → sudah ditangani)
**Bukti:** `storage/logs/laravel.log` 21 Nov 2026 22:20 —
`Failed to authenticate on SMTP server with username "petheal.klinik@gmail.com" ... 535 5.7.8 Authentication failed`.
Brevo memakai kolom **Login** dari tab SMTP (bisa berbentuk `1234567@smtp-brevo.com`), bukan
alamat email akun. Selama percobaan sebelumnya, email tetap "berhasil" karena sebenarnya
dikirim lewat Gmail.
**Sesudah:** dua jalur tersedia — Brevo REST API (`BREVO_API_KEY`, tanpa autentikasi SMTP) yang
diutamakan, dan SMTP relay sebagai cadangan. Kegagalan sekarang dilaporkan sebagai `false` +
`Log::warning` berisi pesan asli Brevo, plus satu percobaan ulang.
**Uji:** `BrevoApiTransportTest` (2 tes), `VerificationMailerTest::test_send_retries_once_then_reports_failure`.

### BK-21 — Tidak ada cara melihat email sampai atau tidak (Sedang → sudah diperbaiki)
**Bukti:** sebelum ini, "email tidak masuk" hanya bisa dicek manual di dashboard Brevo.
**Sesudah:** `php artisan petheal:brevo-events <email> --days=3` membaca
`GET /v3/smtp/statistics/events` dan menampilkan delivered/blocked/bounced + alasan + Message-ID.
Log aplikasi juga mencatat Message-ID tiap kiriman (`Log::info('Email verifikasi terkirim')`).
**Uji:** `tests/Feature/BrevoEventsTest.php` (3 tes).

### BK-22 — Brevo API menolak payload dengan `name` kosong (Sedang → sudah diperbaiki)
**Bukti:** percobaan pertama lewat REST API gagal dengan
`Brevo API menolak pengiriman: name is missing in to`
(`storage/logs/laravel.log`, 21 Nov 2026 22:45). `Mail::to($email)` menghasilkan nama kosong,
dan `App\Support\Mail\BrevoApiTransport` mengirim `"name": ""`.
**Sesudah:** kunci `name` hanya disertakan bila ada isinya
(`BrevoApiTransport::toAddress()`).
**Uji:** `BrevoApiTransportTest::test_it_posts_the_rendered_message_and_returns_the_message_id`.

## 6. Perubahan yang dikerjakan pada audit ini

| # | File | Perubahan | Alasan |
|---|---|---|---|
| 1 | `app/Console/Commands/TestMailDelivery.php` (baru) | perintah `petheal:mail-test` | memisahkan masalah SMTP dari masalah penerima, tanpa membaca log |
| 2 | `app/Console/Commands/OtpStatus.php` (baru) | perintah `petheal:otp-status` | memisahkan "permintaan tidak sampai" dari "email tidak diterima" |
| 3 | `app/Mail/MailDeliveryCheck.php` (baru) | mailable email uji (tanpa kode) | email uji tidak boleh bisa dipakai masuk akun |
| 4 | `resources/views/emails/mail-check.blade.php` (baru) | template HTML email uji | konsisten dengan email OTP yang sudah ada |
| 5 | `resources/views/emails/mail-check-text.blade.php` (baru) | versi teks email uji | email tanpa versi teks lebih mudah dianggap spam |
| 6 | `.env`, `.env.example` | `MAIL_SCHEME=smtp` + komentar | `MAIL_ENCRYPTION` tidak dibaca Laravel 12 (BK-02) |
| 7 | `PetHeal_Android/.../data/repository/AuthRepository.kt` | `requestRegisterOtp` & `resendRegisterOtp` mengembalikan `OtpSentResponse` | `mail_sent` tidak boleh dibuang (BK-03, BK-04) |
| 8 | `.../ui/screens/auth/RegisterViewModel.kt` | bila `mailSent == false`: tampilkan pesan, jangan pindah layar | user tidak lagi menunggu email yang tidak ada |
| 9 | `.../ui/screens/auth/OtpVerificationViewModel.kt` | "kirim ulang" jujur soal kegagalan | snackbar lama berbohong |
| 10 | `.../ui/screens/profile/ProfileScreen.kt` + `res/values/strings.xml` | peringatan email → string resource | BK-10 |
| 11 | `tests/Feature/OtpMailDeliveryTest.php` (baru) | 12 tes / 29 asersi | mengunci janji `mail_sent`, perilaku dua perintah baru, dan preheader tanpa kode |
| 12 | `resources/views/emails/otp.blade.php`, `emails/otp-admin.blade.php` | hapus kode dari teks pratinjau tersembunyi | BK-17 (spam signal + bocor di layar kunci) |
| 13 | `app/Console/Commands/TestMailDelivery.php` | `--template=check\|otp\|doctor` | BK-14 (uji template asli, bukan hanya template uji) |
| 14 | `app/Console/Commands/SyncTunnelUrl.php` (baru) | perintah `petheal:tunnel-url` | menyamakan `APP_URL`/`ASSET_URL` dengan domain ngrok (§4.1) |
| 15 | `tests/Feature/SyncTunnelUrlTest.php` (baru) | 4 tes / 10 asersi | memastikan hanya dua baris URL yang berubah + gagal rapi saat ngrok mati |
| 16 | `app/Mail/OtpMail.php` | 7 subjek email → pola `PetHeal: <tindakan>` tanpa kata "OTP" | BK-13: subjek "Kode OTP …" ditahan filter Gmail |
| 17 | `app/Support/VerificationMailer.php` (baru), `config/mail.php`, `app/Mail/OtpMail.php:54`, `app/Mail/DoctorInviteMail.php:44`, `.env`/`.env.example` | jalur kirim email verifikasi pindah ke Brevo, fallback otomatis ke `MAIL_MAILER` | BK-18: pengirim Gmail pribadi mudah ditahan filter |
| 18 | `app/Support/VerificationMailer.php::send()`, `app/Services/OtpService.php:send()`, `app/Http/Controllers/Admin/DoctorController.php:242` | jalur kirim dipilih saat mengirim + 1 percobaan ulang + Message-ID dicatat | BK-19/BK-20: `$mailable->mailer()` ditimpa Laravel |
| 19 | `app/Support/Mail/BrevoApiTransport.php` (baru), `config/mail.php`, `app/Providers/AppServiceProvider.php:31` | jalur Brevo REST API sebagai prioritas, SMTP relay sebagai cadangan | BK-20: 535 tidak terlihat dari aplikasi |
| 20 | `app/Console/Commands/BrevoEvents.php` (baru), `config/services.php` | `petheal:brevo-events` membaca status kirim dari Brevo | BK-21: tidak ada alat pembukti |
| 21 | `app/Support/Mail/BrevoApiTransport.php::toAddress()` | `name` hanya dikirim bila terisi | BK-22: Brevo menolak `name` kosong |
| 22 | `app/Console/Commands/BrevoEvents.php` | saringan email dilakukan lokal + `limit` diterapkan setelah saring | filter Brevo mengembalikan daftar kosong meski kejadian ada |

Tidak ada perubahan pada: skema `otp_codes`, masa berlaku, batas percobaan, throttle, alamat
pengirim, atau kredensial. (Perbaikan template email OTP di baris 12 memang di luar "tidak ada
perubahan template" yang dinyatakan versi pertama dokumen ini — itu perbaikan lanjutan.)

---

## 7. Cara memverifikasi ulang (checklist)

Jalankan dari `PetHeal_Backend`:

```bash
# 1. SMTP sehat? (template uji biasa)
php artisan petheal:mail-test alamat-anda@gmail.com --template=check

# 2. Apakah template OTP ikut tertsampai? (kode contoh 000000, tidak tersimpan)
php artisan petheal:mail-test alamat-anda@gmail.com --template=otp

# 3. Apakah template undangan dokter ikut tertsampai? (token contoh, tidak valid)
php artisan petheal:mail-test alamat-anda@gmail.com --template=doctor

# 4. Apakah kode untuk sebuah email benar-benar terbit?
php artisan petheal:otp-status alamat-anda@gmail.com --purpose=register

# 5. Kalau ingin melihat semua tujuan sekaligus
php artisan petheal:otp-status alamat-anda@gmail.com
```

Hasil yang diharapkan:

| Langkah | Hasil benar |
|---|---|
| `mail-test --template=check` | `BERHASIL: server email menerima dan mengantar pesan.` |
| `mail-test --template=otp` | `BERHASIL ...`, email subjek "Kode OTP pendaftaran PetHeal" masuk inbox/Spam |
| `mail-test --template=doctor` | `BERHASIL ...`, email subjek "Aktivasi akun dokter" masuk |
| `otp-status` setelah minta kode di aplikasi | 1 baris, status **AKTIF**, sisa waktu > 0, percobaan `0/5` |
| `otp-status` untuk email yang belum pernah minta | `Tidak ada kode OTP untuk email ini.` |

**Aturan isolasi masalah:** kalau `check` masuk tetapi `otp` tidak → masalahnya di template/filter isi,
bukan SMTP (lihat BK-13/BK-17). Kalau ketiganya tidak masuk → masalah SMTP atau penerima.

**Uji A/B subjek vs isi** (dipakai untuk membuktikan BK-13) — kirim dua email berjarak ±20 detik dan
bandingkan mana yang masuk:

```bash
# subjek "berbahaya", isi netral
php artisan petheal:mail-test alamat@gmail.com --template=check --subject="Kode OTP pendaftaran PetHeal"
# subjek netral, isi template OTP asli
php artisan petheal:mail-test alamat@gmail.com --template=otp --subject="Uji pengiriman email PetHeal"
```

Checklist manual di aplikasi Android:

1. Daftar dengan email Gmail pribadi (bukan domain sekolah/kantor) → email OTP harus masuk < 60 detik.
2. Buka layar verifikasi → angka hitung mundur berjalan, tombol "Kirim ulang" terkunci sampai 0.
3. Tunggu kode kedaluwarsa (10 menit), lalu masukkan kode lama → pesan "sudah kedaluwarsa".
4. Minta kode, lalu tekan "Kirim ulang", lalu pakai kode dari email **pertama** → harus ditolak
   (bukti BK-07 bekerja sesuai desain).
5. Masukkan kode salah 5× → pesan sisa percobaan menurun, percobaan ke-6 meminta kode baru.
6. Matikan jaringan server (`php artisan serve` dihentikan) lalu minta kode → aplikasi menampilkan
   pesan gagal, **tidak** berpindah ke layar OTP (perbaikan BK-03).

---

## 8. Yang sengaja tidak dikerjakan

1. **Menampilkan kode OTP di layar/log saat mode lokal.** Terlihat praktis untuk demo, tetapi
   ini pintu belakang: satu salah konfigurasi (`APP_ENV`/`APP_DEBUG`) dan kode verifikasi
   siapa pun bisa dibaca dari respons API. Tidak saya tambahkan.
2. **Menghapus anti-enumeration pada `forgot-password`.** Lihat BK-05.
3. **Menambahkan OTP pada login admin/dokter.** Perubahan alur, perlu keputusan produk (BK-08).
4. **Mengganti pengirim ke domain sendiri (mis. `no-reply@petheal.id`) dengan SPF/DKIM.** Butuh
   domain + akses DNS; tidak bisa dikerjakan dari repo.
5. **Verifikasi nomor telepon.** Di luar cakupan (BK-12).
6. **Menautkan akun pet owner ke akun dokter saat email sama.** Perubahan model data/peran (BK-15).

---

## 9. Rekomendasi lanjutan (urut prioritas)

| Prioritas | Rekomendasi | Alasan |
|---|---|---|
| 1 | OTP/2FA saat login admin & super admin | satu kata sandi bocor = seluruh data klinik (BK-08) |
| 2 | Monitor pengiriman email: hitung `Log::warning('OTP email gagal dikirim')` per hari, tampilkan di dashboard super admin | kegagalan SMTP sekarang hanya terlihat kalau ada yang membuka log |
| 3 | Kirim OTP lewat WhatsApp/SMS sebagai cadangan | jalur email bisa ditahan domain penerima (BK-11) |
| 4 | Putuskan perlakuan email dokter yang sama dengan akun pet owner (konversi akun / izinkan timpa / ganti email) | BK-15 |
| 5 | Pindahkan pengirim ke domain PetHeal + SPF/DKIM/DMARC | deliverability jangka panjang |
| 6 | Jadikan masa berlaku kode bisa diatur per klinik (10 menit bisa terasa pendek untuk email sekolah yang tertunda) | mengurangi keluhan "kode kedaluwarsa" |
| 7 | Samakan bahasa seluruh pesan API ke katalog `resources/lang` | BK-09 |

---

## 10. Lampiran — keluaran nyata perintah

```
$ php artisan petheal:mail-test christopher.aldo1@smk.belajar.id
Konfigurasi aktif:
  mailer      : smtp
  host        : smtp.gmail.com:587
  scheme      : smtp
  username    : p***@gmail.com
  password    : terisi
  pengirim    : petheal.klinik@gmail.com
  APP_URL     : http://127.0.0.1:8000

Mengirim email uji ke christopher.aldo1@smk.belajar.id ...
BERHASIL: server email menerima dan mengantar pesan.
```

```
$ php artisan petheal:otp-status christopher.aldo1@smk.belajar.id
Email   : christopher.aldo1@smk.belajar.id
Ditemukan: 1 baris kode

+----------+--------+--------------+-----------------+-------------------+
| Tujuan   | Status | Sisa berlaku | Percobaan salah | Terbit            |
+----------+--------+--------------+-----------------+-------------------+
| register | AKTIF  | 53s          | 0/5             | 21 Nov 2026 09:43 |
+----------+--------+--------------+-----------------+-------------------+
```

```
$ php artisan petheal:mail-test christopher.aldo1@smk.belajar.id --template=otp
...
Mengirim email uji ke christopher.aldo1@smk.belajar.id ...
Template diuji: otp
BERHASIL: server email menerima dan mengantar pesan.
```

```
$ php artisan petheal:mail-test christopher.aldo1@smk.belajar.id --template=doctor
...
Template diuji: doctor
BERHASIL: server email menerima dan mengantar pesan.
```

Dua email di atas (plus satu email `--template=check` sebelumnya) adalah email uji yang dikirim ke
inbox user selama audit: isinya tidak pernah berisi kode yang sah — kode OTP di email `--template=otp`
adalah `000000` yang tidak tersimpan di database, dan tautan di `--template=doctor` adalah token acak
yang tidak valid.

```
$ php artisan test tests/Feature/OtpMailDeliveryTest.php
Tests:  12 passed (29 assertions)
```

Bukti kredensial SMTP sehat (probe tanpa mengirim email, sandi tidak pernah dicetak):

```
server: 220 smtp.gmail.com ESMTP ...
EHLO  : 250-smtp.gmail.com at your service
STARTTLS: 220 2.0.0 Ready to start TLS
pass   : 235 2.7.0 Accepted
```


### Bukti akhir: OTP asli terkirim lewat Brevo API (21 Nov 2026)

```
POST /api/auth/register/request-otp  → success=true, mail_sent=true
petheal:otp-status  → register AKTIF, terbit 22:49
petheal:brevo-events → 22:49:56 requests | 22:49:57 delivered
Message-ID: <202611211546.56574786236@smtp-relay.mailin.fr>
```

Jalur yang terpakai: `brevo-api`. Sebelum BK-19 diperbaiki, email yang sama tetap keluar lewat
Gmail; sebelum BK-20/22 diperbaiki, pengiriman gagal tanpa terlihat dari aplikasi.
