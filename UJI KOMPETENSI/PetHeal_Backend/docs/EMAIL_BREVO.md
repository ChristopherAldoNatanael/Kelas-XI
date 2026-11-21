# Email verifikasi lewat Brevo (OTP & tautan aktivasi)

Dokumen ini hanya membahas **satu hal**: memindahkan pengiriman email verifikasi
(kode OTP dan tautan aktivasi akun dokter) ke Brevo. Email lain dan notifikasi
push **tidak** ikut dipindah.

- Kode: `app/Support/VerificationMailer.php` (satu-satunya penentu jalur kirim)
- Pemakai: `app/Mail/OtpMail.php:54` (7 tujuan) dan `app/Mail/DoctorInviteMail.php:44`
- Konfigurasi: `config/mail.php` → mailer `brevo` + blok `verification`
- Kredensial: `.env` → `BREVO_SMTP_*`, `BREVO_API_KEY`, `VERIFICATION_MAIL_FROM_*`

---

## 1. Kenapa dipisah, bukan semua email dipindah

Uji A/B 21 November 2026 membuktikan filter Gmail menahan email verifikasi yang
datang dari pengirim Gmail biasa (lihat `docs/OTP_EMAIL_AUDIT.md` bagian BK-13).
Penyedia transaksional seperti Brevo punya reputasi pengirim sendiri sehingga
email verifikasi jauh lebih jarang ditahan.

Yang ikut pindah ke Brevo **hanya**:

| Mailable | Isi |
|---|---|
| `app/Mail/OtpMail.php` | kode OTP: register, reset, hapus akun, ganti email, admin klinik, kata sandi admin, kata sandi dokter |
| `app/Mail/DoctorInviteMail.php` | tautan aktivasi akun portal dokter |

Yang **tidak** berubah:

- Notifikasi push Firebase/FCM (`app/Services/FCMService.php`, job `SendFcmNotification`) — jalur terpisah, tidak memakai mailer.
- Email hasil pendaftaran klinik (`ClinicRegistrationApprovedMail`, `ClinicRegistrationRejectedMail`) — tetap memakai `MAIL_MAILER`.
- Semua email lain di panel admin.

Kalau nanti ada jenis email verifikasi baru, jangan panggil `Mail::mailer('brevo')`
langsung di controller. Bungkus mailable-nya dengan `VerificationMailer::applyTo()`
di dalam `build()` supaya aturan tetap di satu tempat.

---

## 2. Yang harus disiapkan di dashboard Brevo

### 2.1 Akun
1. Daftar di <https://app.brevo.com/>.
2. Paket gratis sudah cukup: **300 email/hari**. Kode OTP pendaftaran jauh di bawah itu.

### 2.2 Alamat pengirim (wajib)
1. Menu **Senders, Domains & Dedicated IPs** → **Senders** → **Add a sender**.
2. Isi alamat pengirim, misal `verifikasi@petheal.id` atau `petheal.klinik@gmail.com`.
3. Buka inbox alamat itu dan klik tautan verifikasi dari Brevo. **Tanpa langkah ini
   Brevo akan menolak mengirim** dengan pesan "sender is invalid".

Alamat inilah yang nanti diisi ke `VERIFICATION_MAIL_FROM_ADDRESS`.

### 2.3 Autentikasi domain (opsional, disarankan untuk produksi)

**Autentikasi domain tidak ada hubungannya dengan localhost atau ngrok.** Yang
diautentikasi adalah **domain di alamat pengirim** (bagian setelah `@`), dan record-nya
dipasang di **DNS domain itu** — bukan di server aplikasi, bukan di ngrok. Jadi aplikasi
boleh tetap jalan di `127.0.0.1:8000` atau lewat tunnel; emailnya tetap terautentikasi.

1. Masih di menu yang sama → tab **Domains** → **Add a domain** (isi mis. `petheal.id`).
2. Pilih **Authenticate** — otomatis bila penyedia DNS didukung, atau manual dengan menyalin
   record yang Brevo tampilkan (**Brevo code** TXT, **DKIM**, **DMARC**) ke DNS domain.
3. Tunggu status domain menjadi **Authenticated** (biasanya < 1 jam).
4. Ganti `VERIFICATION_MAIL_FROM_ADDRESS` ke alamat di domain itu (mis. `no-reply@petheal.id`)
   dan daftarkan juga alamat tersebut sebagai sender di langkah 2.2.

