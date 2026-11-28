# UAT Setelah Perbaikan UI/UX dan Performa

Tanggal: 28 November 2026

Gunakan checklist ini setelah deploy atau setelah `npm run build`.

## Cara cepat

1. Login admin.
2. Buka setiap halaman di bawah.
3. Pastikan tidak ada teks terpotong, gambar rusak, tombol tidak berfungsi,
   atau error di console browser.
4. Ulangi halaman utama pada lebar 390px.
5. Ulangi dashboard pada dark mode.

---

# 1. Halaman wajib — Admin

## Dashboard
`/admin`

Periksa:

- [ ] Kartu ringkasan tampil.
- [ ] Chart pembayaran, dokter, booking, dan pendapatan tampil.
- [ ] Tidak ada error console “Chart.js bundle is missing”.
- [ ] Tombol “Terapkan” filter tanggal berfungsi.
- [ ] Batang chart pendapatan terlihat jelas.
- [ ] Mode gelap tetap terbaca.
- [ ] Sidebar hijau terang/gelap sesuai tema.

## Booking
`/admin/bookings`  
`/admin/schedule`

- [ ] Tabel tampil.
- [ ] Foto hewan kecil tampil dan tidak berat.
- [ ] Tab daftar/jadwal berfungsi.
- [ ] Filter tanggal dan dokter berfungsi.
- [ ] Detail booking terbuka.
- [ ] Tombol konfirmasi, selesai, batal, dan reminder berfungsi.

## Antrian
`/admin/queue`

- [ ] Filter tanggal dan dokter punya label yang terbaca oleh screen reader.
- [ ] Tombol Hari Ini/Besok berfungsi.
- [ ] Panggil, panggil ulang, mulai layanan, selesai berfungsi.
- [ ] Nomor antrian tidak dobel.

## Walk-in & Darurat
`/admin/walk-ins`

- [ ] Autocomplete pemilik muncul setelah 2 karakter.
- [ ] Pilih pemilik mengisi daftar hewan.
- [ ] Walk-in biasa mendapat nomor antrian.
- [ ] Darurat wajib memilih triage.
- [ ] Pemilik baru bisa dibuat tanpa email.

## Ruang Klinis
`/admin/clinical`

- [ ] Tujuh tab tampil.
- [ ] Tab bisa diklik.
- [ ] Panah kiri/kanan memindahkan tab.
- [ ] `aria-selected` berpindah.
- [ ] Pagination muncul jika data lebih dari 25.
- [ ] Form publikasi template klinis berfungsi.
- [ ] Form publikasi template consent berfungsi.
- [ ] Pemulangan rawat inap berfungsi.
- [ ] Pembatalan rujukan berfungsi.

## Rekam Medis
`/admin/medical-records`  
`/admin/medical-records/{id}`  
`/admin/medical-records/{id}/edit`

- [ ] Foto hewan tampil dari thumbnail.
- [ ] Filter status/tanggal berfungsi.
- [ ] Detail rekam medis terbuka.
- [ ] Form SOAP, vital, resep, lampiran tampil.
- [ ] Edit rekam medis tidak kehilangan data.
- [ ] Tombol ekspor PDF/CSV berfungsi.
- [ ] Resume PDF terbuka.
- [ ] Panel darurat tampil untuk rekam medis darurat.

## Pembayaran
`/admin/payments`

- [ ] Ringkasan pembayaran tampil.
- [ ] Filter berfungsi.
- [ ] Detail pembayaran/booking terbuka.
- [ ] Konfirmasi pembayaran berfungsi.
- [ ] Refund berfungsi.
- [ ] Kirim reminder berfungsi.
- [ ] Ekspor PDF berfungsi.

## Pengguna
`/admin/users`

- [ ] Daftar pengguna tampil.
- [ ] Foto pengguna/hewan memakai thumbnail.
- [ ] Filter role dan pencarian berfungsi.
- [ ] Detail pengguna terbuka.
- [ ] Tambah/edit pengguna berfungsi.
- [ ] Hapus pengguna memiliki konfirmasi.

## Obat
`/admin/medicines`  
`/admin/medicines/expiry`  
`/admin/medicines/mutations`

