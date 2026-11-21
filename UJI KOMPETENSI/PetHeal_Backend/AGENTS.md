# AGENTS.md — PetHeal Backend (Laravel 12)

> File ini DIBACA OTOMATIS tiap sesi agent. Patuhi tanpa kecuali.

## 1. Bahasa & Gaya Komunikasi
- Bahasa: **Indonesia** untuk penjelasan ke user. Istilah teknis tetap English (migration, middleware, tenant).
- Jawaban singkat, faktual, tanpa pujian berlebihan. Jangan tebak — baca file dulu via `read`/`grep`/`glob`.
- Setiap menyebut fungsi/kode, sertakan `path:line` (cth `routes/web.php:32`).

## 2. Stack & Cara Jalan (JANGAN diubah seenaknya)
- Laravel 12, PHP ^8.2 (runtime 8.3.31), MySQL `pet_heal` @127.0.0.1:3306 root/(kosong), Sanctum 4, Spatie Permission 6.
- Serve BENAR: `php artisan serve --host=127.0.0.1 --port=8000` (HTTP murni) + `ngrok http 8000` → `https://envious-reselect-darn.ngrok-free.dev`.
- LARANGAN KERAS: jangan akses `https://127.0.0.1:8000` (menyebabkan `Invalid request (Unsupported SSL request)`). Jangan pakai `start-https.bat` / `ssl/ssl-router.php` — `php -S` tidak support TLS, file itu menyesatkan, ABAIKAN.
- `.env` aktif (dicek langsung, bukan asumsi): `APP_URL=http://127.0.0.1:8000`, `ASSET_URL` sama, `APP_TIMEZONE=Asia/Jakarta`. Bila `APP_URL` diisi URL ngrok, `AppServiceProvider` force `https`; URL aset tetap mengikuti host request lewat `UseRequestAssetOrigin`.
- Tunnel: saat `APP_DEBUG=true`, halaman **web** berpotensi menampilkan stack trace. `AppServiceProvider` otomatis mematikan debug untuk host ngrok/cloudflare (API memang sudah tersanitasi).
- Admin demo: `admin@petheal.com` / `admin123`. URL: lokal `http://127.0.0.1:8000/admin/login`, ngrok `https://envious-reselect-darn.ngrok-free.dev/admin/login`.

## 3. Gaya Kode
- Ikuti gaya Laravel 12 existing: controller di `app/Http/Controllers/Api/*` dan `Admin/*`, FormRequest bila validasi panjang, Resource secukupnya.
- Migration: selalu sertakan `down()`, nama deskriptif `xxxx_xx_xx_xxxx_add_clinic_id_to_X_table.php`, tambah index untuk kolom filter (`clinic_id`, `status`, `booking_date`).
- Jangan hapus field/kolom/endpoint lama (backward compat). Field baru = nullable/opsional dulu, backfill, baru NOT NULL.
- Validasi: slug `lowercase-hyphen unique`, warna `^#[0-9A-Fa-f]{6}$`, upload logo max 2MB (png/jpg/webp) via Intervention Image seperti photo dokter/pet.

## 4. Arsitektur Multi-Klinik (keputusan final, JANGAN diganti tanpa izin user)
- Model: **shared DB + `clinic_id`** (BUKAN DB-per-klinik). Lihat `docs/CONSTRAINTS.md` + `docs/TENANCY_RULES.md`.
- **Staf** klinik (`clinic_admin`, `admin`, `doctor`) terikat 1 klinik (`users.clinic_id`).
- **Pasien** (`role = user`) adalah **akun GLOBAL**: `users.clinic_id` = NULL, memilih klinik per booking. Definisi "pasien klinik" hanya lewat transaksi → gunakan `App\Services\PatientScope` (`scopeClinicUsers`, `scopeClinicPets`). JANGAN memfilter pasien dengan `users.clinic_id`.
- Android = **1 APK dinamis** (BUKAN flavors / beda APK). Tenant via header `X-Clinic-Slug` + `GET /public/clinics`.
- 100% GRATIS: tanpa service berbayar baru. Tetap MySQL lokal, ngrok Free, Firebase Spark, Midtrans Sandbox.