Catatan DMARC: hanya boleh ada **satu** record `_dmarc` per domain. Kalau domain sudah punya
DMARC, pakai jalur **manual** dan jangan biarkan Brevo menimpanya.

**Kalau belum punya domain (kondisi sekarang: `petheal.klinik@gmail.com`).** Domain gratis
seperti `@gmail.com` memang tidak bisa diautentikasi Brevo. Yang berlaku hanya verifikasi
**sender** (langkah 2.2), dan email tetap terkirim lewat Brevo. Konsekuensinya:

| Tanpa domain sendiri | Dengan domain sendiri |
|---|---|
| Gmail penerima menampilkan "via smtp-relay.brevo.com" | Tidak ada label "via" |
| Lebih sering masuk Promosi/Spam | Cenderung masuk Inbox |
| Tidak bisa memasang DMARC | DMARC bisa dipasang |
| Cukup untuk demo, tugas, dan volume kecil | Diperlukan untuk pemakaian nyata |

Untuk naik ke tahap itu cukup beli satu domain murah, lalu **tidak ada kode yang perlu diubah**
— hanya `VERIFICATION_MAIL_FROM_ADDRESS` di `.env` dan daftar sender di Brevo.

### 2.4 Jalur pengiriman: API atau SMTP (pilih salah satu)

`App\Support\VerificationMailer` mencoba jalur secara berurutan:

1. **Brevo REST API** — aktif bila `BREVO_API_KEY` terisi. Tidak memakai password SMTP.
2. **Brevo SMTP relay** — aktif bila `BREVO_SMTP_USERNAME` + `BREVO_SMTP_PASSWORD` terisi.
3. **Mailer bawaan** (`MAIL_MAILER`) — bila keduanya kosong.

REST API lebih diutamakan karena kegagalan SMTP relay (535) sulit terlihat: pesannya
ditolak saat autentikasi, bukan saat pengiriman. API mengembalikan `messageId` dan
pesan error asli Brevo.