- [ ] Daftar obat tampil.
- [ ] Filter dan pencarian berfungsi.
- [ ] Monitoring kedaluwarsa tampil.
- [ ] Mutasi stok tampil.
- [ ] Form mutasi berfungsi.
- [ ] Import/export berfungsi.
- [ ] Halaman terasa lebih cepat dari sebelumnya.

## Dokter
`/admin/doctors`  
`/admin/doctors/{id}`  
`/admin/doctors/{id}/schedule`

- [ ] Foto dokter memakai thumbnail, bukan file 1,8 MB.
- [ ] Tidak ada URL `?t=...` yang berubah setiap refresh.
- [ ] Daftar dokter tampil cepat.
- [ ] Tambah/edit dokter berfungsi.
- [ ] Undang akun dokter berfungsi.
- [ ] Jadwal dan cuti dokter berfungsi.

## Permintaan Layanan
`/admin/service-requests`

- [ ] Daftar permintaan tampil.
- [ ] Detail permintaan terbuka.
- [ ] Susun penawaran berfungsi.
- [ ] Slot dokter muncul.
- [ ] Terima/tolak permintaan berfungsi.

## Permintaan Privasi / PDP
`/admin/privacy-requests`

- [ ] Daftar permintaan tampil.
- [ ] Filter status/tipe berfungsi.
- [ ] Detail permintaan terbuka.
- [ ] Verifikasi, mulai, selesai, tolak berfungsi.
- [ ] Berkas jawaban bisa diunggah/dibuat.
- [ ] Unduh berkas jawaban berfungsi.
- [ ] Erasure menampilkan alasan retensi/legal hold.

## Audit Log
`/admin/audit-logs`

- [ ] Daftar audit tampil.
- [ ] Filter berfungsi.
- [ ] Tidak ada data klinik lain.

## Pengaturan
`/admin/settings`

- [ ] Tab profil tampil.
- [ ] Tab keamanan tampil.
- [ ] Tab klinik tampil.
- [ ] Simpan profil berfungsi.
- [ ] Ganti password dengan OTP berfungsi.
- [ ] Logo klinik tampil.

## Pengaturan Pembayaran
`/admin/payment-settings`

- [ ] Status Midtrans tampil.
- [ ] Riwayat versi kredensial tampil.
- [ ] Simpan kredensial berfungsi.
- [ ] Tidak ada server key yang tampil di halaman.

## Notifikasi
`/admin/notification-settings`

- [ ] Hanya super admin yang bisa membuka.
- [ ] Simpan template berfungsi.

## Pet Shop
`/admin/pet-shop`  
`/admin/pet-shop/products`  
`/admin/pet-shop/orders`  
`/admin/pet-shop/pickups`  
`/admin/pet-shop/sales`

- [ ] Dashboard Pet Shop tampil.
- [ ] Produk tampil dan gambar tidak berat.
- [ ] Import/export produk berfungsi.
- [ ] Pesanan tampil.
- [ ] Pembayaran offline berfungsi.
- [ ] Pickup dan verifikasi kode berfungsi.
- [ ] Riwayat penjualan tampil.

## Layanan dan Kategori
`/admin/services`  
`/admin/service-categories`

- [ ] Daftar layanan tampil.
- [ ] Tambah/edit/hapus layanan berfungsi.
- [ ] Import/export layanan berfungsi.
- [ ] Kategori layanan hanya bisa diubah super admin.

---

# 2. Super Admin

- [ ] `/admin/clinics`
- [ ] `/admin/clinics/create`
- [ ] `/admin/clinics/{id}/edit`
- [ ] `/admin/join-requests`
- [ ] `/admin/service-categories`
- [ ] `/admin/notification-settings`
- [ ] Switch klinik berfungsi.
- [ ] Overview tanpa klinik tidak menampilkan data operasional klinik lain.

---

# 3. Portal Dokter

Login di `/doctor/login`.

- [ ] `/doctor` dashboard tampil.
- [ ] `/doctor/queue` hanya pasien dokter tersebut.
- [ ] `/doctor/bookings` tampil.
- [ ] `/doctor/medical-records` tampil.
- [ ] `/doctor/medical-records/create/{bookingId}` berfungsi.
- [ ] `/doctor/medical-records/{id}` tampil.
- [ ] `/doctor/medical-records/{id}/edit` berfungsi.
- [ ] `/doctor/service-requests` tampil.
- [ ] `/doctor/schedule` tampil.
- [ ] `/doctor/profile` tampil.
- [ ] Foto dokter memakai thumbnail.
- [ ] Sidebar dokter memakai label Praktik/Dokumentasi/Jadwal/Akun.