## 5. Perintah Standar
```powershell
# dari folder PetHeal_Backend
php artisan config:clear; php artisan cache:clear
php artisan migrate --force
php artisan route:list --path=api
php artisan route:list --path=admin
curl.exe -s --max-time 15 "https://envious-reselect-darn.ngrok-free.dev/api/health"
curl.exe -s --max-time 10 http://127.0.0.1:8000/admin/login -o NUL -w "%{http_code}\n"
```
- Jangan `commit/push/PR` kecuali diminta eksplisit. Jangan ubah git config, jangan force-push.
- Jangan buat file baru kecuali perlu; utamakan edit file existing. Jangan buat `*.md` dokumentasi baru di luar `docs/` kecuali diminta.

## 6. Kapan Harus Bertanya (STOP, jangan tebak)
- Tanya jika: (a) mau ganti model tenancy, (b) mau hapus kolom/endpoint lama, (c) mau tambah dependency berbayar, (d) slug/role/SDK berubah, (e) request user ambigu antara 2+ interpretasi.
- Gunakan tool `question` dengan opsi + rekomendasi jelas.

## 7. Verifikasi Wajib Setelah Edit
- Setelah edit backend: `php artisan config:clear` + curl lokal + curl ngrok + `route:list` relevan.
- Setelah edit route `/`: pastikan `/` = 200 (pernah bug `config('app.maintenance')` array-truthy → sekarang `app()->isDownForMaintenance()`, lihat `routes/web.php:32`).
- Update `docs/PROGRESS.md` (apa diubah, kenapa, apa tersisa) setiap selesai fase. Lihat `docs/PLAN.md` untuk posisi fase.

## 8. Roadmap UI/UX (WAJIB)
- Sebelum menyentuh UI/UX, baca `docs/UIUX_ROADMAP.md`. Dokumen itu sumber kebenaran urutan fase, non-goal, dan aturan "jangan bikin data palsu / dead code".
- Setiap fase selesai: update `docs/PROGRESS.md` + tulis laporan di `docs/phases/`.
- Jangan mulai Fase 3/4 tanpa menyelesaikan fase sebelumnya. Jangan membuat mock membership/SaaS/chat.

## 9. Peta Aset Tampilan (jangan bikin ulang yang sudah ada)

Sebelum menulis tampilan, JavaScript, atau format angka baru, pakai yang sudah ada di bawah ini. Aturan lengkapnya: `docs/AUDIT_FIXES_REPORT.md` Bagian II (temuan) + Bagian III (status implementasi).

### 9.1 JavaScript konsol admin — `resources/js/admin/`

| Berkas | Tanggung jawab | Jangan |
|---|---|---|
| `boot.js` | Membaca `<script type="application/json" id="admin-boot">` (flashes, labels, routes) | Menulis teks terjemahan di dalam JS |
| `layout.js` | Laci sidebar, tombol tema, `showToast()` | Menambah `<script>` inline baru di layout |
| `command-palette.js` | Command palette Ctrl/Cmd+K | Menduplikasi logika pencarian |
| `ui.js` | Escape/Tab/fokus modal + satu jalur konfirmasi (`data-confirm`, `data-confirm-type="HAPUS"`) | Menulis `onsubmit="return confirm(...)"` baru |

- Halaman baru: cukup tambah `data-confirm` pada form destruktif; tidak perlu JS sendiri.
- Label baru untuk modul di atas: tambahkan kunci di `resources/lang/{id,en}/admin.php` lalu daftarkan di blok `admin-boot` pada `layouts/admin.blade.php`.
- Slug/nilai yang tampil ke pengguna wajib lewat katalog bahasa; nilai kanonis di database tidak pernah diterjemahkan.

### 9.2 Format angka & tanggal — `app/helpers.php`

| Helper | Guna | Contoh |
|---|---|---|
| `format_rupiah($n)` | Satu-satunya format rupiah di tampilan | `Rp 1.250.000` |
| `format_qty($n)` | Stok/jumlah tanpa nol di belakang koma | `42`, `42,5` |
| `format_tanggal($d, $preset, $fallback)` | Tanggal mengikuti bahasa aktif | preset `date`/`datetime`/`time`/`long`/`iso` |

- Jangan menulis `'Rp ' . number_format(...)`, `number_format(...)` bertumpuk `rtrim`, atau `->format('d M Y')` di Blade. Pengecualian tunggal: `admin/payments/receipt_pdf.blade.php` (dompdf, lebar kolom tetap).
- Tanggal kosong harus tampil `—`, bukan `01 Jan 1970`.