### 2.5 Kunci SMTP (jalur cadangan)
1. Menu **Settings** (ikon gerigi) → **SMTP & API** → tab **SMTP**
   (tautan langsung: <https://app.brevo.com/settings/keys/smtp>).
2. Catat nilai **Login**. Bentuknya alamat email akun Brevo Anda **atau**
   `1234567@smtp-brevo.com`. **Bukan** `smtp-relay.brevo.com` — kesalahan ini
   penyebab paling umum error `535`.
3. Klik **Generate a new SMTP key** → simpan nilainya sekarang (hanya tampil sekali).
   Pilih varian **Standard (64 karakter)**. Kalau muncul error autentikasi aneh,
   buat ulang dengan varian **Short (15 karakter)**.

### 2.6 Opsional tapi berguna
- **Authorized IPs** (Security → Authorized IPs): batasi kunci SMTP hanya dari IP server.
- **Transactional → Logs**: memantau status `delivered`, `opened`, `bounced` per email.
- **Statistics → Email**: melihat apakah kuota 300/hari mendekati batas.

---

## 3. Isi `.env`

```dotenv
BREVO_SMTP_HOST=smtp-relay.brevo.com
BREVO_SMTP_PORT=587
BREVO_SMTP_USERNAME=<nilai Login dari langkah 2.4>
BREVO_SMTP_PASSWORD=<SMTP key dari langkah 2.4>

VERIFICATION_MAIL_FROM_ADDRESS=<alamat dari langkah 2.2>
VERIFICATION_MAIL_FROM_NAME="PetHeal"
```

Kesalahan yang paling sering terjadi: nilai diisi di **`.env.example`**, bukan **`.env`**.
Aplikasi hanya membaca `.env`; `.env.example` cuma contoh dan biasanya ikut di-commit, jadi
jangan pernah menyimpan kredensial asli di sana. Tanda belum terbaca: baris
`jalur verifikasi` masih menampilkan `smtp (...)`, bukan `brevo @ smtp-relay.brevo.com:587`.

Catatan:

- `BREVO_SMTP_USERNAME` + `BREVO_SMTP_PASSWORD` adalah **satu-satunya saklar**.
  Selama salah satunya kosong, email verifikasi otomatis memakai `MAIL_MAILER`
  (sekarang Gmail SMTP). Jadi tidak ada kondisi "aplikasi mati karena Brevo belum
  disiapkan".
- Port `587` = STARTTLS. Kalau jaringan server memblokir 587, ganti ke `465`
  dan tambahkan `'scheme' => 'smtps'` di `config/mail.php` (mailer `brevo`).
- Jangan pernah commit `.env`. `.env.example` sengaja dikosongkan.
- Kalau aplikasi dijalankan lewat ngrok, jalankan `php artisan petheal:tunnel-url` sebelum
  mengirim undangan dokter: tautan aktivasi diambil dari `APP_URL`, jadi tanpa itu dokter
  menerima tautan `127.0.0.1`. Ini **hanya** soal isi tautan, tidak memengaruhi SPF/DKIM.

---

## 4. Cara memastikan sudah bekerja

```bash
php artisan config:clear
php artisan petheal:mail-test alamat-anda@gmail.com --template=otp
```

Baris pertama output harus menampilkan:

```
  jalur verifikasi (OTP & tautan aktivasi): brevo @ smtp-relay.brevo.com:587 sebagai x***@smtp-brevo.com
```

Kalau masih tertulis `... (Brevo belum diisi: BREVO_SMTP_USERNAME/PASSWORD kosong)`,
berarti `.env` belum terbaca: jalankan `php artisan config:clear` lagi dan pastikan
tidak ada `bootstrap/cache/config.php`.

Contoh keluaran yang benar:

```
jalur verifikasi (OTP & tautan aktivasi): brevo-api (REST API, BREVO_API_KEY terisi)
Jalur kirim  : brevo-api
BERHASIL: server email menerima dan mengantar pesan.
```

Untuk melihat **status sebenarnya** (delivered / blocked / bounced) langsung dari Brevo:

```bash
php artisan petheal:brevo-events alamat-anda@gmail.com --days=3
```

Butuh `BREVO_API_KEY` (Settings → SMTP & API → tab **API keys**). Ini jawaban pasti untuk
pertanyaan "emailnya sampai atau tidak", tanpa menebak folder Spam.

Uji kedua untuk tautan aktivasi dokter:

```bash
php artisan petheal:mail-test alamat-anda@gmail.com --template=doctor
```

Keduanya memakai template asli. Kode di email uji OTP sengaja `000000` dan tidak
tersimpan di database, jadi tidak bisa dipakai masuk.

---

## 5. Kalau gagal (gejala → penyebab)

| Gejala | Penyebab & tindakan |
|---|---|
| `535 5.7.8 Authentication failed` | Pasangan login/key SMTP tidak cocok. Ambil **Login** apa adanya dari tab SMTP (bentuknya bisa `1234567@smtp-brevo.com`, bukan alamat Gmail Anda) dan buat SMTP key baru. Cara paling cepat: pakai jalur REST API dengan `BREVO_API_KEY`. |
| `535` padahal kredensial terlihat benar | Key 64 karakter terpotong oleh klien. Buat SMTP key varian **Short (15 karakter)**. |
| `Sender is invalid` / 550 | `VERIFICATION_MAIL_FROM_ADDRESS` belum diverifikasi di Brevo (langkah 2.2). |
| `Brevo API menolak pengiriman: name is missing in to` | Penerima tanpa nama. Sudah ditangani otomatis (kunci `name` tidak dikirim bila kosong). |
| `Baca log` menampilkan "Tidak ada kejadian" padahal baru kirim | Kejadian Brevo muncul 30–60 detik setelah `requests`. Tunggu lalu ulangi. |
| SMTP key hilang dari akun / ada email keamanan dari Brevo | Ada koneksi dari IP tak dikenal. Setujui IP server di email itu, atau aktifkan Authorized IPs. |
| Email terkirim tetapi masuk Promosi/Spam | Domain belum diautentikasi (langkah 2.3). |
| Kuota terlampaui | Brevo gratis 300/hari. Kirim ulang besok atau naikkan paket. |

---

## 6. Kembali ke jalur lama (rollback)

Kosongkan dua baris ini, lalu `php artisan config:clear`:

```dotenv
BREVO_SMTP_USERNAME=
BREVO_SMTP_PASSWORD=
```

Tidak ada kode yang perlu diubah. Email verifikasi kembali memakai `MAIL_MAILER`.

---

## 7. Pemeliharaan

- Rotasi SMTP key setiap 6–12 bulan (Brevo mendukung masa berlaku 7 hari s/d 1 tahun).
- Kalau ganti alamat pengirim, ubah **dua** tempat: sender di Brevo **dan**
  `VERIFICATION_MAIL_FROM_ADDRESS`.
- Tes yang menjaga aturan ini: `tests/Feature/VerificationMailerTest.php`
  (jalur Brevo, fallback saat kosong, dan email non-verifikasi tidak ikut pindah).