### Perpindahan admin ke portal dokter

- [ ] Buka `/admin/doctors/{id}` untuk dokter yang akunnya sudah aktif.
- [ ] Klik **Buka portal dokter**.
- [ ] Halaman login dokter terbuka, bukan dasbor admin.
- [ ] Muncul pemberitahuan **Sesi admin masih aktif**.
- [ ] Klik **Keluar dari akun admin**.
- [ ] Login dengan akun dokter berhasil masuk ke `/doctor`.
- [ ] Setelah logout dokter, admin bisa login kembali.
- [ ] Jika sesi admin tidak ditutup, memasukkan akun dokter mengganti sesi admin
      dan tidak membuka dua sesi sekaligus.

---

# 4. Halaman publik dan autentikasi

- [ ] `/`
- [ ] `/admin/login`
- [ ] `/admin/register`
- [ ] `/doctor/login`
- [ ] `/doctor/activate`
- [ ] Logo tampil dari `logo-320.png`.
- [ ] Login gagal menampilkan pesan yang jelas.
- [ ] OTP terkirim.
- [ ] Tidak ada stack trace saat error validasi.

---

# 5. Keamanan dan header

Jalankan:

```bash
curl -I https://domain-anda.com/admin/login
```

Periksa:

- [ ] `X-Content-Type-Options: nosniff`
- [ ] `X-Frame-Options: SAMEORIGIN`
- [ ] `Referrer-Policy`
- [ ] `Permissions-Policy`
- [ ] `Strict-Transport-Security` saat HTTPS
- [ ] `Content-Security-Policy-Report-Only`

Cek aset:

```bash
curl -I https://domain-anda.com/build/assets/app-xxxx.css
curl -I -H 'Accept-Encoding: gzip' https://domain-anda.com/build/assets/app-xxxx.css
```

- [ ] `Cache-Control: public, max-age=31536000, immutable`
- [ ] `Content-Encoding: gzip`
- [ ] `Vary: Accept-Encoding`

---

# 6. Mobile 390px

Ulangi minimal:

- [ ] Dashboard
- [ ] Booking
- [ ] Rekam medis
- [ ] Antrian
- [ ] Obat
- [ ] Ruang Klinis
- [ ] Walk-in
- [ ] Pembayaran
- [ ] Pengguna
- [ ] Pengaturan

Pastikan:

- [ ] Tidak ada scroll horizontal.
- [ ] Sidebar bisa dibuka/ditutup.
- [ ] Tombol minimal 44px dan mudah ditekan.
- [ ] Tabel bisa digulir horizontal di dalam wrapper.
- [ ] Tidak ada tombol tertutup footer.

---

# 7. Dark mode

- [ ] Dashboard.
- [ ] Sidebar tetap hijau dan terbaca.
- [ ] Label sekunder terbaca.
- [ ] Chart tetap terlihat.
- [ ] Tabel dan form tetap terbaca.

---

# 8. Performance spot check

- [ ] `/admin/doctors` terasa ringan.
- [ ] `/admin/medical-records` terasa ringan.
- [ ] `/admin/medicines` cepat.
- [ ] Gambar tidak berkedip atau rusak.
- [ ] Thumbnail muncul.
- [ ] Dashboard chart tetap muncul.
- [ ] Tidak ada error 500.

---

# 9. Cross-clinic

Gunakan dua klinik:

- [ ] Admin klinik A tidak bisa membuka booking klinik B.
- [ ] Admin klinik A tidak bisa membuka rekam medis klinik B.
- [ ] Admin klinik A tidak bisa membuka pengguna klinik B.
- [ ] Admin klinik A tidak bisa membuka pembayaran klinik B.
- [ ] Admin klinik A tidak bisa membuka PDP klinik B.
- [ ] Dokter hanya melihat pasien miliknya.
- [ ] Super admin bisa memilih klinik dengan jelas.
