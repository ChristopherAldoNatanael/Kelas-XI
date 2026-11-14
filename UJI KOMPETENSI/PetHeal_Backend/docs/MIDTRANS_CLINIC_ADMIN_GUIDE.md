# Panduan Admin Klinik — Menghubungkan Midtrans

Panduan ini untuk **Clinic Admin** yang menghubungkan akun Midtrans kliniknya
sendiri. Tidak ada kredensial pusat yang perlu diisi di sini.

## 1. Sebelum mulai

Siapkan dari dashboard Midtrans klinik (menu **Settings → Access Keys**):

- Merchant ID
- Client Key
- Server Key

Gunakan kredensial **Sandbox** untuk uji coba. Jangan pernah mengirim Server Key
lewat chat, email, atau tiket.

## 2. Buka halaman pengaturan

1. Login ke admin klinik.
2. Buka **Pengaturan → Pengaturan Pembayaran** (`/admin/payment-settings`).
3. Pastikan nama klinik di panel atas sudah benar.

Clinic Admin hanya dapat melihat dan mengubah kliniknya sendiri. Super Admin
yang belum memilih klinik akan melihat ringkasan semua klinik.

## 3. Isi kredensial Sandbox

1. Pada panel **Sandbox**, isi Merchant ID, Client Key, dan Server Key.
2. Klik **Simpan kredensial**. Ini membuat versi baru, tetapi pembayaran
   **belum aktif**.
3. Klik **Uji koneksi** pada baris versi tersebut.
4. Setelah hasil uji valid, klik **Aktifkan**.

Status akun berubah menjadi **Aktif** dan badge **Pembayaran online siap**
muncul. Selama masih `pending_verification` atau `invalid`, transaksi online
ditolak — bukan dialihkan ke akun pusat.

## 4. Daftarkan Notification URL

Salin **Notification URL** dari panel akun pusat (tombol **Salin**), lalu
tempelkan di dashboard Midtrans klinik pada menu **Settings → Payment
Notification URL**.

URL yang dipakai: `https://<domain-aplikasi>/api/midtrans/webhook`.

Tanpa URL ini, status pembayaran tidak akan diperbarui otomatis. Pengguna masih
bisa memakai tombol cek status, tetapi webhook tetap wajib didaftarkan.

## 5. Production

1. Simpan kredensial production pada panel **Production**.
2. Saat menyimpan, aktivasi, atau meminta persetujuan, masukkan **password akun
   admin** Anda. Konfirmasi berlaku 15 menit.
3. Aktivasi production juga membutuhkan persetujuan **Super Admin**.
4. Pastikan environment server sudah production sebelum mengaktifkan kredensial
   production. Kredensial environment yang berbeda tidak akan dipakai.

## 6. Rotasi kredensial

Menyimpan kredensial baru tidak menimpa versi lama. Transaksi yang sudah berjalan
tetap diverifikasi dengan versi yang dipakai saat transaksi dibuat. Setelah versi
baru lolos uji dan diaktifkan, transaksi **baru** memakai versi baru.

## 7. Arti status

| Status | Arti | Tindakan |
| --- | --- | --- |
| Belum diatur | Belum pernah menyimpan kredensial | Isi panel Sandbox/Production |
| Menunggu verifikasi | Kredensial tersimpan, belum lolos uji | Klik **Uji koneksi** |
| Aktif | Kredensial valid dan dipakai transaksi baru | Tidak ada |
| Tidak valid | Uji koneksi gagal | Periksa Merchant/Client/Server Key |
| Dinonaktifkan | Admin menonaktifkan pembayaran online | Aktifkan ulang setelah uji |
| Ditangguhkan | Ditangguhkan sistem/verifikasi | Hubungi Super Admin |

## 8. Jika pembayaran online tidak tersedia

Lihat panel **Monitor routing pembayaran**. Alasan yang umum:

- `clinic_credentials_pending` — kredensial belum lolos uji/aktivasi.
- `clinic_credentials_invalid` — kredensial ditolak Midtrans.
- `clinic_account_disabled` / `clinic_account_suspended` — akun dinonaktifkan.
- `environment_mismatch` — kredensial terdaftar untuk environment lain.
- `central_fallback_disabled` — klinik belum mengatur dan fallback pusat tidak
  diizinkan.

Konfigurasi klinik yang rusak **tidak pernah** dialihkan diam-diam ke akun
pusat. Perbaiki kredensial klinik terlebih dahulu.

## 9. Keamanan

- Server Key disimpan terenkripsi dan tidak pernah ditampilkan kembali, termasuk
  di API, log, atau aplikasi Android.
- Jangan membagikan Server Key. Jika bocor, rotasi segera dari dashboard
  Midtrans, lalu simpan versi baru di halaman ini.
- Aksi production meminta password ulang; jangan tinggalkan sesi admin terbuka.
- Jejak audit simpan/uji/aktivasi/persetujuan tersedia di bagian bawah halaman.