### 9.3 Katalog bahasa

| Katalog | Isi |
|---|---|
| `resources/lang/{id,en}/admin.php` | Konsol admin (tombol, label, konfirmasi, pesan) |
| `resources/lang/{id,en}/doctor.php` | **Seluruh** portal dokter (207 kunci; paritas id/en wajib dijaga) |
| `resources/lang/{id,en}/{common,menu,clinics,payments,petshop,inventory,crud,dashboard}.php` | Domain masing-masing |

- Menambah kunci: tambahkan di `id` **dan** `en` dengan nama yang sama. Kunci tanpa padanan akan tampil sebagai teks kosong.
- Tidak ada teks Indonesia langsung di `resources/views/doctor/**`.

### 9.4 Umpan balik & validasi

- Pesan flash **hanya** dari layout (toast `admin-boot`). Halaman tidak boleh punya `@if(session('success'))` sendiri, kecuali halaman auth yang tidak memakai `layouts/admin.blade.php`.
- Validasi **hanya** dari `resources/views/components/admin/validation-errors.blade.php` yang sudah dirender di layout. Jangan menambah blok `$errors` baru di halaman.

### 9.5 Parameter query & tampilan

- Pencarian memakai `search` (bukan `q`). `q` tetap dibaca sebagai alias agar tautan lama hidup.
- Semua `paginate()` wajib `->withQueryString()`.
- Dilarang memakai gradien (`bg-gradient-to-*`, `linear-gradient`) di panel web. Warna solid: sidebar `bg-emerald-950`.
- Dilarang `href="#"`; gunakan `<button type="button">` atau elemen non-tautan.

### 9.6 Diagnosa kode OTP & email

Semua kode verifikasi lewat satu pintu: `app/Services/OtpService.php` (7 tujuan: register, reset, delete_account, email_change, admin_register, admin_password, doctor_password) dan satu mailable `app/Mail/OtpMail.php`. Jangan membuat mekanisme OTP baru di controller.

Kalau ada laporan "kode OTP tidak masuk", urutannya:

1. `php artisan petheal:otp-status <email>` — kalau tidak ada baris, permintaan tidak pernah sampai ke server.
2. `php artisan petheal:mail-test <email> --template=check|otp|doctor` — kalau gagal, masalahnya SMTP/kredensial (535 = App Password Gmail salah/dicabut). Kalau `check` masuk tetapi `otp` tidak, masalahnya di template/filter isi, bukan SMTP.
3. `app/Services/OtpService::send()` hanya menulis `Log::warning('OTP email gagal dikirim')`; pemanggil **wajib** memakai nilai balik `bool`-nya dan tidak boleh mengklaim "kode terkirim" saat `false`. API aplikasi mengekspos ini sebagai `mail_sent`.

4. Email verifikasi (OTP + tautan aktivasi dokter) punya jalur kirim sendiri: `App\Support\VerificationMailer::send()` → mailer `brevo-api` (prioritas, bila `BREVO_API_KEY` terisi) atau `brevo` (SMTP relay). **Jalur harus dipilih saat mengirim** (`Mail::mailer()`), karena `$mailable->mailer()` ditimpa `Mailer::sendMailable()`. Kalau `BREVO_SMTP_USERNAME`/`BREVO_SMTP_PASSWORD` kosong, otomatis kembali ke `MAIL_MAILER`. Jangan panggil `Mail::mailer('brevo')` langsung di controller dan jangan menambah mailable verifikasi baru tanpa `VerificationMailer::applyTo()`.

5. Bukti "sampai atau tidak" ada di Brevo: `php artisan petheal:brevo-events <email> --days=3` (butuh `BREVO_API_KEY`). Log aplikasi juga mencatat Message-ID setiap kiriman.

Detail lengkap: `docs/OTP_EMAIL_AUDIT.md` (diagnosa) dan `docs/EMAIL_BREVO.md` (setup Brevo, gejala → penyebab, rollback).

Saat demo lewat ngrok, samakan URL aset lebih dulu: `php artisan petheal:tunnel-url` (mengubah hanya `APP_URL` + `ASSET_URL`). Tanpa itu dashboard dibuka lewat tunnel akan memuat CSS/JS dari `127.0.0.1` perangkat pengunjung dan tampil rusak. Pengiriman email OTP tidak dipengaruhi host/URL sama sekali.
